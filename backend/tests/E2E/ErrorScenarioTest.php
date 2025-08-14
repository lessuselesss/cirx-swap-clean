<?php

namespace Tests\E2E;

use App\Utils\BlockchainTestUtils;

/**
 * Error Scenario and Edge Case E2E Tests
 * 
 * Tests various failure modes, edge cases, and error handling
 * in the OTC swap flow to ensure robustness and proper error handling.
 */
class ErrorScenarioTest extends E2ETestCase
{
    private BlockchainTestUtils $blockchainUtils;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->blockchainUtils = new BlockchainTestUtils($this->sepoliaClient);
    }
    
    /**
     * Test handling of completely invalid transaction hash
     */
    public function testInvalidTransactionHashFormat(): void
    {
        $this->logTestInfo("Testing invalid transaction hash format");
        
        $recipientWallet = $this->getRecipientWallet();
        
        $invalidHashes = [
            'not-a-hash',
            '0x123', // Too short
            '0x' . str_repeat('z', 64), // Invalid hex characters
            '', // Empty
            null // Null value (will be converted to empty string)
        ];
        
        foreach ($invalidHashes as $index => $invalidHash) {
            $this->logTestInfo("Testing invalid hash #{$index}", ['hash' => $invalidHash]);
            
            $swapRequest = [
                'txId' => $invalidHash,
                'paymentChain' => 'sepolia',
                'cirxRecipientAddress' => $recipientWallet->getAddress(),
                'amountPaid' => '1.0',
                'paymentToken' => 'ETH'
            ];
            
            $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
            $response = $this->runApp($request);
            
            // Should reject invalid transaction hash format
            $this->assertContains($response->getStatusCode(), [400, 422], 
                "Should reject invalid transaction hash format: " . $invalidHash
            );
        }
    }
    
    /**
     * Test handling of invalid wallet addresses
     */
    public function testInvalidWalletAddresses(): void
    {
        $this->logTestInfo("Testing invalid wallet addresses");
        
        $paymentWallet = $this->getPaymentWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Send a valid payment first
        $txHash = $this->sendSepoliaPayment(
            fromWallet: $paymentWallet,
            toAddress: $projectWallet,
            amount: '0.01',
            token: 'ETH'
        );
        
        $this->waitForTransactionConfirmation($txHash, 1);
        
        $invalidAddresses = [
            'not-an-address',
            '0x123', // Too short
            '0x' . str_repeat('z', 40), // Invalid hex
            '', // Empty
            '0x' . str_repeat('0', 39), // One character short
            '0x' . str_repeat('0', 41), // One character too long
        ];
        
        foreach ($invalidAddresses as $index => $invalidAddress) {
            $this->logTestInfo("Testing invalid address #{$index}", ['address' => $invalidAddress]);
            
            $swapRequest = [
                'txId' => $txHash,
                'paymentChain' => 'sepolia',
                'cirxRecipientAddress' => $invalidAddress,
                'amountPaid' => '0.01',
                'paymentToken' => 'ETH'
            ];
            
            $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
            $response = $this->runApp($request);
            
            // Should reject invalid wallet address format
            $this->assertContains($response->getStatusCode(), [400, 422],
                "Should reject invalid wallet address: " . $invalidAddress
            );
        }
    }
    
    /**
     * Test handling of negative and zero payment amounts
     */
    public function testInvalidPaymentAmounts(): void
    {
        $this->logTestInfo("Testing invalid payment amounts");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        $invalidAmounts = [
            '-1.0',    // Negative amount
            '0',       // Zero amount
            '0.0',     // Zero decimal amount
            '',        // Empty string
            'not-a-number', // Invalid number format
            '1e50',    // Extremely large number
            '0.000000000000000001', // Extremely small amount
        ];
        
        foreach ($invalidAmounts as $index => $invalidAmount) {
            $this->logTestInfo("Testing invalid amount #{$index}", ['amount' => $invalidAmount]);
            
            // Generate a valid transaction hash for each test
            $txHash = $this->blockchainUtils->generateTestTxHash();
            
            $swapRequest = [
                'txId' => $txHash,
                'paymentChain' => 'sepolia',
                'cirxRecipientAddress' => $recipientWallet->getAddress(),
                'amountPaid' => $invalidAmount,
                'paymentToken' => 'ETH'
            ];
            
            $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
            $response = $this->runApp($request);
            
            // Should reject invalid payment amounts
            $this->assertContains($response->getStatusCode(), [400, 422],
                "Should reject invalid payment amount: " . $invalidAmount
            );
        }
    }
    
    /**
     * Test handling of unsupported tokens
     */
    public function testUnsupportedTokens(): void
    {
        $this->logTestInfo("Testing unsupported token types");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Send a valid payment
        $txHash = $this->sendSepoliaPayment(
            fromWallet: $paymentWallet,
            toAddress: $projectWallet,
            amount: '1.0',
            token: 'ETH'
        );
        
        $this->waitForTransactionConfirmation($txHash, 1);
        
        $unsupportedTokens = [
            'BTC',     // Bitcoin
            'DOGE',    // Dogecoin
            'INVALID', // Completely invalid token
            'eth',     // Wrong case
            'usdc',    // Wrong case
            '',        // Empty token
            'WETH',    // Wrapped ETH (might not be supported)
            'DAI',     // DAI (might not be supported)
        ];
        
        foreach ($unsupportedTokens as $index => $unsupportedToken) {
            $this->logTestInfo("Testing unsupported token #{$index}", ['token' => $unsupportedToken]);
            
            $swapRequest = [
                'txId' => $txHash,
                'paymentChain' => 'sepolia',
                'cirxRecipientAddress' => $recipientWallet->getAddress(),
                'amountPaid' => '1.0',
                'paymentToken' => $unsupportedToken
            ];
            
            $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
            $response = $this->runApp($request);
            
            // Should reject unsupported tokens
            $this->assertContains($response->getStatusCode(), [400, 422],
                "Should reject unsupported token: " . $unsupportedToken
            );
        }
    }
    
    /**
     * Test handling of unsupported blockchain networks
     */
    public function testUnsupportedChains(): void
    {
        $this->logTestInfo("Testing unsupported blockchain networks");
        
        $recipientWallet = $this->getRecipientWallet();
        $txHash = $this->blockchainUtils->generateTestTxHash();
        
        $unsupportedChains = [
            'bitcoin',
            'binance',
            'polygon',
            'avalanche',
            'mainnet', // Might not be supported for OTC
            'ethereum', // Wrong name format
            '',
            'SEPOLIA', // Wrong case
        ];
        
        foreach ($unsupportedChains as $index => $unsupportedChain) {
            $this->logTestInfo("Testing unsupported chain #{$index}", ['chain' => $unsupportedChain]);
            
            $swapRequest = [
                'txId' => $txHash,
                'paymentChain' => $unsupportedChain,
                'cirxRecipientAddress' => $recipientWallet->getAddress(),
                'amountPaid' => '1.0',
                'paymentToken' => 'ETH'
            ];
            
            $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
            $response = $this->runApp($request);
            
            // Should reject unsupported chains
            $this->assertContains($response->getStatusCode(), [400, 422],
                "Should reject unsupported chain: " . $unsupportedChain
            );
        }
    }
    
    /**
     * Test duplicate transaction processing
     */
    public function testDuplicateTransactionProcessing(): void
    {
        $this->logTestInfo("Testing duplicate transaction processing");
        
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
        
        // First request should succeed
        $request1 = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
        $response1 = $this->runApp($request1);
        
        $responseData1 = $this->assertJsonResponse($response1, 202, ['swapId']);
        $swapId1 = $responseData1['swapId'];
        
        // Second request with same transaction hash should fail or return same swap ID
        $request2 = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
        $response2 = $this->runApp($request2);
        
        // Should either reject duplicate or return existing swap ID
        $this->assertContains($response2->getStatusCode(), [200, 202, 409],
            "Should handle duplicate transaction appropriately"
        );
        
        if ($response2->getStatusCode() === 202) {
            $responseData2 = json_decode((string)$response2->getBody(), true);
            $this->assertEquals($swapId1, $responseData2['swapId'], 
                "Duplicate request should return same swap ID"
            );
        }
        
        $this->logTestInfo("Duplicate transaction handling verified", [
            'original_swap_id' => $swapId1,
            'tx_hash' => $txHash
        ]);
    }
    
    /**
     * Test handling of failed blockchain RPC calls
     */
    public function testBlockchainRPCFailures(): void
    {
        $this->logTestInfo("Testing blockchain RPC failure handling");
        
        $recipientWallet = $this->getRecipientWallet();
        
        // Use a transaction hash that will cause RPC lookup failures
        $problematicTxHash = '0x' . str_repeat('f', 64); // Valid format but non-existent
        
        $swapRequest = [
            'txId' => $problematicTxHash,
            'paymentChain' => 'sepolia',
            'cirxRecipientAddress' => $recipientWallet->getAddress(),
            'amountPaid' => '1.0',
            'paymentToken' => 'ETH'
        ];
        
        $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
        $response = $this->runApp($request);
        
        // Should accept the request (verification happens in worker)
        $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
        $swapId = $responseData['swapId'];
        
        // Run payment verification worker - should handle RPC failures gracefully
        $this->runPaymentVerificationWorker();
        
        // Check final status - should be failed verification
        $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
        $statusResponse = $this->runApp($statusRequest);
        
        $statusData = $this->assertJsonResponse($statusResponse, 200, ['status']);
        
        $this->assertContains($statusData['status'], [
            'failed_payment_verification',
            'pending_payment_verification' // If verification is still retrying
        ]);
        
        $this->logTestInfo("RPC failure handling verified", [
            'swap_id' => $swapId,
            'final_status' => $statusData['status']
        ]);
    }
    
    /**
     * Test worker timeout and retry scenarios
     */
    public function testWorkerTimeoutAndRetry(): void
    {
        $this->logTestInfo("Testing worker timeout and retry scenarios");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Send multiple payments to create workload
        $swapIds = [];
        
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
        }
        
        // Run workers multiple times to test retry logic
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->logTestInfo("Worker attempt #{$attempt}");
            
            $startTime = microtime(true);
            $this->runPaymentVerificationWorker();
            $verificationTime = microtime(true) - $startTime;
            
            $startTime = microtime(true);
            $this->runCirxTransferWorker();
            $transferTime = microtime(true) - $startTime;
            
            $this->logTestInfo("Worker timing", [
                'attempt' => $attempt,
                'verification_time' => round($verificationTime, 2) . 's',
                'transfer_time' => round($transferTime, 2) . 's'
            ]);
        }
        
        // Check final status of all swaps
        $completedCount = 0;
        foreach ($swapIds as $swapId) {
            $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
            $statusResponse = $this->runApp($statusRequest);
            
            $statusData = $this->assertJsonResponse($statusResponse, 200, ['status']);
            
            if (in_array($statusData['status'], ['completed', 'cirx_transfer_initiated'])) {
                $completedCount++;
            }
        }
        
        $this->assertGreaterThan(0, $completedCount, 'At least some transactions should complete');
        
        $this->logTestInfo("Worker timeout and retry test completed", [
            'total_swaps' => count($swapIds),
            'completed_swaps' => $completedCount,
            'completion_rate' => round(($completedCount / count($swapIds)) * 100, 1) . '%'
        ]);
    }
    
    /**
     * Test edge case payment amounts (very small, very large)
     */
    public function testEdgeCasePaymentAmounts(): void
    {
        $this->logTestInfo("Testing edge case payment amounts");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        $edgeCaseAmounts = [
            ['amount' => '0.000000000000000001', 'token' => 'ETH', 'description' => '1 wei'],
            ['amount' => '0.000000001', 'token' => 'ETH', 'description' => '1 gwei'],
            ['amount' => '0.001', 'token' => 'ETH', 'description' => 'minimal practical ETH'],
            ['amount' => '1000000.0', 'token' => 'USDC', 'description' => 'very large USDC amount'],
            ['amount' => '0.000001', 'token' => 'USDC', 'description' => 'micro USDC amount'],
            ['amount' => '999999999.999999', 'token' => 'USDT', 'description' => 'maximum precision USDT'],
        ];
        
        foreach ($edgeCaseAmounts as $index => $testCase) {
            $this->logTestInfo("Testing edge case #{$index}: {$testCase['description']}", [
                'amount' => $testCase['amount'],
                'token' => $testCase['token']
            ]);
            
            // Skip very small ETH amounts that would be impractical to test
            if ($testCase['token'] === 'ETH' && floatval($testCase['amount']) < 0.001) {
                $this->logTestInfo("Skipping impractical small ETH amount");
                continue;
            }
            
            try {
                $txHash = $this->sendSepoliaPayment(
                    fromWallet: $paymentWallet,
                    toAddress: $projectWallet,
                    amount: $testCase['amount'],
                    token: $testCase['token']
                );
                
                $this->waitForTransactionConfirmation($txHash, 1);
                
                $swapRequest = [
                    'txId' => $txHash,
                    'paymentChain' => 'sepolia',
                    'cirxRecipientAddress' => $recipientWallet->getAddress(),
                    'amountPaid' => $testCase['amount'],
                    'paymentToken' => $testCase['token']
                ];
                
                $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
                $response = $this->runApp($request);
                
                // Should handle edge case amounts appropriately
                $this->assertContains($response->getStatusCode(), [202, 400, 422],
                    "Should handle edge case amount appropriately"
                );
                
                if ($response->getStatusCode() === 202) {
                    $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
                    $swapId = $responseData['swapId'];
                    
                    // Try to process the swap
                    $this->runPaymentVerificationWorker();
                    $this->runCirxTransferWorker();
                    
                    $this->logTestInfo("Edge case amount processed", [
                        'description' => $testCase['description'],
                        'swap_id' => $swapId,
                        'amount' => $testCase['amount'] . ' ' . $testCase['token']
                    ]);
                }
                
            } catch (\Exception $e) {
                $this->logTestInfo("Edge case amount failed as expected", [
                    'description' => $testCase['description'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Test concurrent worker execution
     */
    public function testConcurrentWorkerExecution(): void
    {
        $this->logTestInfo("Testing concurrent worker execution");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Create multiple transactions quickly
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
        
        // Run workers simultaneously (simulated)
        $this->logTestInfo("Running workers in rapid succession");
        
        $startTime = microtime(true);
        
        // Multiple worker runs to simulate concurrent execution
        $this->runPaymentVerificationWorker();
        $this->runPaymentVerificationWorker(); // Second run
        $this->runCirxTransferWorker();
        $this->runCirxTransferWorker(); // Second run
        
        $totalTime = microtime(true) - $startTime;
        
        // Verify no race conditions occurred
        foreach ($swapIds as $index => $swapId) {
            $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
            $statusResponse = $this->runApp($statusRequest);
            
            $statusData = $this->assertJsonResponse($statusResponse, 200, ['status']);
            
            // Status should be valid (not corrupted by race conditions)
            $validStatuses = [
                'pending_payment_verification',
                'payment_verified',
                'cirx_transfer_pending',
                'cirx_transfer_initiated',
                'completed',
                'failed_payment_verification'
            ];
            
            $this->assertContains($statusData['status'], $validStatuses,
                "Transaction status should be valid after concurrent worker execution"
            );
        }
        
        $this->logTestInfo("Concurrent worker execution completed", [
            'total_time' => round($totalTime, 2) . 's',
            'transactions_processed' => count($swapIds)
        ]);
    }
}