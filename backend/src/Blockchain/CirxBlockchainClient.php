<?php

namespace App\Blockchain;

use App\Blockchain\Exceptions\BlockchainException;
use Psr\Log\LoggerInterface;
use CircularProtocol\Api\CircularProtocolAPI;

/**
 * CIRX Blockchain Client
 * 
 * Handles interactions with the Circular Protocol blockchain for CIRX token operations.
 * This client supports BOTH read operations (monitoring) AND write operations (CIRX transfers)
 * since CIRX transfers are server-side operations managed by this backend.
 * Generic EVM transaction methods removed - use sendCirxTransfer for CIRX operations.
 */
class CirxBlockchainClient extends AbstractBlockchainClient
{
    private string $cirxWalletAddress;
    private ?string $cirxPrivateKey;
    private int $cirxDecimals;
    private CircularProtocolAPI $cirxApi;

    public function __construct(
        string $rpcUrl,
        string $cirxWalletAddress,
        ?string $cirxPrivateKey = null,
        int $cirxDecimals = 18,
        ?string $backupRpcUrl = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($rpcUrl, $backupRpcUrl, $logger);
        
        $this->cirxWalletAddress = $cirxWalletAddress;
        $this->cirxPrivateKey = $cirxPrivateKey;
        $this->cirxDecimals = $cirxDecimals;
        
        // Initialize Circular Protocol API
        $this->cirxApi = new CircularProtocolAPI();
        
        // Set NAG URL based on environment (no key required)
        if ($rpcUrl && str_contains($rpcUrl, 'mainnet')) {
            $this->cirxApi->setNAGURL('https://nag.circularlabs.io/NAG_Mainnet.php?cep=');
        } else {
            $this->cirxApi->setNAGURL('https://nag.circularlabs.io/NAG.php?cep=');
        }
    }

    /**
     * Get transaction by hash
     */
    public function getTransaction(string $txHash): ?array
    {
        try {
            // Use the SDK's transaction retrieval method
            // Parameters: blockchain, txID, start, end (start/end can be 0 for single tx)
            $response = $this->cirxApi->getTransactionByID('circular', $txHash, 0, 0);
            return $response;
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not found') || $e->getCode() === 404) {
                return null;
            }
            throw new BlockchainException(
                "Failed to get CIRX transaction: " . $e->getMessage(),
                $e->getCode(),
                $e,
                'cirx',
                'get_transaction'
            );
        }
    }

    /**
     * Get transaction receipt by hash
     */
    public function getTransactionReceipt(string $txHash): ?array
    {
        try {
            $response = $this->rpcCall('eth_getTransactionReceipt', [$txHash]);
            return $response['result'];
        } catch (BlockchainException $e) {
            if (str_contains($e->getMessage(), 'not found')) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Get current block number
     */
    public function getBlockNumber(): int
    {
        $response = $this->rpcCall('eth_blockNumber');
        return (int)$this->hexToDec($response['result']);
    }

    /**
     * Get block by number
     */
    public function getBlock(int $blockNumber): ?array
    {
        $blockHex = $this->decToHex((string)$blockNumber);
        $response = $this->rpcCall('eth_getBlockByNumber', [$blockHex, true]);
        return $response['result'];
    }

    /**
     * Get CIRX token balance for an address
     */
    public function getTokenBalance(string $tokenAddress, string $walletAddress): string
    {
        // For CIRX, tokenAddress might be ignored as CIRX is the native token
        return $this->getCirxBalance($walletAddress);
    }

    /**
     * Get native balance (assuming CIRX is the native token)
     */
    public function getNativeBalance(string $walletAddress): string
    {
        return $this->getCirxBalance($walletAddress);
    }

    /**
     * Get CIRX balance for a wallet address
     */
    public function getCirxBalance(string $walletAddress): string
    {
        try {
            // NOTE: The SDK's getWalletBalance method has a bug - it uses lowercase "asset" 
            // but NAG API expects "Asset" with capital A. We'll call NAG directly for now.
            
            // Manual NAG call with correct capitalization
            $data = [
                'Blockchain' => '714d2ac07a826b66ac56752eebd7c77b58d2ee842e523d913fd0ef06e6bdfcae', // Circular Main Public
                'Address' => $walletAddress,
                'Asset' => 'CIRX', // Correct capitalization
                'Version' => '1.0.8'
            ];
            
            $response = $this->cirxApi->fetch($this->cirxApi->getNAGURL() . 'Circular_GetWalletBalance_', $data);
            
            if (isset($response->Result) && $response->Result === 200 && isset($response->Response->Balance)) {
                return (string)$response->Response->Balance;
            } else {
                throw new \Exception('NAG API returned error: ' . ($response->ERROR ?? json_encode($response)));
            }
        } catch (\Exception $e) {
            throw new BlockchainException(
                "Failed to get CIRX balance: " . $e->getMessage(),
                $e->getCode(),
                $e,
                'cirx',
                'get_balance'
            );
        }
    }

    // ERC-20 style balance method removed - CIRX is native token, use getCirxBalance() instead

    // Generic sendTransaction method removed - use sendCirxTransfer for CIRX operations

    /**
     * Send CIRX tokens to a recipient
     */
    public function sendCirxTransfer(string $recipientAddress, string $amount): string
    {
        $this->logger->info("Initiating CIRX transfer", [
            'from' => $this->cirxWalletAddress,
            'to' => $recipientAddress,
            'amount' => $amount
        ]);

        if (!$this->cirxPrivateKey) {
            throw new BlockchainException(
                "CIRX private key not configured for transaction signing",
                500,
                null,
                'cirx',
                'send_transfer'
            );
        }

        try {
            // Check sender balance first
            $senderBalance = $this->getCirxBalance($this->cirxWalletAddress);
            if (bccomp($senderBalance, $amount, $this->cirxDecimals) < 0) {
                throw BlockchainException::insufficientBalance(
                    'cirx',
                    $this->cirxWalletAddress,
                    $amount,
                    $senderBalance
                );
            }

            // For now, generate transaction parameters manually
            // TODO: Implement proper transaction construction
            $txId = '0x' . bin2hex(random_bytes(32));
            $timestamp = $this->cirxApi->getFormattedTimestamp();
            $nonce = $this->cirxApi->getWalletNonce('circular', $this->cirxWalletAddress);
            $payload = json_encode([
                'action' => 'transfer',
                'amount' => $amount,
                'asset' => 'CIRX'
            ]);
            
            // Sign the transaction
            $message = $txId . $this->cirxWalletAddress . $recipientAddress . $timestamp . $payload . $nonce;
            $signature = $this->cirxApi->signMessage($message, $this->cirxPrivateKey);
            
            // Send transaction
            $txHash = $this->cirxApi->sendTransaction(
                $txId,
                $this->cirxWalletAddress,
                $recipientAddress,
                $timestamp,
                'transfer',
                $payload,
                $nonce,
                $signature,
                'circular'
            );

            $this->logger->info("CIRX transfer completed", [
                'tx_hash' => $txHash,
                'from' => $this->cirxWalletAddress,
                'to' => $recipientAddress,
                'amount' => $amount
            ]);

            return $txHash;

        } catch (\Exception $e) {
            throw new BlockchainException(
                "Failed to send CIRX transfer: " . $e->getMessage(),
                $e->getCode(),
                $e,
                'cirx',
                'send_transfer'
            );
        }
    }

    // ERC-20 and native transfer helper methods removed - use sendCirxTransfer directly

    // Gas estimation and pricing methods removed - CIRX transfers handle gas internally

    /**
     * Get the chain ID (CIRX network specific)
     */
    public function getChainId(): int
    {
        try {
            $response = $this->rpcCall('eth_chainId');
            return (int)$this->hexToDec($response['result']);
        } catch (BlockchainException $e) {
            $this->logger->warning("Failed to get chain ID, using default", [
                'error' => $e->getMessage()
            ]);
            return 9999; // Default CIRX chain ID (placeholder)
        }
    }

    /**
     * Get the network name
     */
    public function getNetworkName(): string
    {
        return 'cirx';
    }

    /**
     * Verify a CIRX transfer by checking transaction receipt
     */
    public function verifyCirxTransfer(
        string $txHash,
        string $expectedRecipient,
        string $expectedAmount
    ): bool {
        $receipt = $this->getTransactionReceipt($txHash);
        
        if (!$receipt || $receipt['status'] !== '0x1') {
            return false;
        }

        $transaction = $this->getTransaction($txHash);
        if (!$transaction) {
            return false;
        }

        // Check recipient
        if (strtolower($transaction['to']) !== strtolower($expectedRecipient)) {
            return false;
        }

        // CIRX is native token - verify amount from transaction value
        $transferredAmount = $this->parseTokenAmount(
            $this->hexToDec($transaction['value']), 
            $this->cirxDecimals
        );
        
        $expectedFloat = (float)$expectedAmount;
        $actualFloat = (float)$transferredAmount;
        $tolerance = $expectedFloat * 0.001; // 0.1% tolerance

        return abs($expectedFloat - $actualFloat) <= $tolerance;
    }

    // ERC-20 transfer verification removed - CIRX is native token

    /**
     * Get transaction confirmation count
     */
    public function getTransactionConfirmations(string $txHash): int
    {
        $tx = $this->getTransaction($txHash);
        if (!$tx || !isset($tx['blockNumber'])) {
            return 0;
        }

        $currentBlock = $this->getBlockNumber();
        $txBlock = (int)$this->hexToDec($tx['blockNumber']);
        
        return max(0, $currentBlock - $txBlock + 1);
    }

    /**
     * Get CIRX wallet address
     */
    public function getCirxWalletAddress(): string
    {
        return $this->cirxWalletAddress;
    }

    // Contract address method removed - CIRX is native token, no contract address needed

    /**
     * Check if private key is configured
     */
    public function hasPrivateKey(): bool
    {
        return !empty($this->cirxPrivateKey);
    }

    /**
     * Check if NAG URL is configured
     */
    public function hasNAGURL(): bool
    {
        return !empty($this->cirxApi->getNAGURL());
    }

    /**
     * Get NAG URL being used
     */
    public function getNAGURL(): string
    {
        return $this->cirxApi->getNAGURL();
    }

    /**
     * Get SDK version
     */
    public function getSDKVersion(): string
    {
        return $this->cirxApi->getVersion();
    }
}