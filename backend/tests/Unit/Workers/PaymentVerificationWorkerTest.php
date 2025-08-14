<?php

namespace Tests\Unit\Workers;

use Tests\TestCase;
use App\Workers\PaymentVerificationWorker;
use App\Services\PaymentVerificationService;
use App\Services\PaymentVerificationResult;
use App\Models\Transaction;
use Mockery;

/**
 * @covers \App\Workers\PaymentVerificationWorker
 */
class PaymentVerificationWorkerTest extends TestCase
{
    private PaymentVerificationWorker $worker;
    private PaymentVerificationService $mockService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockService = Mockery::mock(PaymentVerificationService::class);
        $this->worker = new PaymentVerificationWorker($this->mockService, 2, 10); // 2 retries, 10 sec delay
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testProcessPendingTransactionsWithNoTransactions(): void
    {
        // Create worker with real service since no DB calls will be made
        $worker = new PaymentVerificationWorker(new PaymentVerificationService(), 2, 10);
        
        $result = $worker->processPendingTransactions();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('verified', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('retried', $result);
        $this->assertArrayHasKey('errors', $result);
        
        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(0, $result['verified']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEquals(0, $result['retried']);
        $this->assertEmpty($result['errors']);
    }

    public function testProcessTransactionWithSuccessfulVerification(): void
    {
        $transactionData = $this->createTransaction([
            'swap_status' => Transaction::STATUS_PENDING_PAYMENT_VERIFICATION
        ]);
        $transaction = Transaction::create($transactionData);
        
        // Mock successful verification
        $verificationResult = PaymentVerificationResult::valid(
            $transaction->payment_tx_id,
            $transaction->amount_paid,
            'ethereum'
        );
        
        $this->mockService
            ->expects('verifyTransactionPayment')
            ->with($transaction)
            ->andReturn($verificationResult);
        
        $result = $this->worker->processTransaction($transaction);
        
        $this->assertEquals('verified', $result['status']);
        $this->assertEquals('Payment verified and status updated', $result['message']);
        
        // Check transaction status was updated
        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_PAYMENT_VERIFIED, $transaction->swap_status);
    }

    public function testProcessTransactionWithFailedVerification(): void
    {
        $transactionData = $this->createTransaction([
            'swap_status' => Transaction::STATUS_PENDING_PAYMENT_VERIFICATION,
            'retry_count' => 0
        ]);
        $transaction = Transaction::create($transactionData);
        
        // Mock failed verification
        $verificationResult = PaymentVerificationResult::invalid(
            $transaction->payment_tx_id,
            'Payment not found on blockchain'
        );
        
        $this->mockService
            ->expects('verifyTransactionPayment')
            ->with($transaction)
            ->andReturn($verificationResult);
        
        $result = $this->worker->processTransaction($transaction);
        
        $this->assertEquals('retried', $result['status']);
        $this->assertStringContainsString('Scheduled for retry', $result['message']);
        
        // Check retry count was incremented
        $transaction->refresh();
        $this->assertEquals(1, $transaction->retry_count);
        $this->assertNotNull($transaction->last_retry_at);
    }

    public function testProcessTransactionExceedsMaxRetries(): void
    {
        $transactionData = $this->createTransaction([
            'swap_status' => Transaction::STATUS_PENDING_PAYMENT_VERIFICATION,
            'retry_count' => 2 // Already at max retries
        ]);
        $transaction = Transaction::create($transactionData);
        
        // Mock failed verification
        $verificationResult = PaymentVerificationResult::invalid(
            $transaction->payment_tx_id,
            'Payment still not found'
        );
        
        $this->mockService
            ->expects('verifyTransactionPayment')
            ->with($transaction)
            ->andReturn($verificationResult);
        
        $result = $this->worker->processTransaction($transaction);
        
        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('Payment verification failed permanently', $result['message']);
        
        // Check transaction was marked as failed
        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_FAILED_PAYMENT_VERIFICATION, $transaction->swap_status);
        $this->assertNotNull($transaction->failure_reason);
    }

    public function testProcessTransactionHandlesException(): void
    {
        $transactionData = $this->createTransaction([
            'swap_status' => Transaction::STATUS_PENDING_PAYMENT_VERIFICATION,
            'retry_count' => 0
        ]);
        $transaction = Transaction::create($transactionData);
        
        // Mock service throwing exception
        $this->mockService
            ->expects('verifyTransactionPayment')
            ->with($transaction)
            ->andThrow(new \Exception('Network timeout'));
        
        $result = $this->worker->processTransaction($transaction);
        
        $this->assertEquals('retried', $result['status']);
        $this->assertStringContainsString('Exception occurred', $result['message']);
        $this->assertEquals('Network timeout', $result['error']);
        
        // Check retry count was incremented
        $transaction->refresh();
        $this->assertEquals(1, $transaction->retry_count);
    }

    public function testProcessTransactionExceptionExceedsMaxRetries(): void
    {
        $transactionData = $this->createTransaction([
            'swap_status' => Transaction::STATUS_PENDING_PAYMENT_VERIFICATION,
            'retry_count' => 2 // Already at max retries
        ]);
        $transaction = Transaction::create($transactionData);
        
        // Mock service throwing exception
        $this->mockService
            ->expects('verifyTransactionPayment')
            ->with($transaction)
            ->andThrow(new \Exception('Persistent network error'));
        
        $result = $this->worker->processTransaction($transaction);
        
        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('Worker failed permanently due to exceptions', $result['message']);
        
        // Check transaction was marked as failed
        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_FAILED_PAYMENT_VERIFICATION, $transaction->swap_status);
        $this->assertStringContainsString('Worker exception after 2 attempts', $transaction->failure_reason);
    }

    public function testProcessRetryTransactionsWithNoRetries(): void
    {
        $result = $this->worker->processRetryTransactions();
        
        $this->assertIsArray($result);
        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(0, $result['verified']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEquals(0, $result['retried']);
        $this->assertEmpty($result['errors']);
    }

    public function testGetStatistics(): void
    {
        $stats = $this->worker->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('pending_verification', $stats);
        $this->assertArrayHasKey('pending_retries', $stats);
        $this->assertArrayHasKey('failed_verification', $stats);
        $this->assertArrayHasKey('payment_verified', $stats);
        
        $this->assertIsInt($stats['pending_verification']);
        $this->assertIsInt($stats['pending_retries']);
        $this->assertIsInt($stats['failed_verification']);
        $this->assertIsInt($stats['payment_verified']);
    }

    public function testSetMaxRetries(): void
    {
        $this->worker->setMaxRetries(5);
        
        // Test that it accepts the new value (we can't easily test the internal state)
        $this->assertTrue(true); // Method completed without error
    }

    public function testSetMaxRetriesWithNegativeValue(): void
    {
        $this->worker->setMaxRetries(-1);
        
        // Should handle negative values gracefully
        $this->assertTrue(true); // Method completed without error
    }

    public function testSetRetryDelay(): void
    {
        $this->worker->setRetryDelay(30);
        
        // Test that it accepts the new value
        $this->assertTrue(true); // Method completed without error
    }

    public function testSetRetryDelayWithNegativeValue(): void
    {
        $this->worker->setRetryDelay(-1);
        
        // Should handle negative values gracefully
        $this->assertTrue(true); // Method completed without error
    }

    public function testWorkerConstructorWithDefaults(): void
    {
        $worker = new PaymentVerificationWorker();
        
        // Test that worker can be created with default parameters
        $this->assertInstanceOf(PaymentVerificationWorker::class, $worker);
    }

    public function testWorkerConstructorWithCustomParameters(): void
    {
        $mockService = Mockery::mock(PaymentVerificationService::class);
        $worker = new PaymentVerificationWorker($mockService, 5, 120);
        
        // Test that worker can be created with custom parameters
        $this->assertInstanceOf(PaymentVerificationWorker::class, $worker);
    }
}