<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CirxTransferService;
use App\Services\CirxTransferResult;
use App\Models\Transaction;
use App\Exceptions\CirxTransferException;
use Mockery;

/**
 * @covers \App\Services\CirxTransferService
 * @covers \App\Services\CirxTransferResult
 * @covers \App\Exceptions\CirxTransferException
 */
class CirxTransferServiceTest extends TestCase
{
    private CirxTransferService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CirxTransferService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cirx_transfer_result_success()
    {
        $txHash = '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370';
        $recipientAddress = '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370';
        $amount = '1000.0';
        $metadata = ['gas_used' => '21000'];

        $result = CirxTransferResult::success(
            $txHash,
            $recipientAddress,
            $amount,
            $metadata
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals($txHash, $result->getTransactionHash());
        $this->assertEquals($recipientAddress, $result->getRecipientAddress());
        $this->assertEquals($amount, $result->getAmount());
        $this->assertEquals($metadata, $result->getMetadata());
        $this->assertNull($result->getErrorMessage());
    }

    public function test_cirx_transfer_result_failure()
    {
        $recipientAddress = '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370';
        $amount = '1000.0';
        $errorMessage = 'Insufficient CIRX balance';

        $result = CirxTransferResult::failure($recipientAddress, $amount, $errorMessage);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals($recipientAddress, $result->getRecipientAddress());
        $this->assertEquals($amount, $result->getAmount());
        $this->assertEquals($errorMessage, $result->getErrorMessage());
        $this->assertNull($result->getTransactionHash());
        $this->assertEquals([], $result->getMetadata());
    }

    public function test_validates_circular_protocol_address_format()
    {
        $invalidAddresses = [
            '0x123',                                                                    // Too short
            '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef12345',      // 63 chars, not 64
            '0xgg9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',      // Invalid hex
            'bb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',        // Missing 0x prefix
            '',                                                                         // Empty
        ];

        foreach ($invalidAddresses as $address) {
            $this->assertFalse($this->service->isValidCircularAddress($address));
        }

        // Valid address (64 hex characters with 0x prefix)
        $validAddress = '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370';
        $this->assertTrue($this->service->isValidCircularAddress($validAddress));
    }

    public function test_calculates_cirx_amount_correctly()
    {
        $testCases = [
            // [paymentAmount, paymentToken, swapType, expectedCirxAmount]
            ['1.0', 'ETH', 'liquid', '1080.0'],        // 1 ETH = $2700 = 1080 CIRX (liquid)
            ['1.0', 'ETH', 'otc', '1134.0'],           // 1 ETH = $2700 = 1080 CIRX + 5% discount ($1K-$10K tier)
            ['100.0', 'USDC', 'liquid', '40.0'],       // 100 USDC = $100 = 40 CIRX (liquid)  
            ['100.0', 'USDC', 'otc', '40.0'],          // 100 USDC = $100 = 40 CIRX (no discount under $1K)
            ['1000.0', 'USDT', 'otc', '420.0'],        // 1000 USDT = $1000 = 400 CIRX + 5% discount
        ];

        foreach ($testCases as [$paymentAmount, $paymentToken, $swapType, $expectedCirxAmount]) {
            $result = $this->service->calculateCirxAmount($paymentAmount, $paymentToken, $swapType);
            $this->assertEquals($expectedCirxAmount, $result, 
                "Failed for {$paymentAmount} {$paymentToken} ({$swapType}): expected {$expectedCirxAmount}, got {$result}");
        }
    }

    public function test_determines_discount_percentage_correctly()
    {
        $testCases = [
            // [amountUSD, expectedDiscount]
            ['500.0', 0],      // Under $1K threshold
            ['1500.0', 5],     // $1K-$10K tier (5%)
            ['25000.0', 8],    // $10K-$50K tier (8%)
            ['75000.0', 12],   // $50K+ tier (12%)
            ['999.99', 0],     // Just under $1K
            ['1000.00', 5],    // Exactly $1K
            ['49999.99', 8],   // Just under $50K
            ['50000.00', 12],  // Exactly $50K
        ];

        foreach ($testCases as [$amountUSD, $expectedDiscount]) {
            $result = $this->service->getDiscountPercentage($amountUSD);
            $this->assertEquals($expectedDiscount, $result,
                "Failed for USD {$amountUSD}: expected {$expectedDiscount}%, got {$result}%");
        }
    }

    public function test_transfer_cirx_with_valid_transaction()
    {
        $transactionData = $this->createTransaction([
            'payment_tx_id' => '0xabc123',
            'payment_chain' => 'ethereum',
            'amount_paid' => '1.0',
            'payment_token' => 'ETH',
            'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
        ]);
        $transaction = Transaction::create($transactionData);

        // Since we can't easily mock blockchain calls, test the error handling for when
        // the CIRX wallet doesn't have sufficient balance or the service is unavailable
        $result = $this->service->transferCirxToUser($transaction);

        // Should fail due to no CIRX wallet configured
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('CIRX wallet not configured', $result->getErrorMessage());
        
        // Transaction status should be updated to reflect the transfer attempt
        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_FAILED_CIRX_TRANSFER, $transaction->swap_status);
        $this->assertNotNull($transaction->failure_reason);
    }

    public function test_batch_transfer_cirx_processes_multiple_transactions()
    {
        $transactions = [];
        
        for ($i = 0; $i < 3; $i++) {
            $transactionData = $this->createTransaction([
                'payment_tx_id' => '0x' . str_repeat(dechex($i), 64),
                'payment_chain' => 'ethereum',
                'amount_paid' => '1.0',
                'payment_token' => 'ETH',
                'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
                'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
            ]);
            $transactions[] = Transaction::create($transactionData);
        }

        $results = $this->service->batchTransferCirx($transactions);

        $this->assertCount(3, $results);
        
        // All should fail due to CIRX wallet not configured
        foreach ($results as $transactionId => $result) {
            $this->assertFalse($result->isSuccess());
            $this->assertStringContainsString('CIRX wallet not configured', $result->getErrorMessage());
        }
    }

    public function test_cirx_transfer_exception_methods()
    {
        $recipientAddress = '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370';
        $amount = '1000.0';
        $message = 'Network timeout';

        $exception = CirxTransferException::transferFailed($message, $recipientAddress, $amount);

        $this->assertEquals($recipientAddress, $exception->getRecipientAddress());
        $this->assertEquals($amount, $exception->getAmount());
        $this->assertEquals("CIRX transfer failed: {$message}", $exception->getMessage());
        $this->assertEquals(2001, $exception->getCode());
    }

    public function test_insufficient_balance_exception()
    {
        $recipientAddress = '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370';
        $amount = '1000.0';
        $availableBalance = '500.0';

        $exception = CirxTransferException::insufficientBalance($recipientAddress, $amount, $availableBalance);

        $this->assertEquals($recipientAddress, $exception->getRecipientAddress());
        $this->assertEquals($amount, $exception->getAmount());
        $this->assertEquals("Insufficient CIRX balance: requested {$amount}, available {$availableBalance}", $exception->getMessage());
        $this->assertEquals(2002, $exception->getCode());
    }

    public function test_wallet_configuration_exception()
    {
        $exception = CirxTransferException::walletNotConfigured();

        $this->assertEquals('CIRX wallet not configured or private key not available', $exception->getMessage());
        $this->assertEquals(2003, $exception->getCode());
    }

    /**
     * Integration test placeholder for when blockchain client is available
     */
    public function test_integration_with_real_blockchain()
    {
        $this->markTestSkipped('Integration test - requires blockchain client configuration');
        
        // When blockchain client is configured, this test would:
        // 1. Configure test CIRX wallet with test tokens
        // 2. Make real blockchain calls to transfer CIRX
        // 3. Verify the transaction on-chain
        // 4. Test all transfer scenarios with real data
    }

    /**
     * Test token price conversion logic
     */
    public function test_token_to_usd_conversion()
    {
        // Access private method using reflection to test the logic
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('convertToUSD');
        $method->setAccessible(true);

        // Mock token prices (these would come from a price oracle in production)
        $testCases = [
            ['1.0', 'ETH', 2700.0],    // 1 ETH = $2700
            ['100.0', 'USDC', 100.0],  // 100 USDC = $100
            ['1000.0', 'USDT', 1000.0], // 1000 USDT = $1000
        ];

        foreach ($testCases as [$amount, $token, $expectedUSD]) {
            $result = $method->invoke($this->service, $amount, $token);
            $this->assertEquals($expectedUSD, $result,
                "Failed for {$amount} {$token}: expected {$expectedUSD} USD, got {$result} USD");
        }
    }

    public function test_validates_transaction_is_ready_for_transfer()
    {
        // Test various transaction states
        $readyTransaction = $this->createTransaction([
            'payment_tx_id' => '0xabc123',
            'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
        ]);
        $readyTx = Transaction::create($readyTransaction);

        $this->assertTrue($this->service->isTransactionReadyForTransfer($readyTx));

        // Test transaction not ready (various states)
        $notReadyStates = [
            Transaction::STATUS_PENDING_PAYMENT_VERIFICATION,
            Transaction::STATUS_CIRX_TRANSFER_PENDING,
            Transaction::STATUS_COMPLETED,
            Transaction::STATUS_FAILED_PAYMENT_VERIFICATION,
            Transaction::STATUS_FAILED_CIRX_TRANSFER,
        ];

        foreach ($notReadyStates as $status) {
            $notReadyTransaction = $this->createTransaction([
                'payment_tx_id' => '0x' . $status,
                'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
                'swap_status' => $status,
            ]);
            $notReadyTx = Transaction::create($notReadyTransaction);

            $this->assertFalse($this->service->isTransactionReadyForTransfer($notReadyTx),
                "Transaction with status {$status} should not be ready for transfer");
        }
    }

    /**
     * Test platform fee calculation (4 CIRX fee) - still used for internal calculations
     */
    public function test_calculates_platform_fee_in_payment_token()
    {
        $testCases = [
            // [paymentToken, expectedFeeInToken]
            ['ETH', '0.0037037'],     // 4 CIRX = $10, $10 / $2700 per ETH = 0.0037037 ETH
            ['USDC', '10.0'],         // 4 CIRX = $10, $10 / $1 per USDC = 10 USDC
            ['USDT', '10.0'],         // 4 CIRX = $10, $10 / $1 per USDT = 10 USDT
            ['BNB', '0.0333333'],     // 4 CIRX = $10, $10 / $300 per BNB = 0.0333333 BNB
            ['MATIC', '12.5'],        // 4 CIRX = $10, $10 / $0.80 per MATIC = 12.5 MATIC
        ];

        foreach ($testCases as [$paymentToken, $expectedFeeInToken]) {
            $result = $this->service->calculatePlatformFeeInPaymentToken($paymentToken);
            $this->assertEquals($expectedFeeInToken, $result,
                "Failed for {$paymentToken}: expected {$expectedFeeInToken}, got {$result}");
        }
    }

    /**
     * Test base payment amount calculation (NO platform fee added to payment)
     */
    public function test_calculates_total_payment_amount_with_platform_fee()
    {
        $testCases = [
            // [cirxAmount, paymentToken, expectedBasePaymentAmount] - NO platform fee added
            ['400.0', 'USDC', '1000.0'],     // 400 CIRX = $1000, user pays exactly $1000 in USDC
            ['1080.0', 'ETH', '1.0'],        // 1080 CIRX = $2700, user pays exactly 1.0 ETH ($2700)
        ];

        foreach ($testCases as [$cirxAmount, $paymentToken, $expectedBasePayment]) {
            $result = $this->service->calculateTotalPaymentWithFee($cirxAmount, $paymentToken);
            $this->assertEquals($expectedBasePayment, $result,
                "Failed for {$cirxAmount} CIRX in {$paymentToken}: expected {$expectedBasePayment}, got {$result}");
        }
    }

    /**
     * Test that CIRX amount calculations work correctly and platform fee gets subtracted
     */
    public function test_cirx_amount_calculation_unchanged_by_platform_fee()
    {
        // These tests verify the gross CIRX calculation (before 4 CIRX platform fee deduction)
        
        $testCases = [
            // [paymentAmount, paymentToken, swapType, expectedGrossCirxAmount]
            ['1.0', 'ETH', 'liquid', '1080.0'],        // 1 ETH = $2700 = 1080 CIRX (gross)
            ['100.0', 'USDC', 'liquid', '40.0'],       // 100 USDC = $100 = 40 CIRX (gross)
            ['1000.0', 'USDT', 'otc', '420.0'],        // 1000 USDT = $1000 = 400 CIRX + 5% OTC discount = 420 CIRX (gross)
        ];

        foreach ($testCases as [$paymentAmount, $paymentToken, $swapType, $expectedGrossCirxAmount]) {
            $result = $this->service->calculateCirxAmount($paymentAmount, $paymentToken, $swapType);
            $this->assertEquals($expectedGrossCirxAmount, $result,
                "Gross CIRX calculation: {$paymentAmount} {$paymentToken} ({$swapType})");
        }
        
        // Additional test: Verify that small payments are properly validated
        // (payments that would result in less than 4 CIRX should be rejected)
        $this->assertTrue(
            $this->service->validatePaymentAmount(
                $this->createMockTransaction('100.0', 'USDC') // 40 CIRX > 4 CIRX fee - valid
            ),
            'Payments resulting in more than 4 CIRX should be valid'
        );
        
        $this->assertFalse(
            $this->service->validatePaymentAmount(
                $this->createMockTransaction('5.0', 'USDC') // 2 CIRX < 4 CIRX fee - invalid
            ),
            'Payments resulting in less than 4 CIRX should be rejected'
        );
    }
    
    private function createMockTransaction($amountPaid, $paymentToken)
    {
        $mock = $this->createMock(Transaction::class);
        $mock->amount_paid = $amountPaid;
        $mock->payment_token = $paymentToken;
        $mock->cirx_recipient_address = '0x1234567890123456789012345678901234567890123456789012345678901234';
        $mock->swap_status = Transaction::STATUS_PAYMENT_VERIFIED;
        return $mock;
    }
}