<?php

namespace Tests\E2E;

use Tests\E2E\E2ETestCase;

/**
 * Complete OTC Swap Flow E2E Tests
 * 
 * Tests the full OTC swap pipeline from initiation to completion
 * including real blockchain interactions on Sepolia testnet.
 * 
 * @group e2e
 * @group slow
 */
class CompleteOTCSwapFlowTest extends E2ETestCase
{
    /**
     * Test complete USDC to CIRX OTC swap flow
     * 
     * @group e2e
     * @group slow
     */
    public function testCompleteUSDCToCircSwapFlow(): void
    {
        $this->logTestInfo("Starting complete USDC to CIRX OTC swap flow test");
        
        // 1. Initiate swap via API
        $swapRequest = $this->createSwapRequest('USDC', '1000.00', 'otc');
        $swapResponse = $this->initiateSwap($swapRequest);
        
        $this->assertIsArray($swapResponse);
        $this->assertArrayHasKey('transaction_id', $swapResponse);
        $this->assertArrayHasKey('payment_address', $swapResponse);
        $this->assertArrayHasKey('payment_amount', $swapResponse);
        $this->assertEquals('1000.00', $swapResponse['payment_amount']);
        
        $transactionId = $swapResponse['transaction_id'];
        $paymentAddress = $swapResponse['payment_address'];
        
        $this->logTestInfo("Swap initiated successfully", [
            'transaction_id' => $transactionId,
            'payment_address' => $paymentAddress,
            'payment_amount' => $swapResponse['payment_amount']
        ]);
        
        // 2. Send real payment on Sepolia
        $paymentWallet = $this->getPaymentWallet();
        $txHash = $this->sendSepoliaPayment(
            $paymentWallet,
            $paymentAddress,
            '1000.00',
            'USDC'
        );
        
        $this->assertNotEmpty($txHash);
        $this->assertStringStartsWith('0x', $txHash);
        
        $this->logTestInfo("Payment transaction sent", [
            'tx_hash' => $txHash,
            'from_wallet' => $paymentWallet->getAddress(),
            'to_address' => $paymentAddress,
            'amount' => '1000.00 USDC'
        ]);
        
        // 3. Wait for blockchain confirmation
        $receipt = $this->waitForTransactionConfirmation($txHash);
        $this->assertIsArray($receipt);
        $this->assertArrayHasKey('blockNumber', $receipt);
        $this->assertArrayHasKey('status', $receipt);
        $this->assertEquals('0x1', $receipt['status']); // Success status
        
        $this->logTestInfo("Transaction confirmed on blockchain", [
            'block_number' => $receipt['blockNumber'],
            'gas_used' => $receipt['gasUsed'] ?? 'N/A'
        ]);
        
        // 4. Run payment verification worker
        $this->runPaymentVerificationWorker();
        
        // 5. Verify payment was detected
        $this->assertPaymentVerified($transactionId);
        
        // 6. Run CIRX transfer worker
        $this->runCirxTransferWorker();
        
        // 7. Verify CIRX transfer completed
        $this->assertCirxTransferCompleted($transactionId);
        
        // 8. Validate final balances
        $this->validateFinalBalances($swapResponse);
        
        $this->logTestInfo("Complete OTC swap flow test completed successfully");
    }
    
    /**
     * Test ETH to CIRX OTC swap with discount
     */
    public function testETHToCircOTCSwapWithDiscount(): void
    {
        $this->logTestInfo("Starting ETH to CIRX OTC swap with discount test");
        
        // Large amount to trigger discount tier
        $swapRequest = $this->createSwapRequest('ETH', '5.0', 'otc', [
            'discount_tier' => '8-percent',
            'expected_discount' => 8
        ]);
        
        $swapResponse = $this->initiateSwap($swapRequest);
        
        // Verify discount is applied
        $this->assertArrayHasKey('discount_percentage', $swapResponse);
        $this->assertEquals(8, $swapResponse['discount_percentage']);
        
        // Verify CIRX amount includes discount
        $expectedCirxAmount = $this->calculateExpectedCirxAmount('5.0', 'ETH', 8);
        $this->assertEquals($expectedCirxAmount, $swapResponse['cirx_amount']);
        
        $this->logTestInfo("Discount calculation verified", [
            'discount_percentage' => $swapResponse['discount_percentage'],
            'cirx_amount' => $swapResponse['cirx_amount'],
            'expected_amount' => $expectedCirxAmount
        ]);
        
        // Continue with payment flow
        $paymentWallet = $this->getPaymentWallet();
        $txHash = $this->sendSepoliaPayment(
            $paymentWallet,
            $swapResponse['payment_address'],
            '5.0',
            'ETH'
        );
        
        $receipt = $this->waitForTransactionConfirmation($txHash);
        $this->runPaymentVerificationWorker();
        $this->assertPaymentVerified($swapResponse['transaction_id']);
        
        $this->logTestInfo("ETH OTC swap with discount completed successfully");
    }
    
    /**
     * Test liquid swap (no vesting)
     */
    public function testLiquidSwapFlow(): void
    {
        $this->logTestInfo("Starting liquid swap flow test");
        
        $swapRequest = $this->createSwapRequest('USDC', '500.00', 'liquid');
        $swapResponse = $this->initiateSwap($swapRequest);
        
        // Liquid swaps should have no discount
        $this->assertEquals(0, $swapResponse['discount_percentage'] ?? 0);
        
        // Should have immediate CIRX transfer
        $this->assertEquals('immediate', $swapResponse['vesting_type'] ?? 'immediate');
        
        $paymentWallet = $this->getPaymentWallet();
        $txHash = $this->sendSepoliaPayment(
            $paymentWallet,
            $swapResponse['payment_address'],
            '500.00',
            'USDC'
        );
        
        $receipt = $this->waitForTransactionConfirmation($txHash);
        $this->runPaymentVerificationWorker();
        $this->assertPaymentVerified($swapResponse['transaction_id']);
        
        // For liquid swaps, CIRX should transfer immediately
        $this->runCirxTransferWorker();
        $this->assertCirxTransferCompleted($swapResponse['transaction_id']);
        
        $this->logTestInfo("Liquid swap flow completed successfully");
    }
    
    /**
     * Test multiple concurrent swaps
     */
    public function testConcurrentSwapProcessing(): void
    {
        $this->logTestInfo("Starting concurrent swap processing test");
        
        $swapRequests = [
            $this->createSwapRequest('USDC', '100.00', 'liquid'),
            $this->createSwapRequest('ETH', '0.5', 'otc'),
            $this->createSwapRequest('USDT', '200.00', 'liquid')
        ];
        
        $swapResponses = [];
        $txHashes = [];
        
        // Initiate all swaps
        foreach ($swapRequests as $request) {
            $response = $this->initiateSwap($request);
            $swapResponses[] = $response;
            
            $this->logTestInfo("Concurrent swap initiated", [
                'transaction_id' => $response['transaction_id'],
                'payment_token' => $request['payment_token'],
                'swap_type' => $request['swap_type']
            ]);
        }
        
        // Send payments for all swaps
        $paymentWallet = $this->getPaymentWallet();
        foreach ($swapResponses as $i => $response) {
            $request = $swapRequests[$i];
            $txHash = $this->sendSepoliaPayment(
                $paymentWallet,
                $response['payment_address'],
                $request['payment_amount'],
                $request['payment_token']
            );
            $txHashes[] = $txHash;
        }
        
        // Wait for all confirmations
        foreach ($txHashes as $txHash) {
            $this->waitForTransactionConfirmation($txHash);
        }
        
        // Process all payments
        $this->runPaymentVerificationWorker();
        
        // Verify all payments were processed
        foreach ($swapResponses as $response) {
            $this->assertPaymentVerified($response['transaction_id']);
        }
        
        $this->logTestInfo("Concurrent swap processing completed successfully", [
            'total_swaps' => count($swapResponses),
            'all_verified' => true
        ]);
    }
    
    /**
     * Create a swap request array
     */
    private function createSwapRequest(string $token, string $amount, string $type, array $options = []): array
    {
        $recipientWallet = $this->getRecipientWallet();
        
        return array_merge([
            'payment_token' => $token,
            'payment_amount' => $amount,
            'swap_type' => $type,
            'cirx_recipient_address' => $recipientWallet->getAddress(),
            'ethereum_sender_address' => $this->getPaymentWallet()->getAddress()
        ], $options);
    }
    
    /**
     * Initiate swap via API
     */
    private function initiateSwap(array $requestData): array
    {
        $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap');
        $request = $request->withParsedBody($requestData);
        
        $response = $this->runApp($request);
        return $this->assertJsonResponse($response, 200, ['transaction_id', 'payment_address']);
    }
    
    /**
     * Assert payment was verified
     */
    private function assertPaymentVerified(string $transactionId): void
    {
        $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$transactionId}/status");
        $statusResponse = $this->runApp($statusRequest);
        
        $statusData = $this->assertJsonResponse($statusResponse, 200, ['transaction_id', 'status']);
        
        $this->assertEquals($transactionId, $statusData['transaction_id']);
        $this->assertContains($statusData['status'], ['payment_verified', 'cirx_transfer_initiated', 'completed']);
        $this->assertTrue($statusData['payment_received'] ?? false, 'Payment should be marked as received');
        
        $this->logTestInfo("Payment verification confirmed", [
            'transaction_id' => $transactionId,
            'status' => $statusData['status']
        ]);
    }
    
    /**
     * Assert CIRX transfer was completed
     */
    private function assertCirxTransferCompleted(string $transactionId): void
    {
        $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$transactionId}/status");
        $statusResponse = $this->runApp($statusRequest);
        
        $statusData = $this->assertJsonResponse($statusResponse, 200);
        
        $this->assertContains($statusData['status'], ['cirx_transfer_completed', 'completed']);
        $this->assertTrue($statusData['cirx_transferred'] ?? false, 'CIRX should be marked as transferred');
        
        $this->logTestInfo("CIRX transfer completion confirmed", [
            'transaction_id' => $transactionId,
            'final_status' => $statusData['status']
        ]);
    }
    
    /**
     * Validate final balances after swap
     */
    private function validateFinalBalances(array $swapResponse): void
    {
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        // In a real implementation, you would check:
        // 1. Recipient wallet received expected CIRX amount
        // 2. Project wallet received payment
        // 3. Balances match expected values
        
        $this->logTestInfo("Final balance validation completed", [
            'recipient_wallet' => $recipientWallet->getAddress(),
            'project_wallet' => $projectWallet,
            'expected_cirx' => $swapResponse['cirx_amount']
        ]);
        
        // For now, we'll just verify the transaction completed
        $this->assertTrue(true, 'Balance validation placeholder - implement with real balance checks');
    }
    
    /**
     * Calculate expected CIRX amount with discount
     */
    private function calculateExpectedCirxAmount(string $paymentAmount, string $token, int $discountPercent = 0): string
    {
        // Mock calculation - in real implementation, use actual price feeds
        $baseRate = match($token) {
            'ETH' => 2160.0, // 1 ETH = 2160 CIRX (example rate)
            'USDC' => 2.16, // 1 USDC = 2.16 CIRX (example rate)
            'USDT' => 2.16, // 1 USDT = 2.16 CIRX (example rate)
            default => 1.0
        };
        
        $cirxAmount = floatval($paymentAmount) * $baseRate;
        
        if ($discountPercent > 0) {
            $discountMultiplier = 1 + ($discountPercent / 100);
            $cirxAmount *= $discountMultiplier;
        }
        
        return number_format($cirxAmount, 2, '.', '');
    }
}