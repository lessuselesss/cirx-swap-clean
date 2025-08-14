<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PaymentVerificationService;
use App\Services\PaymentVerificationResult;
use App\Models\Transaction;
use App\Exceptions\PaymentVerificationException;
use Mockery;

/**
 * @covers \App\Services\PaymentVerificationService
 * @covers \App\Services\PaymentVerificationResult
 * @covers \App\Exceptions\PaymentVerificationException
 */
class PaymentVerificationServiceTest extends TestCase
{
    private PaymentVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentVerificationService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_payment_verification_result_success()
    {
        $txHash = '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234';
        $actualAmount = '1.5';
        $recipientAddress = '0x742d35Cc6641659EB96A69A2C27Bb9c5fCfe6e8c';
        $confirmations = 15;
        $metadata = ['verification_method' => 'test'];

        $result = PaymentVerificationResult::success(
            $txHash,
            $actualAmount,
            $recipientAddress,
            $confirmations,
            $metadata
        );

        $this->assertTrue($result->isValid());
        $this->assertEquals($txHash, $result->getTransactionHash());
        $this->assertEquals($actualAmount, $result->getActualAmount());
        $this->assertEquals($recipientAddress, $result->getRecipientAddress());
        $this->assertEquals($confirmations, $result->getConfirmations());
        $this->assertEquals($metadata, $result->getMetadata());
        $this->assertNull($result->getErrorMessage());
    }

    public function test_payment_verification_result_failure()
    {
        $txHash = '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234';
        $errorMessage = 'Transaction not found';

        $result = PaymentVerificationResult::failure($txHash, $errorMessage);

        $this->assertFalse($result->isValid());
        $this->assertEquals($txHash, $result->getTransactionHash());
        $this->assertEquals($errorMessage, $result->getErrorMessage());
        $this->assertEquals('0', $result->getActualAmount());
        $this->assertEquals('', $result->getRecipientAddress());
        $this->assertEquals(0, $result->getConfirmations());
    }

    public function test_verifies_transaction_with_database_record()
    {
        $transactionData = $this->createTransaction([
            'payment_tx_id' => '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234',
            'payment_chain' => 'ethereum',
            'amount_paid' => '1.5',
            'payment_token' => 'ETH'
        ]);
        $transaction = Transaction::create($transactionData);
        $projectWallet = '0x742d35Cc6641659EB96A69A2C27Bb9c5fCfe6e8c';

        // Since we can't easily mock HTTP calls in the current setup, let's test the fallback behavior
        // In test mode, the service uses fallback verification which returns success
        
        $result = $this->service->verifyTransactionRecord($transaction, $projectWallet);

        // In test mode, the service should return a success result using test fallback
        $this->assertTrue($result->isValid());
        $this->assertEquals('1.500000000000000000', $result->getActualAmount());
        
        // Verify transaction status was updated to reflect the success
        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_PAYMENT_VERIFIED, $transaction->swap_status);
        $this->assertNull($transaction->failure_reason);
    }

    public function test_batch_verify_transactions_handles_errors_gracefully()
    {
        $transactions = [];
        
        for ($i = 0; $i < 3; $i++) {
            $transactionData = $this->createTransaction([
                'payment_tx_id' => '0x' . str_repeat(dechex($i), 64),
                'payment_chain' => 'ethereum',
                'amount_paid' => '1.0',
                'payment_token' => 'ETH'
            ]);
            $transactions[] = Transaction::create($transactionData);
        }
        
        $projectWallet = '0x742d35Cc6641659EB96A69A2C27Bb9c5fCfe6e8c';

        $results = $this->service->batchVerifyTransactions($transactions, $projectWallet);

        $this->assertCount(3, $results);
        
        // All should fail due to indexer unavailability
        foreach ($results as $transactionId => $result) {
            $this->assertFalse($result->isValid());
            $this->assertStringContainsString('Indexer service unavailable', $result->getErrorMessage());
        }
    }

    public function test_payment_verification_exception_methods()
    {
        $txHash = '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234';
        $chain = 'ethereum';
        $message = 'Network timeout';

        $exception = PaymentVerificationException::apiError($message, $txHash, $chain);

        $this->assertEquals($txHash, $exception->getTransactionHash());
        $this->assertEquals($chain, $exception->getChain());
        $this->assertEquals("Failed to verify payment: {$message}", $exception->getMessage());
        $this->assertEquals(1001, $exception->getCode());
    }

    public function test_transaction_not_found_exception()
    {
        $txHash = '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234';
        $chain = 'ethereum';

        $exception = PaymentVerificationException::transactionNotFound($txHash, $chain);

        $this->assertEquals($txHash, $exception->getTransactionHash());
        $this->assertEquals($chain, $exception->getChain());
        $this->assertEquals("Transaction not found: {$txHash}", $exception->getMessage());
        $this->assertEquals(1002, $exception->getCode());
    }

    public function test_verification_failed_exception()
    {
        $txHash = '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234';
        $chain = 'ethereum';
        $reason = 'Insufficient amount';

        $exception = PaymentVerificationException::verificationFailed($reason, $txHash, $chain);

        $this->assertEquals($txHash, $exception->getTransactionHash());
        $this->assertEquals($chain, $exception->getChain());
        $this->assertEquals("Payment verification failed: {$reason}", $exception->getMessage());
        $this->assertEquals(1003, $exception->getCode());
    }

    /**
     * Integration test placeholder for when indexer is actually running
     * This would require the indexer service to be running with test data
     */
    public function test_integration_with_real_indexer()
    {
        $this->markTestSkipped('Integration test - requires running indexer service');
        
        // When the indexer is running, this test would:
        // 1. Start the indexer with test data
        // 2. Make real HTTP calls to verify functionality
        // 3. Test all the verification scenarios
        
        // For now, we're focusing on unit testing the core logic
    }

    /**
     * Test the verification logic components that don't require HTTP calls
     */
    public function test_required_confirmations_logic()
    {
        // Access private method using reflection to test the logic
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getRequiredConfirmations');
        $method->setAccessible(true);

        $this->assertEquals(12, $method->invoke($this->service, 'ethereum'));
        $this->assertEquals(20, $method->invoke($this->service, 'polygon'));
        $this->assertEquals(30, $method->invoke($this->service, 'solana'));
        $this->assertEquals(15, $method->invoke($this->service, 'binance-smart-chain'));
        $this->assertEquals(12, $method->invoke($this->service, 'unknown-chain'));
    }

    public function test_token_decimals_logic()
    {
        // Access private method using reflection to test the logic
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getTokenDecimals');
        $method->setAccessible(true);

        $this->assertEquals(6, $method->invoke($this->service, 'USDC'));
        $this->assertEquals(6, $method->invoke($this->service, 'USDT'));
        $this->assertEquals(18, $method->invoke($this->service, 'ETH'));
        $this->assertEquals(18, $method->invoke($this->service, 'MATIC'));
        $this->assertEquals(18, $method->invoke($this->service, 'BNB'));
        $this->assertEquals(18, $method->invoke($this->service, 'SOL'));
        $this->assertEquals(18, $method->invoke($this->service, 'UNKNOWN'));
    }
}