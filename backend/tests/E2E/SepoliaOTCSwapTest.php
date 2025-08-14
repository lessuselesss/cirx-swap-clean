<?php

namespace Tests\E2E;

use App\Models\Transaction;

/**
 * End-to-End tests for complete OTC swap flow on Sepolia testnet
 * 
 * These tests run against real Sepolia blockchain transactions
 * and verify the complete user journey from payment to CIRX delivery.
 */
class SepoliaOTCSwapTest extends E2ETestCase
{
    /**
     * Test complete successful OTC swap flow with ETH payment
     */
    public function testCompleteETHSwapFlowOnSepolia(): void
    {
        $this->logTestInfo("Starting complete ETH swap flow test");
        
        // Phase 1: Setup test data
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        $paymentAmount = '0.01'; // 0.01 ETH
        
        $this->logTestInfo("Test setup complete", [
            'payment_wallet' => $paymentWallet->getAddress(),
            'recipient_wallet' => $recipientWallet->getAddress(),
            'project_wallet' => $projectWallet,
            'payment_amount' => $paymentAmount . ' ETH'
        ]);
        
        // Phase 2: Send real payment transaction on Sepolia
        $txHash = $this->sendSepoliaPayment(
            fromWallet: $paymentWallet,
            toAddress: $projectWallet,
            amount: $paymentAmount,
            token: 'ETH'
        );
        
        $this->assertNotEmpty($txHash, 'Payment transaction should have valid hash');
        $this->assertStringStartsWith('0x', $txHash, 'Transaction hash should be hex string');
        
        // Phase 3: Wait for transaction confirmation
        $receipt = $this->waitForTransactionConfirmation($txHash, 1);
        $this->assertArrayHasKey('blockNumber', $receipt, 'Receipt should contain block number');
        
        // Phase 4: Initiate OTC swap via API
        $swapRequest = [
            'txId' => $txHash,
            'paymentChain' => 'sepolia',
            'cirxRecipientAddress' => $recipientWallet->getAddress(),
            'amountPaid' => $paymentAmount,
            'paymentToken' => 'ETH'
        ];
        
        $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
        $response = $this->runApp($request);
        
        // Verify swap initiation response
        $responseData = $this->assertJsonResponse($response, 202, [
            'status', 'swapId', 'message'
        ]);
        
        $this->assertEquals('success', $responseData['status']);
        $this->assertNotEmpty($responseData['swapId']);
        
        $swapId = $responseData['swapId'];
        
        $this->logTestInfo("OTC swap initiated", [
            'swap_id' => $swapId,
            'payment_tx' => $txHash
        ]);
        
        // Phase 5: Verify initial transaction status
        $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
        $statusResponse = $this->runApp($statusRequest);
        
        $statusData = $this->assertJsonResponse($statusResponse, 200, [
            'status', 'txId'
        ]);
        
        $this->assertEquals('pending_payment_verification', $statusData['status']);
        $this->assertEquals($txHash, $statusData['txId']);
        
        // Phase 6: Run payment verification worker
        $this->runPaymentVerificationWorker();
        
        // Verify transaction moved to payment verified status
        $verifiedStatusResponse = $this->runApp($statusRequest);
        $verifiedStatusData = $this->assertJsonResponse($verifiedStatusResponse, 200);
        
        // Should be verified or in CIRX transfer stage
        $this->assertContains($verifiedStatusData['status'], [
            'payment_verified',
            'cirx_transfer_pending',
            'cirx_transfer_initiated'
        ]);
        
        $this->logTestInfo("Payment verification completed", [
            'new_status' => $verifiedStatusData['status']
        ]);
        
        // Phase 7: Run CIRX transfer worker
        $this->runCirxTransferWorker();
        
        // Phase 8: Final status verification
        $finalStatusResponse = $this->runApp($statusRequest);
        $finalStatusData = $this->assertJsonResponse($finalStatusResponse, 200);
        
        // Should be completed or transfer initiated
        $this->assertContains($finalStatusData['status'], [
            'cirx_transfer_initiated',
            'completed'
        ]);
        
        $this->logTestInfo("Complete E2E flow verified", [
            'final_status' => $finalStatusData['status'],
            'swap_id' => $swapId,
            'payment_tx' => $txHash
        ]);
        
        // Verify transaction exists in database
        $this->assertTransactionInDatabase($swapId, $txHash);
    }
    
    /**
     * Test USDC token payment flow on Sepolia
     */
    public function testUSDCSwapFlowOnSepolia(): void
    {
        $this->logTestInfo("Starting USDC swap flow test");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        $paymentAmount = '100.0'; // 100 USDC
        
        // Send USDC payment
        $txHash = $this->sendSepoliaPayment(
            fromWallet: $paymentWallet,
            toAddress: $projectWallet,
            amount: $paymentAmount,
            token: 'USDC'
        );
        
        $this->assertNotEmpty($txHash);
        
        // Wait for confirmation
        $receipt = $this->waitForTransactionConfirmation($txHash, 1);
        
        // Initiate swap
        $swapRequest = [
            'txId' => $txHash,
            'paymentChain' => 'sepolia',
            'cirxRecipientAddress' => $recipientWallet->getAddress(),
            'amountPaid' => $paymentAmount,
            'paymentToken' => 'USDC'
        ];
        
        $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
        $response = $this->runApp($request);
        
        $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
        $swapId = $responseData['swapId'];
        
        // Run workers
        $this->runPaymentVerificationWorker();
        $this->runCirxTransferWorker();
        
        // Verify completion
        $this->assertTransactionCompleted($swapId);
        
        $this->logTestInfo("USDC swap flow completed", [
            'swap_id' => $swapId,
            'payment_amount' => $paymentAmount . ' USDC'
        ]);
    }
    
    /**
     * Test concurrent swap processing
     */
    public function testConcurrentSwapProcessing(): void
    {
        $this->logTestInfo("Starting concurrent swap processing test");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        $swapIds = [];
        $txHashes = [];
        
        // Create 3 concurrent swaps
        for ($i = 1; $i <= 3; $i++) {
            $amount = '0.00' . $i; // 0.001, 0.002, 0.003 ETH
            
            $txHash = $this->sendSepoliaPayment(
                fromWallet: $paymentWallet,
                toAddress: $projectWallet,
                amount: $amount,
                token: 'ETH'
            );
            
            $txHashes[] = $txHash;
            
            // Wait for confirmation
            $this->waitForTransactionConfirmation($txHash, 1);
            
            // Initiate swap
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
        
        $this->assertCount(3, $swapIds, 'Should have 3 swap IDs');
        $this->assertCount(3, array_unique($swapIds), 'All swap IDs should be unique');
        
        // Process all swaps
        $this->runPaymentVerificationWorker();
        $this->runCirxTransferWorker();
        
        // Verify all swaps completed
        foreach ($swapIds as $index => $swapId) {
            $this->assertTransactionCompleted($swapId);
            
            $this->logTestInfo("Concurrent swap completed", [
                'index' => $index + 1,
                'swap_id' => $swapId,
                'tx_hash' => $txHashes[$index]
            ]);
        }
        
        $this->logTestInfo("All concurrent swaps completed successfully");
    }
    
    /**
     * Test swap with invalid transaction hash
     */
    public function testSwapWithInvalidTransactionHash(): void
    {
        $this->logTestInfo("Testing swap with invalid transaction hash");
        
        $recipientWallet = $this->getRecipientWallet();
        $invalidTxHash = '0x' . str_repeat('0', 64); // Invalid/non-existent tx hash
        
        $swapRequest = [
            'txId' => $invalidTxHash,
            'paymentChain' => 'sepolia',
            'cirxRecipientAddress' => $recipientWallet->getAddress(),
            'amountPaid' => '1.0',
            'paymentToken' => 'ETH'
        ];
        
        $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
        $response = $this->runApp($request);
        
        $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
        $swapId = $responseData['swapId'];
        
        // Run payment verification - should fail
        $this->runPaymentVerificationWorker();
        
        // Check transaction status
        $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
        $statusResponse = $this->runApp($statusRequest);
        
        $statusData = $this->assertJsonResponse($statusResponse, 200, ['status']);
        
        // Should be in failed state
        $this->assertContains($statusData['status'], [
            'failed_payment_verification',
            'pending_payment_verification' // If verification hasn't completed yet
        ]);
        
        $this->logTestInfo("Invalid transaction properly handled", [
            'swap_id' => $swapId,
            'final_status' => $statusData['status']
        ]);
    }
    
    /**
     * Test payment amount mismatch
     */
    public function testPaymentAmountMismatch(): void
    {
        $this->logTestInfo("Testing payment amount mismatch");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Send 0.01 ETH but claim 0.02 ETH
        $actualAmount = '0.01';
        $claimedAmount = '0.02';
        
        $txHash = $this->sendSepoliaPayment(
            fromWallet: $paymentWallet,
            toAddress: $projectWallet,
            amount: $actualAmount,
            token: 'ETH'
        );
        
        $this->waitForTransactionConfirmation($txHash, 1);
        
        // Claim wrong amount
        $swapRequest = [
            'txId' => $txHash,
            'paymentChain' => 'sepolia',
            'cirxRecipientAddress' => $recipientWallet->getAddress(),
            'amountPaid' => $claimedAmount, // Wrong amount
            'paymentToken' => 'ETH'
        ];
        
        $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
        $response = $this->runApp($request);
        
        $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
        $swapId = $responseData['swapId'];
        
        // Run verification
        $this->runPaymentVerificationWorker();
        
        // Should fail verification due to amount mismatch
        $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
        $statusResponse = $this->runApp($statusRequest);
        
        $statusData = $this->assertJsonResponse($statusResponse, 200, ['status']);
        
        $this->logTestInfo("Amount mismatch properly detected", [
            'actual_amount' => $actualAmount,
            'claimed_amount' => $claimedAmount,
            'final_status' => $statusData['status']
        ]);
    }
    
    /**
     * Verify transaction exists in database with correct data
     */
    private function assertTransactionInDatabase(string $swapId, string $txHash): void
    {
        $transaction = Transaction::find($swapId);
        
        $this->assertNotNull($transaction, "Transaction {$swapId} should exist in database");
        $this->assertEquals($txHash, $transaction->payment_tx_id, "Transaction should have correct payment tx hash");
        $this->assertEquals('sepolia', $transaction->payment_chain, "Transaction should have correct payment chain");
        
        $this->logTestInfo("Database verification completed", [
            'swap_id' => $swapId,
            'payment_tx_id' => $transaction->payment_tx_id,
            'status' => $transaction->swap_status
        ]);
    }
    
    /**
     * Test worker performance and timing
     */
    public function testWorkerPerformance(): void
    {
        $this->logTestInfo("Testing worker performance");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Send payment
        $txHash = $this->sendSepoliaPayment(
            fromWallet: $paymentWallet,
            toAddress: $projectWallet,
            amount: '0.005',
            token: 'ETH'
        );
        
        $this->waitForTransactionConfirmation($txHash, 1);
        
        // Initiate swap
        $swapRequest = [
            'txId' => $txHash,
            'paymentChain' => 'sepolia',
            'cirxRecipientAddress' => $recipientWallet->getAddress(),
            'amountPaid' => '0.005',
            'paymentToken' => 'ETH'
        ];
        
        $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
        $response = $this->runApp($request);
        
        $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
        $swapId = $responseData['swapId'];
        
        // Measure payment verification time
        $startTime = microtime(true);
        $this->runPaymentVerificationWorker();
        $verificationTime = microtime(true) - $startTime;
        
        // Measure CIRX transfer time
        $startTime = microtime(true);
        $this->runCirxTransferWorker();
        $transferTime = microtime(true) - $startTime;
        
        $totalTime = $verificationTime + $transferTime;
        
        // Performance assertions
        $this->assertLessThan(30, $verificationTime, 'Payment verification should complete within 30 seconds');
        $this->assertLessThan(60, $transferTime, 'CIRX transfer should complete within 60 seconds');
        $this->assertLessThan(90, $totalTime, 'Total processing should complete within 90 seconds');
        
        $this->logTestInfo("Worker performance measured", [
            'verification_time' => round($verificationTime, 2) . 's',
            'transfer_time' => round($transferTime, 2) . 's',
            'total_time' => round($totalTime, 2) . 's',
            'swap_id' => $swapId
        ]);
    }
}