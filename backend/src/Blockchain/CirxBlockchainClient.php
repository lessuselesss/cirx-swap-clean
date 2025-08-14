<?php

namespace App\Blockchain;

use App\Blockchain\Exceptions\BlockchainException;
use Psr\Log\LoggerInterface;
use CircularProtocol\API\CircularProtocolAPI;
use CircularProtocol\API\Exceptions\CircularProtocolException;

/**
 * CIRX Blockchain Client
 * 
 * Handles interactions with the Circular Protocol blockchain for CIRX token operations
 * Note: This is a specialized client that might use different protocols than standard EVM
 */
class CirxBlockchainClient extends AbstractBlockchainClient
{
    private string $cirxWalletAddress;
    private ?string $cirxPrivateKey;
    private string $cirxContractAddress;
    private int $cirxDecimals;
    private CircularProtocolAPI $cirxApi;

    public function __construct(
        string $rpcUrl,
        string $cirxWalletAddress,
        string $cirxContractAddress,
        ?string $cirxPrivateKey = null,
        int $cirxDecimals = 18,
        ?string $backupRpcUrl = null,
        ?LoggerInterface $logger = null,
        ?string $apiKey = null
    ) {
        parent::__construct($rpcUrl, $backupRpcUrl, $logger);
        
        $this->cirxWalletAddress = $cirxWalletAddress;
        $this->cirxPrivateKey = $cirxPrivateKey;
        $this->cirxContractAddress = $cirxContractAddress;
        $this->cirxDecimals = $cirxDecimals;
        
        // Initialize Circular Protocol API
        $this->cirxApi = new CircularProtocolAPI([
            'api_key' => $apiKey,
            'base_url' => $rpcUrl,
            'timeout' => 30
        ]);
    }

    /**
     * Get transaction by hash
     */
    public function getTransaction(string $txHash): ?array
    {
        try {
            $response = $this->cirxApi->getTransaction($txHash);
            return $response;
        } catch (CircularProtocolException $e) {
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
            $balance = $this->cirxApi->getBalance($walletAddress);
            return (string)$balance;
        } catch (CircularProtocolException $e) {
            throw new BlockchainException(
                "Failed to get CIRX balance: " . $e->getMessage(),
                $e->getCode(),
                $e,
                'cirx',
                'get_balance'
            );
        }
    }

    /**
     * Get ERC-20 style CIRX balance
     */
    private function getErc20Balance(string $walletAddress): string
    {
        // ERC-20 balanceOf function signature: 0x70a08231
        $functionSignature = '0x70a08231';
        $paddedAddress = str_pad(substr($walletAddress, 2), 64, '0', STR_PAD_LEFT);
        $data = $functionSignature . $paddedAddress;

        $response = $this->rpcCall('eth_call', [
            [
                'to' => $this->cirxContractAddress,
                'data' => $data
            ],
            'latest'
        ]);

        $balance = $this->hexToDec($response['result']);
        return $this->parseTokenAmount($balance, $this->cirxDecimals);
    }

    /**
     * Send a CIRX transfer transaction
     */
    public function sendTransaction(array $transactionData): string
    {
        if (!$this->cirxPrivateKey) {
            throw new BlockchainException(
                "CIRX private key not configured for transaction signing",
                500,
                null,
                'cirx',
                'send_transaction'
            );
        }

        // For now, return a mock transaction hash
        // In production, this would:
        // 1. Create the transaction with proper nonce, gas, etc.
        // 2. Sign it with the private key
        // 3. Broadcast it to the CIRX network
        // 4. Return the actual transaction hash
        
        $mockTxHash = '0x' . bin2hex(random_bytes(32));
        
        $this->logger->info("Mock CIRX transaction sent", [
            'tx_hash' => $mockTxHash,
            'to' => $transactionData['to'] ?? null,
            'value' => $transactionData['value'] ?? null,
            'from' => $this->cirxWalletAddress
        ]);

        return $mockTxHash;
    }

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

            // Use Circular Protocol API to send transfer
            $txHash = $this->cirxApi->sendTransaction([
                'from' => $this->cirxWalletAddress,
                'to' => $recipientAddress,
                'amount' => $amount,
                'private_key' => $this->cirxPrivateKey
            ]);

            $this->logger->info("CIRX transfer completed", [
                'tx_hash' => $txHash,
                'from' => $this->cirxWalletAddress,
                'to' => $recipientAddress,
                'amount' => $amount
            ]);

            return $txHash;

        } catch (CircularProtocolException $e) {
            throw new BlockchainException(
                "Failed to send CIRX transfer: " . $e->getMessage(),
                $e->getCode(),
                $e,
                'cirx',
                'send_transfer'
            );
        }
    }

    /**
     * Send ERC-20 style CIRX transfer
     */
    private function sendErc20Transfer(string $recipientAddress, string $amount): string
    {
        $formattedAmount = $this->formatTokenAmount($amount, $this->cirxDecimals);

        // ERC-20 transfer function signature: 0xa9059cbb
        $functionSignature = '0xa9059cbb';
        $paddedToAddress = str_pad(substr($recipientAddress, 2), 64, '0', STR_PAD_LEFT);
        $paddedAmount = str_pad(dechex((int)$formattedAmount), 64, '0', STR_PAD_LEFT);
        
        $data = $functionSignature . $paddedToAddress . $paddedAmount;

        $transactionData = [
            'from' => $this->cirxWalletAddress,
            'to' => $this->cirxContractAddress,
            'data' => $data,
            'gas' => $this->decToHex('100000'), // 100k gas limit
        ];

        return $this->sendTransaction($transactionData);
    }

    /**
     * Send native CIRX transfer
     */
    private function sendNativeTransfer(string $recipientAddress, string $amount): string
    {
        $weiAmount = $this->formatTokenAmount($amount, $this->cirxDecimals);

        $transactionData = [
            'from' => $this->cirxWalletAddress,
            'to' => $recipientAddress,
            'value' => $this->decToHex($weiAmount),
            'gas' => $this->decToHex('21000'), // Standard transfer gas
        ];

        return $this->sendTransaction($transactionData);
    }

    /**
     * Estimate gas for a CIRX transaction
     */
    public function estimateGas(array $transactionData): int
    {
        try {
            $response = $this->rpcCall('eth_estimateGas', [$transactionData]);
            return (int)$this->hexToDec($response['result']);
        } catch (BlockchainException $e) {
            // If gas estimation fails, return conservative defaults
            $this->logger->warning("Gas estimation failed, using default", [
                'error' => $e->getMessage(),
                'transaction' => $transactionData
            ]);
            
            if (isset($transactionData['data'])) {
                return 100000; // ERC-20 transfer
            } else {
                return 21000; // Native transfer
            }
        }
    }

    /**
     * Get gas price for CIRX network
     */
    public function getGasPrice(): string
    {
        try {
            $response = $this->rpcCall('eth_gasPrice');
            return $this->hexToDec($response['result']);
        } catch (BlockchainException $e) {
            $this->logger->warning("Failed to get gas price, using default", [
                'error' => $e->getMessage()
            ]);
            return '20000000000'; // 20 Gwei default
        }
    }

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

        // Check amount
        if ($this->cirxContractAddress && $this->cirxContractAddress !== '0x0000000000000000000000000000000000000000') {
            // ERC-20 style transfer - check logs
            return $this->verifyErc20TransferAmount($receipt, $expectedAmount);
        } else {
            // Native transfer - check value
            $transferredAmount = $this->parseTokenAmount(
                $this->hexToDec($transaction['value']), 
                $this->cirxDecimals
            );
            
            $expectedFloat = (float)$expectedAmount;
            $actualFloat = (float)$transferredAmount;
            $tolerance = $expectedFloat * 0.001; // 0.1% tolerance

            return abs($expectedFloat - $actualFloat) <= $tolerance;
        }
    }

    /**
     * Verify ERC-20 transfer amount from logs
     */
    private function verifyErc20TransferAmount(array $receipt, string $expectedAmount): bool
    {
        // Transfer event signature: 0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef
        $transferEventSignature = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

        foreach ($receipt['logs'] as $log) {
            if (
                strtolower($log['address']) === strtolower($this->cirxContractAddress) &&
                isset($log['topics'][0]) &&
                $log['topics'][0] === $transferEventSignature
            ) {
                $transferredAmount = $this->parseTokenAmount(
                    $this->hexToDec($log['data']), 
                    $this->cirxDecimals
                );

                $expectedFloat = (float)$expectedAmount;
                $actualFloat = (float)$transferredAmount;
                $tolerance = $expectedFloat * 0.001; // 0.1% tolerance

                return abs($expectedFloat - $actualFloat) <= $tolerance;
            }
        }

        return false;
    }

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

    /**
     * Get CIRX contract address
     */
    public function getCirxContractAddress(): string
    {
        return $this->cirxContractAddress;
    }

    /**
     * Check if private key is configured
     */
    public function hasPrivateKey(): bool
    {
        return !empty($this->cirxPrivateKey);
    }
}