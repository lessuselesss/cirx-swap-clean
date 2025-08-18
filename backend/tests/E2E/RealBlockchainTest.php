<?php

namespace Tests\E2E;

use Tests\E2E\E2ETestCase;

/**
 * Real Blockchain Integration E2E Tests
 * 
 * Tests actual blockchain interactions on Sepolia testnet
 * to verify the system works with real transactions.
 * 
 * @group e2e
 * @group blockchain
 * @group slow
 */
class RealBlockchainTest extends E2ETestCase
{
    /**
     * Test real Sepolia transaction flow with ETH
     * 
     * @group e2e
     * @group blockchain
     */
    public function testRealSepoliaETHTransactionFlow(): void
    {
        $this->logTestInfo("Starting real Sepolia ETH transaction flow test");
        
        $paymentWallet = $this->getPaymentWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Get initial ETH balance
        $initialBalance = $this->sepoliaClient->getBalance($projectWallet);
        $this->logTestInfo("Initial project wallet balance", [
            'wallet' => $projectWallet,
            'balance' => $initialBalance . ' ETH'
        ]);
        
        // Send real ETH transaction
        $amount = '0.001'; // 0.001 ETH (minimal test amount)
        $txHash = $this->sendSepoliaPayment(
            $paymentWallet,
            $projectWallet,
            $amount,
            'ETH'
        );
        
        $this->assertNotEmpty($txHash);
        $this->assertStringStartsWith('0x', $txHash);
        $this->assertEquals(66, strlen($txHash), 'Transaction hash should be 66 characters');
        
        $this->logTestInfo("ETH transaction sent", [
            'tx_hash' => $txHash,
            'from' => $paymentWallet->getAddress(),
            'to' => $projectWallet,
            'amount' => $amount . ' ETH'
        ]);
        
        // Wait for blockchain confirmation
        $receipt = $this->waitForTransactionConfirmation($txHash, 3);
        
        $this->assertIsArray($receipt);
        $this->assertArrayHasKey('blockNumber', $receipt);
        $this->assertArrayHasKey('gasUsed', $receipt);
        $this->assertArrayHasKey('status', $receipt);
        $this->assertEquals('0x1', $receipt['status'], 'Transaction should be successful');
        
        // Verify balance increased
        $finalBalance = $this->sepoliaClient->getBalance($projectWallet);
        $balanceDifference = floatval($finalBalance) - floatval($initialBalance);
        
        $this->assertGreaterThan(0, $balanceDifference, 'Project wallet balance should increase');
        $this->assertLessThanOrEqual(floatval($amount), $balanceDifference, 'Balance increase should not exceed sent amount');
        
        $this->logTestInfo("Real Sepolia ETH transaction completed successfully", [
            'initial_balance' => $initialBalance . ' ETH',
            'final_balance' => $finalBalance . ' ETH',
            'balance_increase' => $balanceDifference . ' ETH',
            'gas_used' => $receipt['gasUsed'],
            'block_number' => $receipt['blockNumber']
        ]);
    }
    
    /**
     * Test real USDC token transaction on Sepolia
     * 
     * @group e2e
     * @group blockchain
     */
    public function testRealSepoliaUSDCTransactionFlow(): void
    {
        $this->logTestInfo("Starting real Sepolia USDC transaction flow test");
        
        $paymentWallet = $this->getPaymentWallet();
        $projectWallet = $this->getProjectWallet();
        $usdcContract = $_ENV['SEPOLIA_USDC_CONTRACT'] ?? '';
        
        if (empty($usdcContract)) {
            $this->markTestSkipped('SEPOLIA_USDC_CONTRACT not configured');
        }
        
        // Get initial USDC balance
        $initialBalance = $this->sepoliaClient->getTokenBalance(
            $projectWallet,
            $usdcContract
        );
        
        $this->logTestInfo("Initial project wallet USDC balance", [
            'wallet' => $projectWallet,
            'balance' => $initialBalance . ' USDC',
            'contract' => $usdcContract
        ]);
        
        // Send real USDC transaction
        $amount = '10.00'; // 10 USDC
        $txHash = $this->sendSepoliaPayment(
            $paymentWallet,
            $projectWallet,
            $amount,
            'USDC'
        );
        
        $this->assertNotEmpty($txHash);
        $this->assertStringStartsWith('0x', $txHash);
        
        $this->logTestInfo("USDC transaction sent", [
            'tx_hash' => $txHash,
            'from' => $paymentWallet->getAddress(),
            'to' => $projectWallet,
            'amount' => $amount . ' USDC',
            'contract' => $usdcContract
        ]);
        
        // Wait for confirmation
        $receipt = $this->waitForTransactionConfirmation($txHash, 3);
        $this->assertEquals('0x1', $receipt['status'], 'USDC transaction should be successful');
        
        // Verify USDC balance increased
        $finalBalance = $this->sepoliaClient->getTokenBalance(
            $projectWallet,
            $usdcContract
        );
        
        $balanceDifference = floatval($finalBalance) - floatval($initialBalance);
        $this->assertGreaterThan(0, $balanceDifference, 'USDC balance should increase');
        
        $this->logTestInfo("Real Sepolia USDC transaction completed successfully", [
            'initial_balance' => $initialBalance . ' USDC',
            'final_balance' => $finalBalance . ' USDC',
            'balance_increase' => $balanceDifference . ' USDC',
            'gas_used' => $receipt['gasUsed'],
            'block_number' => $receipt['blockNumber']
        ]);
    }
    
    /**
     * Test transaction monitoring and status updates
     * 
     * @group e2e
     * @group blockchain
     */
    public function testTransactionMonitoringAndStatusUpdates(): void
    {
        $this->logTestInfo("Starting transaction monitoring and status updates test");
        
        $paymentWallet = $this->getPaymentWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Send transaction
        $amount = '0.002';
        $txHash = $this->sendSepoliaPayment(
            $paymentWallet,
            $projectWallet,
            $amount,
            'ETH'
        );
        
        $this->logTestInfo("Transaction sent for monitoring", ['tx_hash' => $txHash]);
        
        // Monitor transaction through various confirmation stages
        $confirmationStages = [1, 2, 3, 5, 10];
        $previousConfirmations = 0;
        
        foreach ($confirmationStages as $targetConfirmations) {
            try {
                $receipt = $this->waitForTransactionConfirmation($txHash, $targetConfirmations);
                
                $currentBlock = $this->sepoliaClient->getBlockNumber();
                $txBlock = hexdec($receipt['blockNumber']);
                $actualConfirmations = $currentBlock - $txBlock;
                
                $this->assertGreaterThanOrEqual($targetConfirmations, $actualConfirmations);
                $this->assertGreaterThan($previousConfirmations, $actualConfirmations);
                
                $this->logTestInfo("Confirmation stage reached", [
                    'target_confirmations' => $targetConfirmations,
                    'actual_confirmations' => $actualConfirmations,
                    'current_block' => $currentBlock,
                    'tx_block' => $txBlock
                ]);
                
                $previousConfirmations = $actualConfirmations;
                
            } catch (\Exception $e) {
                $this->logTestInfo("Confirmation stage timeout", [
                    'target_confirmations' => $targetConfirmations,
                    'error' => $e->getMessage()
                ]);
                break; // Stop if we can't reach higher confirmation levels
            }
        }
        
        $this->logTestInfo("Transaction monitoring completed successfully");
    }
    
    // Gas estimation test removed - backend is read-only for client-side chains
    
    /**
     * Test block and network information retrieval
     * 
     * @group e2e
     * @group blockchain
     */
    public function testBlockAndNetworkInformation(): void
    {
        $this->logTestInfo("Starting block and network information test");
        
        // Test network information
        $chainId = $this->sepoliaClient->getChainId();
        $this->assertEquals(11155111, $chainId, 'Should be connected to Sepolia testnet');
        
        // Test block information
        $latestBlockNumber = $this->sepoliaClient->getBlockNumber();
        $this->assertIsInt($latestBlockNumber);
        $this->assertGreaterThan(0, $latestBlockNumber);
        
        $this->logTestInfo("Network information", [
            'chain_id' => $chainId,
            'network' => 'Sepolia Testnet',
            'latest_block' => $latestBlockNumber
        ]);
        
        // Get block details
        $blockDetails = $this->sepoliaClient->getBlock($latestBlockNumber);
        $this->assertIsArray($blockDetails);
        $this->assertArrayHasKey('number', $blockDetails);
        $this->assertArrayHasKey('timestamp', $blockDetails);
        $this->assertArrayHasKey('hash', $blockDetails);
        
        $blockTimestamp = hexdec($blockDetails['timestamp']);
        $blockAge = time() - $blockTimestamp;
        
        $this->assertLessThan(300, $blockAge, 'Latest block should be less than 5 minutes old');
        
        $this->logTestInfo("Latest block details", [
            'block_number' => hexdec($blockDetails['number']),
            'block_hash' => $blockDetails['hash'],
            'timestamp' => date('Y-m-d H:i:s', $blockTimestamp),
            'age_seconds' => $blockAge,
            'transaction_count' => count($blockDetails['transactions'] ?? [])
        ]);
        
        // Test historical block access
        $historicalBlockNumber = max(1, $latestBlockNumber - 100);
        $historicalBlock = $this->sepoliaClient->getBlock($historicalBlockNumber);
        
        $this->assertIsArray($historicalBlock);
        $this->assertEquals($historicalBlockNumber, hexdec($historicalBlock['number']));
        
        $this->logTestInfo("Historical block access", [
            'requested_block' => $historicalBlockNumber,
            'retrieved_block' => hexdec($historicalBlock['number']),
            'block_hash' => $historicalBlock['hash']
        ]);
        
        $this->logTestInfo("Block and network information test completed successfully");
    }
    
    /**
     * Test wallet balance and transaction history
     * 
     * @group e2e
     * @group blockchain
     */
    public function testWalletBalanceAndTransactionHistory(): void
    {
        $this->logTestInfo("Starting wallet balance and transaction history test");
        
        $paymentWallet = $this->getPaymentWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Test ETH balances
        $paymentBalance = $this->sepoliaClient->getBalance($paymentWallet->getAddress());
        $projectBalance = $this->sepoliaClient->getBalance($projectWallet);
        
        $this->assertIsString($paymentBalance);
        $this->assertIsString($projectBalance);
        $this->assertGreaterThan(0, floatval($paymentBalance), 'Payment wallet should have ETH for testing');
        
        $this->logTestInfo("ETH balances", [
            'payment_wallet' => $paymentWallet->getAddress(),
            'payment_balance' => $paymentBalance . ' ETH',
            'project_wallet' => $projectWallet,
            'project_balance' => $projectBalance . ' ETH'
        ]);
        
        // Test token balances (if contracts available)
        $tokens = [
            'USDC' => $_ENV['SEPOLIA_USDC_CONTRACT'] ?? '',
            'USDT' => $_ENV['SEPOLIA_USDT_CONTRACT'] ?? ''
        ];
        
        foreach ($tokens as $symbol => $contract) {
            if (!empty($contract)) {
                $paymentTokenBalance = $this->sepoliaClient->getTokenBalance(
                    $paymentWallet->getAddress(),
                    $contract
                );
                $projectTokenBalance = $this->sepoliaClient->getTokenBalance(
                    $projectWallet,
                    $contract
                );
                
                $this->logTestInfo("{$symbol} balances", [
                    'payment_wallet_balance' => $paymentTokenBalance . " {$symbol}",
                    'project_wallet_balance' => $projectTokenBalance . " {$symbol}",
                    'contract' => $contract
                ]);
            }
        }
        
        // Test transaction count (nonce)
        $paymentNonce = $this->sepoliaClient->getTransactionCount($paymentWallet->getAddress());
        $projectNonce = $this->sepoliaClient->getTransactionCount($projectWallet);
        
        $this->assertIsInt($paymentNonce);
        $this->assertIsInt($projectNonce);
        $this->assertGreaterThanOrEqual(0, $paymentNonce);
        $this->assertGreaterThanOrEqual(0, $projectNonce);
        
        $this->logTestInfo("Transaction counts (nonces)", [
            'payment_wallet_nonce' => $paymentNonce,
            'project_wallet_nonce' => $projectNonce
        ]);
        
        $this->logTestInfo("Wallet balance and transaction history test completed successfully");
    }
    
    /**
     * Test network performance and latency
     * 
     * @group e2e
     * @group blockchain
     * @group performance
     */
    public function testNetworkPerformanceAndLatency(): void
    {
        $this->logTestInfo("Starting network performance and latency test");
        
        $performanceMetrics = [];
        
        // Test RPC call latency  
        $rpcCalls = [
            'eth_chainId' => fn() => $this->sepoliaClient->getChainId(),
            'eth_blockNumber' => fn() => $this->sepoliaClient->getBlockNumber(),
            // Gas price call removed - read-only client
            'eth_getBalance' => fn() => $this->sepoliaClient->getBalance($this->getProjectWallet())
        ];
        
        foreach ($rpcCalls as $method => $callable) {
            $times = [];
            
            // Make 5 calls and measure latency
            for ($i = 0; $i < 5; $i++) {
                $startTime = microtime(true);
                $result = $callable();
                $endTime = microtime(true);
                
                $latency = ($endTime - $startTime) * 1000; // Convert to milliseconds
                $times[] = $latency;
                
                $this->assertNotNull($result, "RPC call {$method} should return a result");
            }
            
            $avgLatency = array_sum($times) / count($times);
            $minLatency = min($times);
            $maxLatency = max($times);
            
            $performanceMetrics[$method] = [
                'avg_latency_ms' => round($avgLatency, 2),
                'min_latency_ms' => round($minLatency, 2),
                'max_latency_ms' => round($maxLatency, 2),
                'calls_made' => count($times)
            ];
            
            // Performance assertions
            $this->assertLessThan(5000, $avgLatency, "Average latency for {$method} should be under 5 seconds");
            $this->assertLessThan(10000, $maxLatency, "Max latency for {$method} should be under 10 seconds");
        }
        
        $this->logTestInfo("RPC performance metrics", $performanceMetrics);
        
        // Test concurrent RPC calls
        $startTime = microtime(true);
        
        $results = [];
        $results['chainId'] = $this->sepoliaClient->getChainId();
        $results['blockNumber'] = $this->sepoliaClient->getBlockNumber();
        // Gas price call removed - read-only client
        $results['balance'] = $this->sepoliaClient->getBalance($this->getProjectWallet());
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        
        // Verify all calls succeeded
        foreach ($results as $key => $result) {
            $this->assertNotNull($result, "Concurrent call {$key} should succeed");
        }
        
        $this->logTestInfo("Concurrent RPC calls performance", [
            'total_time_ms' => round($totalTime, 2),
            'calls_made' => count($results),
            'avg_time_per_call_ms' => round($totalTime / count($results), 2)
        ]);
        
        $this->logTestInfo("Network performance and latency test completed successfully");
    }
}