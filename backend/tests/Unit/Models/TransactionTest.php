<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @covers \App\Models\Transaction
 */
class TransactionTest extends TestCase
{
    public function test_can_create_transaction()
    {
        $transactionData = $this->createTransaction();
        
        $transaction = Transaction::create($transactionData);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals($transactionData['id'], $transaction->id);
        $this->assertEquals($transactionData['payment_tx_id'], $transaction->payment_tx_id);
        $this->assertEquals($transactionData['payment_chain'], $transaction->payment_chain);
        $this->assertEquals($transactionData['cirx_recipient_address'], $transaction->cirx_recipient_address);
        $this->assertEquals('1.000000000000000000', $transaction->amount_paid);
        $this->assertEquals($transactionData['payment_token'], $transaction->payment_token);
        $this->assertEquals('pending_payment_verification', $transaction->swap_status);
    }

    public function test_can_find_transaction_by_id()
    {
        $transactionData = $this->createTransaction();
        Transaction::create($transactionData);

        $found = Transaction::find($transactionData['id']);

        $this->assertInstanceOf(Transaction::class, $found);
        $this->assertEquals($transactionData['id'], $found->id);
    }

    public function test_can_find_transaction_by_payment_tx_id()
    {
        $transactionData = $this->createTransaction();
        Transaction::create($transactionData);

        $found = Transaction::where('payment_tx_id', $transactionData['payment_tx_id'])->first();

        $this->assertInstanceOf(Transaction::class, $found);
        $this->assertEquals($transactionData['payment_tx_id'], $found->payment_tx_id);
    }

    public function test_can_update_transaction_status()
    {
        $transactionData = $this->createTransaction();
        $transaction = Transaction::create($transactionData);

        $transaction->update(['swap_status' => 'payment_verified']);

        $this->assertEquals('payment_verified', $transaction->fresh()->swap_status);
    }

    public function test_can_set_failure_reason()
    {
        $transactionData = $this->createTransaction();
        $transaction = Transaction::create($transactionData);

        $failureReason = 'Payment amount too low';
        $transaction->update([
            'swap_status' => 'failed_payment_verification',
            'failure_reason' => $failureReason
        ]);

        $updatedTransaction = $transaction->fresh();
        $this->assertEquals('failed_payment_verification', $updatedTransaction->swap_status);
        $this->assertEquals($failureReason, $updatedTransaction->failure_reason);
    }

    public function test_can_set_cirx_transfer_tx_id()
    {
        $transactionData = $this->createTransaction();
        $transaction = Transaction::create($transactionData);

        $cirxTxId = '0x' . bin2hex(random_bytes(32));
        $transaction->update([
            'swap_status' => 'cirx_transfer_initiated',
            'cirx_transfer_tx_id' => $cirxTxId
        ]);

        $updatedTransaction = $transaction->fresh();
        $this->assertEquals('cirx_transfer_initiated', $updatedTransaction->swap_status);
        $this->assertEquals($cirxTxId, $updatedTransaction->cirx_transfer_tx_id);
    }

    public function test_payment_tx_id_is_unique()
    {
        $transactionData1 = $this->createTransaction();
        $transactionData2 = $this->createTransaction([
            'id' => $this->generateUuid(),
            'payment_tx_id' => $transactionData1['payment_tx_id'] // Same payment_tx_id
        ]);

        Transaction::create($transactionData1);

        $this->expectException(\Exception::class);
        Transaction::create($transactionData2);
    }

    public function test_validates_required_fields()
    {
        $this->expectException(\Exception::class);
        
        // Try to create transaction without required fields
        Transaction::create([]);
    }

    public function test_validates_swap_status_enum()
    {
        $transactionData = $this->createTransaction([
            'swap_status' => 'invalid_status'
        ]);

        $this->expectException(\Exception::class);
        Transaction::create($transactionData);
    }

    public function test_can_scope_by_status()
    {
        $pendingData = $this->createTransaction(['swap_status' => 'pending_payment_verification']);
        $verifiedData = $this->createTransaction([
            'id' => $this->generateUuid(),
            'payment_tx_id' => '0x' . bin2hex(random_bytes(32)),
            'swap_status' => 'payment_verified'
        ]);

        Transaction::create($pendingData);
        Transaction::create($verifiedData);

        $pendingTransactions = Transaction::whereStatus('pending_payment_verification')->get();
        $verifiedTransactions = Transaction::whereStatus('payment_verified')->get();

        $this->assertCount(1, $pendingTransactions);
        $this->assertCount(1, $verifiedTransactions);
        $this->assertEquals('pending_payment_verification', $pendingTransactions->first()->swap_status);
        $this->assertEquals('payment_verified', $verifiedTransactions->first()->swap_status);
    }

    public function test_can_check_if_completed()
    {
        $completedData = $this->createTransaction(['swap_status' => 'completed']);
        $pendingData = $this->createTransaction([
            'id' => $this->generateUuid(),
            'payment_tx_id' => '0x' . bin2hex(random_bytes(32)),
            'swap_status' => 'pending_payment_verification'
        ]);

        $completedTransaction = Transaction::create($completedData);
        $pendingTransaction = Transaction::create($pendingData);

        $this->assertTrue($completedTransaction->isCompleted());
        $this->assertFalse($pendingTransaction->isCompleted());
    }

    public function test_can_check_if_failed()
    {
        $failedData = $this->createTransaction(['swap_status' => 'failed_payment_verification']);
        $successData = $this->createTransaction([
            'id' => $this->generateUuid(),
            'payment_tx_id' => '0x' . bin2hex(random_bytes(32)),
            'swap_status' => 'completed'
        ]);

        $failedTransaction = Transaction::create($failedData);
        $successTransaction = Transaction::create($successData);

        $this->assertTrue($failedTransaction->isFailed());
        $this->assertFalse($successTransaction->isFailed());
    }
}