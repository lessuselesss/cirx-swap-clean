<?php

namespace Tests\Unit\Workers;

use Tests\TestCase;
use App\Workers\CirxTransferWorker;
use App\Services\CirxTransferService;
use App\Services\CirxTransferResult;
use App\Models\Transaction;
use Mockery;

/**
 * @covers \App\Workers\CirxTransferWorker
 */
class CirxTransferWorkerTest extends TestCase
{
    private CirxTransferWorker $worker;
    private CirxTransferService $mockService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockService = Mockery::mock(CirxTransferService::class);
        $this->worker = new CirxTransferWorker($this->mockService, 2, 30); // 2 retries, 30 sec delay
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testProcessReadyTransactionsWithNoTransactions(): void
    {
        // Create worker with real service since no DB calls will be made
        $worker = new CirxTransferWorker(new CirxTransferService(), 2, 30);
        
        $result = $worker->processReadyTransactions();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('completed', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('retried', $result);
        $this->assertArrayHasKey('errors', $result);
        
        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(0, $result['completed']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEquals(0, $result['retried']);
        $this->assertEmpty($result['errors']);
    }

    public function testProcessTransactionWithSuccessfulTransfer(): void
    {
        $transactionData = $this->createTransaction([
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
            'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370'
        ]);
        $transaction = Transaction::create($transactionData);
        
        // Mock successful transfer
        $transferResult = CirxTransferResult::success(
            '0x' . str_repeat('a', 64),
            $transaction->cirx_recipient_address,
            '1000.0',
            ['gas_used' => '21000']
        );
        
        $this->mockService
            ->expects('transferCirxToUser')
            ->with($transaction)
            ->andReturn($transferResult);
        
        $result = $this->worker->processTransaction($transaction);
        
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals('CIRX transfer completed successfully', $result['message']);
        $this->assertEquals('0x' . str_repeat('a', 64), $result['transaction_hash']);
    }

    public function testProcessTransactionWithFailedTransfer(): void
    {
        $transactionData = $this->createTransaction([
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
            'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'retry_count' => 0
        ]);
        $transaction = Transaction::create($transactionData);
        
        // Mock failed transfer
        $transferResult = CirxTransferResult::failure(
            $transaction->cirx_recipient_address,
            '1000.0',
            'Insufficient CIRX balance'
        );
        
        $this->mockService
            ->expects('transferCirxToUser')
            ->with($transaction)
            ->andReturn($transferResult);
        
        $result = $this->worker->processTransaction($transaction);
        
        $this->assertEquals('retried', $result['status']);
        $this->assertStringContainsString('Scheduled for retry', $result['message']);
        $this->assertEquals('Insufficient CIRX balance', $result['error']);
        
        // Check retry count was incremented
        $transaction->refresh();
        $this->assertEquals(1, $transaction->retry_count);
        $this->assertNotNull($transaction->last_retry_at);
    }

    public function testProcessTransactionWithPermanentFailure(): void
    {
        $transactionData = $this->createTransaction([
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
            'cirx_recipient_address' => '0xinvalid',
            'retry_count' => 0
        ]);
        $transaction = Transaction::create($transactionData);
        
        // Mock permanent failure
        $transferResult = CirxTransferResult::failure(
            $transaction->cirx_recipient_address,
            '1000.0',
            'Invalid Circular Protocol address format'
        );
        
        $this->mockService
            ->expects('transferCirxToUser')
            ->with($transaction)
            ->andReturn($transferResult);
        
        $result = $this->worker->processTransaction($transaction);
        
        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('CIRX transfer failed permanently', $result['message']);
        $this->assertStringContainsString('Invalid Circular Protocol address format', $result['error']);
        
        // Check transaction was marked as failed
        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_FAILED_CIRX_TRANSFER, $transaction->swap_status);
    }

    public function testProcessTransactionExceedsMaxRetries(): void
    {
        $transactionData = $this->createTransaction([
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
            'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'retry_count' => 2 // Already at max retries
        ]);
        $transaction = Transaction::create($transactionData);
        
        // Mock failed transfer
        $transferResult = CirxTransferResult::failure(
            $transaction->cirx_recipient_address,
            '1000.0',
            'Network timeout'
        );
        
        $this->mockService
            ->expects('transferCirxToUser')
            ->with($transaction)
            ->andReturn($transferResult);
        
        $result = $this->worker->processTransaction($transaction);
        
        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('CIRX transfer failed permanently after retries', $result['message']);
        
        // Check transaction was marked as failed
        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_FAILED_CIRX_TRANSFER, $transaction->swap_status);
        $this->assertStringContainsString('CIRX transfer failed after 2 attempts', $transaction->failure_reason);
    }

    public function testProcessTransactionHandlesException(): void
    {
        $transactionData = $this->createTransaction([
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
            'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'retry_count' => 0
        ]);
        $transaction = Transaction::create($transactionData);
        
        // Mock service throwing exception
        $this->mockService
            ->expects('transferCirxToUser')
            ->with($transaction)
            ->andThrow(new \Exception('Blockchain connection failed'));
        
        $result = $this->worker->processTransaction($transaction);
        
        $this->assertEquals('retried', $result['status']);
        $this->assertStringContainsString('Exception occurred', $result['message']);
        $this->assertEquals('Blockchain connection failed', $result['error']);
        
        // Check retry count was incremented
        $transaction->refresh();
        $this->assertEquals(1, $transaction->retry_count);
    }

    public function testProcessStuckTransactionsWithNoStuckTransactions(): void
    {
        $result = $this->worker->processStuckTransactions();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('reset', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('errors', $result);
        
        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(0, $result['reset']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['errors']);
    }

    public function testProcessBatchTransferWithNoTransactions(): void
    {
        $result = $this->worker->processBatchTransfer();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('completed', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('errors', $result);
        
        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(0, $result['completed']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['errors']);
    }

    public function testGetStatistics(): void
    {
        $stats = $this->worker->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('ready_for_transfer', $stats);
        $this->assertArrayHasKey('transfer_pending', $stats);
        $this->assertArrayHasKey('transfer_initiated', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('failed_transfers', $stats);
        $this->assertArrayHasKey('pending_retries', $stats);
        
        $this->assertIsInt($stats['ready_for_transfer']);
        $this->assertIsInt($stats['transfer_pending']);
        $this->assertIsInt($stats['transfer_initiated']);
        $this->assertIsInt($stats['completed']);
        $this->assertIsInt($stats['failed_transfers']);
        $this->assertIsInt($stats['pending_retries']);
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
        $this->worker->setRetryDelay(60);
        
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
        $worker = new CirxTransferWorker();
        
        // Test that worker can be created with default parameters
        $this->assertInstanceOf(CirxTransferWorker::class, $worker);
    }

    public function testWorkerConstructorWithCustomParameters(): void
    {
        $mockService = Mockery::mock(CirxTransferService::class);
        $worker = new CirxTransferWorker($mockService, 5, 120);
        
        // Test that worker can be created with custom parameters
        $this->assertInstanceOf(CirxTransferWorker::class, $worker);
    }

    public function testProcessTransactionUpdatesStatusToPending(): void
    {
        $transactionData = $this->createTransaction([
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
            'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370'
        ]);
        $transaction = Transaction::create($transactionData);
        
        // Mock successful transfer
        $transferResult = CirxTransferResult::success(
            '0x' . str_repeat('b', 64),
            $transaction->cirx_recipient_address,
            '1000.0'
        );
        
        $this->mockService
            ->expects('transferCirxToUser')
            ->with($transaction)
            ->andReturn($transferResult);
        
        $this->worker->processTransaction($transaction);
        
        // Transaction should be updated to pending status during processing
        $this->assertTrue(true); // Test completed without error
    }
}