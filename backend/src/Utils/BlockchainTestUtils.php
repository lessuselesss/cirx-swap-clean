<?php

namespace App\Utils;

use App\Blockchain\EthereumBlockchainClient;
use InvalidArgumentException;
use RuntimeException;

/**
 * Blockchain Testing Utilities
 * 
 * Provides helper methods for blockchain operations in E2E tests
 */
class BlockchainTestUtils
{
    private EthereumBlockchainClient $client;
    private float $maxGasPrice;
    private int $gasLimit;
    
    public function __construct(EthereumBlockchainClient $client)
    {
        $this->client = $client;
        $this->maxGasPrice = floatval($_ENV['MAX_GAS_PRICE_GWEI'] ?? 20);
        $this->gasLimit = intval($_ENV['GAS_LIMIT'] ?? 21000);
    }
    
    /**
     * Check if wallet has sufficient balance for transaction
     */
    public function checkSufficientBalance(string $walletAddress, string $amount, string $token = 'ETH'): bool
    {
        try {
            if ($token === 'ETH') {
                $balance = $this->client->getBalance($walletAddress);
                return bccomp($balance, $amount, 18) >= 0;
            } else {
                $tokenBalance = $this->client->getTokenBalance($walletAddress, $this->getTokenContract($token));
                return bccomp($tokenBalance, $amount, 18) >= 0;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Estimate gas cost for transaction
     */
    public function estimateTransactionCost(string $to, string $amount, string $token = 'ETH'): array
    {
        try {
            $gasPrice = $this->client->getGasPrice();
            $gasPriceGwei = bcdiv($gasPrice, '1000000000', 9);
            
            // Use higher gas limit for token transfers
            $estimatedGas = $token === 'ETH' ? 21000 : 65000;
            
            $gasCostWei = bcmul($gasPrice, (string)$estimatedGas, 0);
            $gasCostEth = bcdiv($gasCostWei, '1000000000000000000', 18);
            
            return [
                'gas_price_wei' => $gasPrice,
                'gas_price_gwei' => $gasPriceGwei,
                'estimated_gas' => $estimatedGas,
                'gas_cost_wei' => $gasCostWei,
                'gas_cost_eth' => $gasCostEth,
                'is_affordable' => bccomp($gasPriceGwei, (string)$this->maxGasPrice, 9) <= 0
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'is_affordable' => false
            ];
        }
    }
    
    /**
     * Get current network status
     */
    public function getNetworkStatus(): array
    {
        try {
            $blockNumber = $this->client->getBlockNumber();
            $gasPrice = $this->client->getGasPrice();
            $chainId = $this->client->getChainId();
            
            return [
                'chain_id' => $chainId,
                'block_number' => $blockNumber,
                'gas_price_wei' => $gasPrice,
                'gas_price_gwei' => bcdiv($gasPrice, '1000000000', 9),
                'network_healthy' => true,
                'timestamp' => time()
            ];
        } catch (\Exception $e) {
            return [
                'network_healthy' => false,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ];
        }
    }
    
    /**
     * Wait for transaction to be mined
     */
    public function waitForTransaction(string $txHash, int $timeoutSeconds = 300): ?array
    {
        $startTime = time();
        
        while ((time() - $startTime) < $timeoutSeconds) {
            try {
                $receipt = $this->client->getTransactionReceipt($txHash);
                if ($receipt && isset($receipt['blockNumber'])) {
                    return $receipt;
                }
            } catch (\Exception $e) {
                // Transaction might not be mined yet
            }
            
            sleep(2);
        }
        
        return null;
    }
    
    /**
     * Get transaction details with retry logic
     */
    public function getTransactionWithRetry(string $txHash, int $maxRetries = 5): ?array
    {
        $retries = 0;
        
        while ($retries < $maxRetries) {
            try {
                $transaction = $this->client->getTransaction($txHash);
                if ($transaction) {
                    return $transaction;
                }
            } catch (\Exception $e) {
                // Retry on failure
            }
            
            $retries++;
            if ($retries < $maxRetries) {
                sleep(pow(2, $retries)); // Exponential backoff
            }
        }
        
        return null;
    }
    
    /**
     * Verify transaction matches expected parameters
     */
    public function verifyTransaction(string $txHash, array $expectedParams): array
    {
        $transaction = $this->getTransactionWithRetry($txHash);
        
        if (!$transaction) {
            return [
                'valid' => false,
                'error' => 'Transaction not found',
                'tx_hash' => $txHash
            ];
        }
        
        $errors = [];
        
        // Check recipient address
        if (isset($expectedParams['to'])) {
            if (strtolower($transaction['to']) !== strtolower($expectedParams['to'])) {
                $errors[] = "Recipient mismatch: expected {$expectedParams['to']}, got {$transaction['to']}";
            }
        }
        
        // Check amount for ETH transactions
        if (isset($expectedParams['value'])) {
            $actualValue = $this->weiToEth($transaction['value']);
            if (bccomp($actualValue, $expectedParams['value'], 18) !== 0) {
                $errors[] = "Amount mismatch: expected {$expectedParams['value']} ETH, got {$actualValue} ETH";
            }
        }
        
        // Check transaction status
        $receipt = $this->client->getTransactionReceipt($txHash);
        if ($receipt) {
            $status = isset($receipt['status']) ? hexdec($receipt['status']) : 1;
            if ($status !== 1) {
                $errors[] = "Transaction failed with status: {$status}";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'transaction' => $transaction,
            'receipt' => $receipt ?? null
        ];
    }
    
    /**
     * Generate test transaction hash (for simulation)
     */
    public function generateTestTxHash(): string
    {
        return '0x' . bin2hex(random_bytes(32));
    }
    
    /**
     * Create mock transaction receipt
     */
    public function createMockReceipt(string $txHash, string $blockNumber = null): array
    {
        $blockNumber = $blockNumber ?? '0x' . dechex(rand(1000000, 9999999));
        
        return [
            'transactionHash' => $txHash,
            'blockNumber' => $blockNumber,
            'blockHash' => '0x' . bin2hex(random_bytes(32)),
            'transactionIndex' => '0x0',
            'from' => '0x' . bin2hex(random_bytes(20)),
            'to' => '0x' . bin2hex(random_bytes(20)),
            'gasUsed' => '0x5208', // 21000 gas
            'cumulativeGasUsed' => '0x5208',
            'status' => '0x1',
            'logs' => []
        ];
    }
    
    /**
     * Format amounts for display
     */
    public function formatAmount(string $amount, int $decimals = 18): string
    {
        return rtrim(rtrim(number_format(floatval($amount), $decimals, '.', ''), '0'), '.');
    }
    
    /**
     * Convert Wei to ETH
     */
    public function weiToEth(string $wei): string
    {
        return bcdiv($wei, '1000000000000000000', 18);
    }
    
    /**
     * Convert ETH to Wei
     */
    public function ethToWei(string $eth): string
    {
        return bcmul($eth, '1000000000000000000', 0);
    }
    
    /**
     * Get token contract address
     */
    private function getTokenContract(string $token): string
    {
        $contracts = [
            'USDC' => $_ENV['SEPOLIA_USDC_CONTRACT'] ?? '',
            'USDT' => $_ENV['SEPOLIA_USDT_CONTRACT'] ?? '',
        ];
        
        if (!isset($contracts[$token])) {
            throw new InvalidArgumentException("Unknown token: {$token}");
        }
        
        return $contracts[$token];
    }
    
    /**
     * Validate Ethereum address format
     */
    public function isValidAddress(string $address): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    }
    
    /**
     * Validate transaction hash format
     */
    public function isValidTxHash(string $txHash): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash) === 1;
    }
    
    /**
     * Get funding recommendations for test wallets
     */
    public function getFundingRecommendations(array $wallets): array
    {
        $recommendations = [];
        
        foreach ($wallets as $index => $wallet) {
            $balance = '0';
            try {
                $balance = $this->client->getBalance($wallet->getAddress());
            } catch (\Exception $e) {
                // Ignore balance check errors
            }
            
            $balanceEth = $this->weiToEth($balance);
            $needsFunding = bccomp($balanceEth, '0.01', 18) < 0;
            
            $recommendations[] = [
                'index' => $index,
                'address' => $wallet->getAddress(),
                'current_balance' => $this->formatAmount($balanceEth) . ' ETH',
                'needs_funding' => $needsFunding,
                'recommended_amount' => $needsFunding ? '0.1 ETH' : 'Sufficient',
                'purpose' => $this->getWalletPurpose($index)
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get wallet purpose description
     */
    private function getWalletPurpose(int $index): string
    {
        return match($index) {
            0 => 'Project wallet (receives payments)',
            1 => 'Payment wallet (sends payments)',
            2 => 'Recipient wallet (receives CIRX)',
            default => 'Backup wallet'
        };
    }
    
    /**
     * Calculate transaction fees for budget planning
     */
    public function calculateTestingBudget(int $numberOfTransactions = 10): array
    {
        try {
            $gasPrice = $this->client->getGasPrice();
            $gasPriceGwei = bcdiv($gasPrice, '1000000000', 9);
            
            // Estimate costs for different transaction types
            $ethTransferGas = 21000;
            $tokenTransferGas = 65000;
            
            $ethTxCost = bcmul($gasPrice, (string)$ethTransferGas, 0);
            $tokenTxCost = bcmul($gasPrice, (string)$tokenTransferGas, 0);
            
            $ethTxCostEth = $this->weiToEth($ethTxCost);
            $tokenTxCostEth = $this->weiToEth($tokenTxCost);
            
            // Budget for mixed transaction types
            $totalEthTxs = intval($numberOfTransactions * 0.6); // 60% ETH
            $totalTokenTxs = $numberOfTransactions - $totalEthTxs;
            
            $totalCostWei = bcadd(
                bcmul($ethTxCost, (string)$totalEthTxs, 0),
                bcmul($tokenTxCost, (string)$totalTokenTxs, 0),
                0
            );
            
            $totalCostEth = $this->weiToEth($totalCostWei);
            $recommendedBudget = bcmul($totalCostEth, '2', 18); // 2x buffer
            
            return [
                'current_gas_price_gwei' => $this->formatAmount($gasPriceGwei, 2),
                'eth_transfer_cost' => $this->formatAmount($ethTxCostEth, 6) . ' ETH',
                'token_transfer_cost' => $this->formatAmount($tokenTxCostEth, 6) . ' ETH',
                'total_transactions' => $numberOfTransactions,
                'eth_transactions' => $totalEthTxs,
                'token_transactions' => $totalTokenTxs,
                'estimated_total_cost' => $this->formatAmount($totalCostEth, 6) . ' ETH',
                'recommended_budget' => $this->formatAmount($recommendedBudget, 6) . ' ETH',
                'note' => 'Recommended budget includes 2x safety buffer'
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to calculate budget: ' . $e->getMessage(),
                'recommended_budget' => '0.5 ETH (default estimate)'
            ];
        }
    }
}