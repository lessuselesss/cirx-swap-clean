<?php

namespace App\Blockchain;

use App\Blockchain\Exceptions\BlockchainException;
use Psr\Log\LoggerInterface;

/**
 * Ethereum Blockchain Client
 * 
 * Handles interactions with Ethereum and EVM-compatible blockchains
 */
class EthereumBlockchainClient extends AbstractBlockchainClient
{
    private int $chainId;
    private string $networkName;
    private ?string $privateKey;
    private array $tokenContracts;

    public function __construct(
        string $rpcUrl,
        int $chainId = 1,
        string $networkName = 'ethereum',
        ?string $backupRpcUrl = null,
        ?string $privateKey = null,
        array $tokenContracts = [],
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($rpcUrl, $backupRpcUrl, $logger);
        
        $this->chainId = $chainId;
        $this->networkName = $networkName;
        $this->privateKey = $privateKey;
        $this->tokenContracts = $tokenContracts;
    }

    /**
     * Get transaction by hash
     */
    public function getTransaction(string $txHash): ?array
    {
        try {
            $response = $this->rpcCall('eth_getTransactionByHash', [$txHash]);
            return $response['result'];
        } catch (BlockchainException $e) {
            if (str_contains($e->getMessage(), 'not found')) {
                return null;
            }
            throw $e;
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
     * Get token balance for an address
     */
    public function getTokenBalance(string $tokenAddress, string $walletAddress): string
    {
        // ERC-20 balanceOf function signature: 0x70a08231
        $functionSignature = '0x70a08231';
        $paddedAddress = str_pad(substr($walletAddress, 2), 64, '0', STR_PAD_LEFT);
        $data = $functionSignature . $paddedAddress;

        $response = $this->rpcCall('eth_call', [
            [
                'to' => $tokenAddress,
                'data' => $data
            ],
            'latest'
        ]);

        $balance = $this->hexToDec($response['result']);
        
        // Get token decimals to format properly
        $decimals = $this->getTokenDecimals($tokenAddress);
        return $this->parseTokenAmount($balance, $decimals);
    }

    /**
     * Get native token balance for an address (ETH)
     */
    public function getNativeBalance(string $walletAddress): string
    {
        $response = $this->rpcCall('eth_getBalance', [$walletAddress, 'latest']);
        $balanceWei = $this->hexToDec($response['result']);
        return $this->weiToEther($balanceWei);
    }

    /**
     * Send a transaction
     */
    public function sendTransaction(array $transactionData): string
    {
        if (!$this->privateKey) {
            throw new BlockchainException(
                "Private key not configured for {$this->networkName} blockchain",
                500,
                null,
                $this->networkName,
                'send_transaction'
            );
        }

        // For now, return a mock transaction hash
        // In production, this would sign and broadcast the transaction
        $mockTxHash = '0x' . bin2hex(random_bytes(32));
        
        $this->logger->info("Mock transaction sent", [
            'tx_hash' => $mockTxHash,
            'to' => $transactionData['to'] ?? null,
            'value' => $transactionData['value'] ?? null,
            'network' => $this->networkName
        ]);

        return $mockTxHash;
    }

    /**
     * Estimate gas for a transaction
     */
    public function estimateGas(array $transactionData): int
    {
        $response = $this->rpcCall('eth_estimateGas', [$transactionData]);
        return (int)$this->hexToDec($response['result']);
    }

    /**
     * Get gas price
     */
    public function getGasPrice(): string
    {
        $response = $this->rpcCall('eth_gasPrice');
        return $this->hexToDec($response['result']);
    }

    /**
     * Get the chain ID
     */
    public function getChainId(): int
    {
        return $this->chainId;
    }

    /**
     * Get the network name
     */
    public function getNetworkName(): string
    {
        return $this->networkName;
    }

    /**
     * Get token decimals
     */
    private function getTokenDecimals(string $tokenAddress): int
    {
        // Check if we have cached token info
        if (isset($this->tokenContracts[strtolower($tokenAddress)])) {
            return $this->tokenContracts[strtolower($tokenAddress)]['decimals'] ?? 18;
        }

        try {
            // ERC-20 decimals function signature: 0x313ce567
            $functionSignature = '0x313ce567';

            $response = $this->rpcCall('eth_call', [
                [
                    'to' => $tokenAddress,
                    'data' => $functionSignature
                ],
                'latest'
            ]);

            return (int)$this->hexToDec($response['result']);
        } catch (\Exception $e) {
            $this->logger->warning("Failed to get token decimals, using default", [
                'token_address' => $tokenAddress,
                'error' => $e->getMessage()
            ]);
            return 18; // Default to 18 decimals
        }
    }

    /**
     * Verify an ERC-20 token transfer by parsing transaction receipt logs
     */
    public function verifyTokenTransfer(
        string $txHash,
        string $tokenAddress,
        string $fromAddress,
        string $toAddress,
        string $expectedAmount
    ): bool {
        $receipt = $this->getTransactionReceipt($txHash);
        
        if (!$receipt || $receipt['status'] !== '0x1') {
            return false;
        }

        // Look for Transfer event logs
        // Transfer event signature: 0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef
        $transferEventSignature = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

        foreach ($receipt['logs'] as $log) {
            if (
                strtolower($log['address']) === strtolower($tokenAddress) &&
                isset($log['topics'][0]) &&
                $log['topics'][0] === $transferEventSignature &&
                isset($log['topics'][1]) && strtolower('0x' . substr($log['topics'][1], -40)) === strtolower($fromAddress) &&
                isset($log['topics'][2]) && strtolower('0x' . substr($log['topics'][2], -40)) === strtolower($toAddress)
            ) {
                $transferredAmount = $this->hexToDec($log['data']);
                $decimals = $this->getTokenDecimals($tokenAddress);
                $formattedAmount = $this->parseTokenAmount($transferredAmount, $decimals);

                // Check if the amount matches (with small tolerance for rounding)
                $expectedFloat = (float)$expectedAmount;
                $actualFloat = (float)$formattedAmount;
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
     * Wait for transaction confirmation
     */
    public function waitForConfirmation(
        string $txHash, 
        int $requiredConfirmations = 1, 
        int $timeoutSeconds = 300
    ): bool {
        $startTime = time();
        
        while ((time() - $startTime) < $timeoutSeconds) {
            $confirmations = $this->getTransactionConfirmations($txHash);
            
            if ($confirmations >= $requiredConfirmations) {
                return true;
            }

            sleep(5); // Wait 5 seconds before checking again
        }

        return false;
    }

    /**
     * Send ERC-20 token transfer
     */
    public function sendTokenTransfer(
        string $tokenAddress,
        string $toAddress,
        string $amount,
        int $gasLimit = 100000
    ): string {
        if (!$this->privateKey) {
            throw new BlockchainException(
                "Private key not configured for token transfer",
                500,
                null,
                $this->networkName,
                'send_token_transfer'
            );
        }

        $decimals = $this->getTokenDecimals($tokenAddress);
        $formattedAmount = $this->formatTokenAmount($amount, $decimals);

        // ERC-20 transfer function signature: 0xa9059cbb
        $functionSignature = '0xa9059cbb';
        $paddedToAddress = str_pad(substr($toAddress, 2), 64, '0', STR_PAD_LEFT);
        $paddedAmount = str_pad(dechex((int)$formattedAmount), 64, '0', STR_PAD_LEFT);
        
        $data = $functionSignature . $paddedToAddress . $paddedAmount;

        // For now, return mock transaction hash
        // In production, this would create, sign and send the transaction
        $mockTxHash = '0x' . bin2hex(random_bytes(32));
        
        $this->logger->info("Mock token transfer sent", [
            'tx_hash' => $mockTxHash,
            'token_address' => $tokenAddress,
            'to' => $toAddress,
            'amount' => $amount,
            'network' => $this->networkName
        ]);

        return $mockTxHash;
    }
}