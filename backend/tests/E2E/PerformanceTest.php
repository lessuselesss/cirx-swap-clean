<?php

namespace Tests\E2E;

use App\Utils\BlockchainTestUtils;

/**
 * Performance and Timing Validation Tests
 * 
 * Tests system performance under various load conditions,
 * measures timing requirements, and validates scalability.
 */
class PerformanceTest extends E2ETestCase
{
    private BlockchainTestUtils $blockchainUtils;
    
    // Performance thresholds (in seconds)
    private const MAX_API_RESPONSE_TIME = 2.0;
    private const MAX_PAYMENT_VERIFICATION_TIME = 30.0;
    private const MAX_CIRX_TRANSFER_TIME = 60.0;
    private const MAX_END_TO_END_TIME = 120.0;
    
    // Load testing parameters
    private const CONCURRENT_TRANSACTIONS = 10;
    private const STRESS_TEST_TRANSACTIONS = 20;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->blockchainUtils = new BlockchainTestUtils($this->sepoliaClient);
    }
    
    /**
     * Test API response time performance
     */
    public function testAPIResponseTimePerformance(): void
    {
        $this->logTestInfo("Testing API response time performance");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Send a payment
        $txHash = $this->sendSepoliaPayment(
            fromWallet: $paymentWallet,
            toAddress: $projectWallet,
            amount: '0.01',
            token: 'ETH'
        );
        
        $this->waitForTransactionConfirmation($txHash, 1);
        
        $swapRequest = [
            'txId' => $txHash,
            'paymentChain' => 'sepolia',
            'cirxRecipientAddress' => $recipientWallet->getAddress(),
            'amountPaid' => '0.01',
            'paymentToken' => 'ETH'
        ];
        
        // Measure API response time
        $startTime = microtime(true);
        
        $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
        $response = $this->runApp($request);
        
        $responseTime = microtime(true) - $startTime;
        
        $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
        $swapId = $responseData['swapId'];
        
        // Performance assertions
        $this->assertLessThan(self::MAX_API_RESPONSE_TIME, $responseTime,
            "API response time should be under " . self::MAX_API_RESPONSE_TIME . " seconds"
        );
        
        // Test status check response time
        $startTime = microtime(true);
        
        $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
        $statusResponse = $this->runApp($statusRequest);
        
        $statusResponseTime = microtime(true) - $startTime;
        
        $this->assertJsonResponse($statusResponse, 200);
        $this->assertLessThan(self::MAX_API_RESPONSE_TIME, $statusResponseTime,
            "Status check response time should be under " . self::MAX_API_RESPONSE_TIME . " seconds"
        );
        
        $this->logTestInfo("API response time performance validated", [
            'swap_initiation_time' => round($responseTime, 3) . 's',
            'status_check_time' => round($statusResponseTime, 3) . 's',
            'threshold' => self::MAX_API_RESPONSE_TIME . 's'
        ]);
    }
    
    /**
     * Test payment verification worker performance
     */
    public function testPaymentVerificationPerformance(): void
    {
        $this->logTestInfo("Testing payment verification worker performance");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Create multiple transactions to verify
        $swapIds = [];
        $txHashes = [];
        
        for ($i = 1; $i <= 5; $i++) {
            $amount = '0.00' . $i;
            
            $txHash = $this->sendSepoliaPayment(
                fromWallet: $paymentWallet,
                toAddress: $projectWallet,
                amount: $amount,
                token: 'ETH'
            );
            
            $txHashes[] = $txHash;
            $this->waitForTransactionConfirmation($txHash, 1);
            
            $swapRequest = [
                'txId' => $txHash,
                'paymentChain' => 'sepolia',
                'cirxRecipientAddress' => $recipientWallet->getAddress(),
                'amountPaid' => $amount,
                'paymentToken' => 'ETH'
            ];
            
            $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
            $response = $this->runApp($request);
            
            $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
            $swapIds[] = $responseData['swapId'];
        }
        
        // Measure payment verification time
        $startTime = microtime(true);
        $this->runPaymentVerificationWorker();
        $verificationTime = microtime(true) - $startTime;
        
        // Performance assertions
        $this->assertLessThan(self::MAX_PAYMENT_VERIFICATION_TIME, $verificationTime,
            "Payment verification should complete within " . self::MAX_PAYMENT_VERIFICATION_TIME . " seconds"
        );
        
        // Verify all transactions were processed
        $processedCount = 0;
        foreach ($swapIds as $swapId) {
            $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
            $statusResponse = $this->runApp($statusRequest);
            
            $statusData = $this->assertJsonResponse($statusResponse, 200, ['status']);
            
            if (in_array($statusData['status'], ['payment_verified', 'cirx_transfer_pending', 'cirx_transfer_initiated', 'completed'])) {
                $processedCount++;
            }
        }
        
        $processingRate = $processedCount / max($verificationTime, 0.001); // Avoid division by zero
        
        $this->logTestInfo("Payment verification performance validated", [
            'verification_time' => round($verificationTime, 3) . 's',
            'transactions_processed' => $processedCount,
            'total_transactions' => count($swapIds),
            'processing_rate' => round($processingRate, 2) . ' tx/s',
            'threshold' => self::MAX_PAYMENT_VERIFICATION_TIME . 's'
        ]);
        
        $this->assertGreaterThan(0, $processedCount, 'At least some transactions should be verified');
    }
    
    /**
     * Test CIRX transfer worker performance
     */
    public function testCirxTransferPerformance(): void
    {
        $this->logTestInfo("Testing CIRX transfer worker performance");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Create and verify transactions first
        $swapIds = [];
        
        for ($i = 1; $i <= 3; $i++) {
            $amount = '0.00' . $i;
            
            $txHash = $this->sendSepoliaPayment(
                fromWallet: $paymentWallet,
                toAddress: $projectWallet,
                amount: $amount,
                token: 'ETH'
            );
            
            $this->waitForTransactionConfirmation($txHash, 1);
            
            $swapRequest = [
                'txId' => $txHash,
                'paymentChain' => 'sepolia',
                'cirxRecipientAddress' => $recipientWallet->getAddress(),
                'amountPaid' => $amount,
                'paymentToken' => 'ETH'
            ];
            
            $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
            $response = $this->runApp($request);
            
            $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
            $swapIds[] = $responseData['swapId'];
        }
        
        // Run payment verification first
        $this->runPaymentVerificationWorker();
        
        // Measure CIRX transfer time
        $startTime = microtime(true);
        $this->runCirxTransferWorker();
        $transferTime = microtime(true) - $startTime;
        
        // Performance assertions
        $this->assertLessThan(self::MAX_CIRX_TRANSFER_TIME, $transferTime,
            "CIRX transfer should complete within " . self::MAX_CIRX_TRANSFER_TIME . " seconds"
        );
        
        // Check transfer progress
        $transferredCount = 0;
        foreach ($swapIds as $swapId) {
            $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
            $statusResponse = $this->runApp($statusRequest);
            
            $statusData = $this->assertJsonResponse($statusResponse, 200, ['status']);
            
            if (in_array($statusData['status'], ['cirx_transfer_initiated', 'completed'])) {
                $transferredCount++;
            }
        }
        
        $transferRate = $transferredCount / max($transferTime, 0.001);
        
        $this->logTestInfo("CIRX transfer performance validated", [
            'transfer_time' => round($transferTime, 3) . 's',
            'transactions_transferred' => $transferredCount,
            'total_transactions' => count($swapIds),
            'transfer_rate' => round($transferRate, 2) . ' tx/s',
            'threshold' => self::MAX_CIRX_TRANSFER_TIME . 's'
        ]);
    }
    
    /**
     * Test end-to-end transaction processing time
     */
    public function testEndToEndProcessingTime(): void
    {
        $this->logTestInfo("Testing end-to-end processing time");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Send payment
        $txHash = $this->sendSepoliaPayment(
            fromWallet: $paymentWallet,
            toAddress: $projectWallet,
            amount: '0.01',
            token: 'ETH'
        );
        
        $this->waitForTransactionConfirmation($txHash, 1);
        
        // Start end-to-end timing
        $startTime = microtime(true);
        
        // Initiate swap
        $swapRequest = [
            'txId' => $txHash,
            'paymentChain' => 'sepolia',
            'cirxRecipientAddress' => $recipientWallet->getAddress(),
            'amountPaid' => '0.01',
            'paymentToken' => 'ETH'
        ];
        
        $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
        $response = $this->runApp($request);
        
        $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
        $swapId = $responseData['swapId'];
        
        $swapInitiationTime = microtime(true) - $startTime;
        
        // Run processing workers
        $verificationStartTime = microtime(true);
        $this->runPaymentVerificationWorker();
        $verificationTime = microtime(true) - $verificationStartTime;
        
        $transferStartTime = microtime(true);
        $this->runCirxTransferWorker();
        $transferTime = microtime(true) - $transferStartTime;
        
        $totalEndToEndTime = microtime(true) - $startTime;
        
        // Performance assertions
        $this->assertLessThan(self::MAX_END_TO_END_TIME, $totalEndToEndTime,
            "End-to-end processing should complete within " . self::MAX_END_TO_END_TIME . " seconds"
        );
        
        // Verify final status
        $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
        $statusResponse = $this->runApp($statusRequest);
        
        $statusData = $this->assertJsonResponse($statusResponse, 200, ['status']);
        
        $this->logTestInfo("End-to-end processing time validated", [
            'swap_initiation_time' => round($swapInitiationTime, 3) . 's',
            'verification_time' => round($verificationTime, 3) . 's',
            'transfer_time' => round($transferTime, 3) . 's',
            'total_e2e_time' => round($totalEndToEndTime, 3) . 's',
            'final_status' => $statusData['status'],
            'threshold' => self::MAX_END_TO_END_TIME . 's'
        ]);
    }
    
    /**
     * Test concurrent transaction processing performance
     */
    public function testConcurrentProcessingPerformance(): void
    {
        $this->logTestInfo("Testing concurrent transaction processing performance");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        $concurrentCount = self::CONCURRENT_TRANSACTIONS;
        $swapIds = [];
        
        // Create multiple concurrent transactions
        $setupStartTime = microtime(true);
        
        for ($i = 1; $i <= $concurrentCount; $i++) {
            $amount = '0.00' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            
            $txHash = $this->sendSepoliaPayment(
                fromWallet: $paymentWallet,
                toAddress: $projectWallet,
                amount: $amount,
                token: 'ETH'
            );
            
            $this->waitForTransactionConfirmation($txHash, 1);
            
            $swapRequest = [
                'txId' => $txHash,
                'paymentChain' => 'sepolia',
                'cirxRecipientAddress' => $recipientWallet->getAddress(),
                'amountPaid' => $amount,
                'paymentToken' => 'ETH'
            ];
            
            $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
            $response = $this->runApp($request);
            
            $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
            $swapIds[] = $responseData['swapId'];
        }
        
        $setupTime = microtime(true) - $setupStartTime;
        
        // Process all transactions
        $processingStartTime = microtime(true);
        
        $this->runPaymentVerificationWorker();
        $this->runCirxTransferWorker();
        
        $processingTime = microtime(true) - $processingStartTime;
        $totalTime = microtime(true) - $setupStartTime;
        
        // Calculate performance metrics
        $throughput = count($swapIds) / max($processingTime, 0.001);
        $avgProcessingTime = $processingTime / count($swapIds);
        
        // Verify processing results
        $completedCount = 0;
        $verifiedCount = 0;
        
        foreach ($swapIds as $swapId) {
            $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
            $statusResponse = $this->runApp($statusRequest);
            
            $statusData = $this->assertJsonResponse($statusResponse, 200, ['status']);
            
            if (in_array($statusData['status'], ['completed', 'cirx_transfer_initiated'])) {
                $completedCount++;
            }
            
            if (in_array($statusData['status'], ['payment_verified', 'cirx_transfer_pending', 'cirx_transfer_initiated', 'completed'])) {
                $verifiedCount++;
            }
        }
        
        $completionRate = ($completedCount / count($swapIds)) * 100;
        $verificationRate = ($verifiedCount / count($swapIds)) * 100;
        
        // Performance assertions
        $this->assertGreaterThan(0.5, $throughput, 'Should process at least 0.5 transactions per second');
        $this->assertLessThan(60, $avgProcessingTime, 'Average processing time should be under 60 seconds');
        $this->assertGreaterThan(50, $verificationRate, 'At least 50% of transactions should be verified');
        
        $this->logTestInfo("Concurrent processing performance validated", [
            'concurrent_transactions' => $concurrentCount,
            'setup_time' => round($setupTime, 3) . 's',
            'processing_time' => round($processingTime, 3) . 's',
            'total_time' => round($totalTime, 3) . 's',
            'throughput' => round($throughput, 2) . ' tx/s',
            'avg_processing_time' => round($avgProcessingTime, 3) . 's',
            'verification_rate' => round($verificationRate, 1) . '%',
            'completion_rate' => round($completionRate, 1) . '%',
            'verified_count' => $verifiedCount,
            'completed_count' => $completedCount
        ]);
    }
    
    /**
     * Test system performance under stress conditions
     */
    public function testStressTestPerformance(): void
    {
        $this->logTestInfo("Testing system performance under stress conditions");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        $stressTestCount = self::STRESS_TEST_TRANSACTIONS;
        $swapIds = [];
        $timings = [];
        
        // Create stress test load
        $overallStartTime = microtime(true);
        
        for ($i = 1; $i <= $stressTestCount; $i++) {
            $iterationStartTime = microtime(true);
            
            $amount = '0.001'; // Use consistent small amount for stress test
            
            $txHash = $this->sendSepoliaPayment(
                fromWallet: $paymentWallet,
                toAddress: $projectWallet,
                amount: $amount,
                token: 'ETH'
            );
            
            $this->waitForTransactionConfirmation($txHash, 1);
            
            $swapRequest = [
                'txId' => $txHash,
                'paymentChain' => 'sepolia',
                'cirxRecipientAddress' => $recipientWallet->getAddress(),
                'amountPaid' => $amount,
                'paymentToken' => 'ETH'
            ];
            
            $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
            $response = $this->runApp($request);
            
            $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
            $swapIds[] = $responseData['swapId'];
            
            $iterationTime = microtime(true) - $iterationStartTime;
            $timings[] = $iterationTime;
            
            // Log progress every 5 transactions
            if ($i % 5 === 0) {
                $this->logTestInfo("Stress test progress", [
                    'completed' => $i,
                    'total' => $stressTestCount,
                    'avg_iteration_time' => round(array_sum($timings) / count($timings), 3) . 's'
                ]);
            }
        }
        
        $setupTime = microtime(true) - $overallStartTime;
        
        // Process stress test transactions in batches
        $batchSize = 5;
        $batches = array_chunk($swapIds, $batchSize);
        $batchTimings = [];
        
        foreach ($batches as $batchIndex => $batch) {
            $batchStartTime = microtime(true);
            
            $this->runPaymentVerificationWorker();
            $this->runCirxTransferWorker();
            
            $batchTime = microtime(true) - $batchStartTime;
            $batchTimings[] = $batchTime;
            
            $this->logTestInfo("Stress test batch processed", [
                'batch' => $batchIndex + 1,
                'total_batches' => count($batches),
                'batch_size' => count($batch),
                'batch_time' => round($batchTime, 3) . 's'
            ]);
        }
        
        $totalProcessingTime = array_sum($batchTimings);
        $overallTime = microtime(true) - $overallStartTime;
        
        // Calculate stress test metrics
        $avgIterationTime = array_sum($timings) / count($timings);
        $maxIterationTime = max($timings);
        $minIterationTime = min($timings);
        
        $overallThroughput = count($swapIds) / max($overallTime, 0.001);
        $processingThroughput = count($swapIds) / max($totalProcessingTime, 0.001);
        
        // Verify stress test results
        $processedCount = 0;
        foreach ($swapIds as $swapId) {
            $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
            $statusResponse = $this->runApp($statusRequest);
            
            $statusData = $this->assertJsonResponse($statusResponse, 200, ['status']);
            
            if (!in_array($statusData['status'], ['pending_payment_verification'])) {
                $processedCount++;
            }
        }
        
        $processingSuccessRate = ($processedCount / count($swapIds)) * 100;
        
        // Performance assertions for stress test
        $this->assertGreaterThan(50, $processingSuccessRate, 'At least 50% of stress test transactions should be processed');
        $this->assertLessThan(10, $avgIterationTime, 'Average iteration time should be under 10 seconds');
        
        $this->logTestInfo("Stress test performance validated", [
            'stress_test_transactions' => $stressTestCount,
            'setup_time' => round($setupTime, 3) . 's',
            'processing_time' => round($totalProcessingTime, 3) . 's',
            'overall_time' => round($overallTime, 3) . 's',
            'avg_iteration_time' => round($avgIterationTime, 3) . 's',
            'min_iteration_time' => round($minIterationTime, 3) . 's',
            'max_iteration_time' => round($maxIterationTime, 3) . 's',
            'overall_throughput' => round($overallThroughput, 2) . ' tx/s',
            'processing_throughput' => round($processingThroughput, 2) . ' tx/s',
            'processing_success_rate' => round($processingSuccessRate, 1) . '%',
            'processed_count' => $processedCount
        ]);
    }
    
    /**
     * Test memory usage and resource consumption
     */
    public function testResourceConsumption(): void
    {
        $this->logTestInfo("Testing resource consumption");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Measure initial memory usage
        $initialMemory = memory_get_usage(true);
        $initialPeakMemory = memory_get_peak_usage(true);
        
        $swapIds = [];
        
        // Create moderate load to measure resource usage
        for ($i = 1; $i <= 5; $i++) {
            $amount = '0.00' . $i;
            
            $txHash = $this->sendSepoliaPayment(
                fromWallet: $paymentWallet,
                toAddress: $projectWallet,
                amount: $amount,
                token: 'ETH'
            );
            
            $this->waitForTransactionConfirmation($txHash, 1);
            
            $swapRequest = [
                'txId' => $txHash,
                'paymentChain' => 'sepolia',
                'cirxRecipientAddress' => $recipientWallet->getAddress(),
                'amountPaid' => $amount,
                'paymentToken' => 'ETH'
            ];
            
            $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
            $response = $this->runApp($request);
            
            $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
            $swapIds[] = $responseData['swapId'];
            
            // Measure memory usage after each transaction
            $currentMemory = memory_get_usage(true);
            $memoryIncrease = $currentMemory - $initialMemory;
            
            $this->logTestInfo("Memory usage tracking", [
                'transaction' => $i,
                'current_memory' => round($currentMemory / 1024 / 1024, 2) . ' MB',
                'memory_increase' => round($memoryIncrease / 1024 / 1024, 2) . ' MB'
            ]);
        }
        
        // Run workers and measure resource usage
        $preWorkerMemory = memory_get_usage(true);
        
        $this->runPaymentVerificationWorker();
        $this->runCirxTransferWorker();
        
        $postWorkerMemory = memory_get_usage(true);
        $finalPeakMemory = memory_get_peak_usage(true);
        
        // Calculate resource usage metrics
        $totalMemoryIncrease = $postWorkerMemory - $initialMemory;
        $workerMemoryIncrease = $postWorkerMemory - $preWorkerMemory;
        $peakMemoryIncrease = $finalPeakMemory - $initialPeakMemory;
        
        // Convert to MB for easier reading
        $totalMemoryMB = round($totalMemoryIncrease / 1024 / 1024, 2);
        $workerMemoryMB = round($workerMemoryIncrease / 1024 / 1024, 2);
        $peakMemoryMB = round($peakMemoryIncrease / 1024 / 1024, 2);
        
        // Resource consumption assertions
        $this->assertLessThan(50, $totalMemoryMB, 'Total memory increase should be under 50MB');
        $this->assertLessThan(100, $peakMemoryMB, 'Peak memory increase should be under 100MB');
        
        $this->logTestInfo("Resource consumption validated", [
            'transactions_processed' => count($swapIds),
            'initial_memory' => round($initialMemory / 1024 / 1024, 2) . ' MB',
            'final_memory' => round($postWorkerMemory / 1024 / 1024, 2) . ' MB',
            'total_memory_increase' => $totalMemoryMB . ' MB',
            'worker_memory_increase' => $workerMemoryMB . ' MB',
            'peak_memory_increase' => $peakMemoryMB . ' MB',
            'memory_per_transaction' => round($totalMemoryMB / count($swapIds), 3) . ' MB/tx'
        ]);
    }
}