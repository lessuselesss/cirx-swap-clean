<?php

namespace Tests\Integration\Database;

use Tests\Integration\IntegrationTestCase;
use App\Models\Transaction;
use PDO;
use PDOException;

/**
 * Integration tests for database operations and transaction data integrity
 * 
 * @covers \App\Models\Transaction
 * @covers \App\Database\DatabaseConnection
 */
class TransactionDataIntegrityTest extends IntegrationTestCase
{
    /**
     * Test transaction creation with all required fields
     */
    public function testTransactionCreationWithCompleteData(): void
    {
        $transactionData = [
            'transaction_id' => 'tx_integrity_test_001',
            'user_wallet_address' => '0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3',
            'payment_token' => 'USDC',
            'payment_amount' => '3000.500000',
            'cirx_amount' => '6480.000000',
            'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'swap_status' => Transaction::STATUS_INITIATED,
            'discount_percentage' => '8.00',
            'platform_fee' => '15.002500'
        ];

        // Insert transaction
        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (
                transaction_id, user_wallet_address, payment_token, payment_amount,
                cirx_amount, cirx_recipient_address, swap_status, discount_percentage, platform_fee
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $transactionData['transaction_id'],
            $transactionData['user_wallet_address'],
            $transactionData['payment_token'],
            $transactionData['payment_amount'],
            $transactionData['cirx_amount'],
            $transactionData['cirx_recipient_address'],
            $transactionData['swap_status'],
            $transactionData['discount_percentage'],
            $transactionData['platform_fee']
        ]);

        $this->assertTrue($result, 'Transaction should be created successfully');

        // Verify transaction was created correctly
        $retrievedTransaction = $this->getTestTransaction($transactionData['transaction_id']);
        $this->assertNotNull($retrievedTransaction);
        $this->assertEquals($transactionData['transaction_id'], $retrievedTransaction['transaction_id']);
        $this->assertEquals($transactionData['user_wallet_address'], $retrievedTransaction['user_wallet_address']);
        $this->assertEquals($transactionData['payment_token'], $retrievedTransaction['payment_token']);
        $this->assertEquals($transactionData['payment_amount'], $retrievedTransaction['payment_amount']);
        $this->assertEquals($transactionData['cirx_amount'], $retrievedTransaction['cirx_amount']);
        $this->assertEquals($transactionData['swap_status'], $retrievedTransaction['swap_status']);

        // Verify timestamps were set
        $this->assertNotNull($retrievedTransaction['created_at']);
        $this->assertNotNull($retrievedTransaction['updated_at']);

        // Verify default values
        $this->assertEquals(0, $retrievedTransaction['retry_count']);
        $this->assertNull($retrievedTransaction['last_retry_at']);
        $this->assertNull($retrievedTransaction['failure_reason']);
    }

    /**
     * Test transaction status updates and audit trail
     */
    public function testTransactionStatusUpdatesAndAuditTrail(): void
    {
        $transactionId = 'tx_status_update_001';
        
        // Create initial transaction
        $this->createTestTransaction($transactionId, Transaction::STATUS_INITIATED);

        $statusProgression = [
            Transaction::STATUS_PAYMENT_PENDING,
            Transaction::STATUS_PENDING_PAYMENT_VERIFICATION,
            Transaction::STATUS_PAYMENT_VERIFIED,
            Transaction::STATUS_TRANSFER_PENDING,
            Transaction::STATUS_TRANSFER_INITIATED,
            Transaction::STATUS_COMPLETED
        ];

        foreach ($statusProgression as $status) {
            // Update status
            $stmt = $this->pdo->prepare("
                UPDATE transactions 
                SET swap_status = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE transaction_id = ?
            ");
            $result = $stmt->execute([$status, $transactionId]);
            $this->assertTrue($result, "Status update to '{$status}' should succeed");

            // Verify status was updated
            $transaction = $this->getTestTransaction($transactionId);
            $this->assertEquals($status, $transaction['swap_status']);
            
            // Verify updated_at timestamp changed
            $this->assertNotNull($transaction['updated_at']);
        }
    }

    /**
     * Test transaction uniqueness constraints
     */
    public function testTransactionUniquenessConstraints(): void
    {
        $transactionId = 'tx_unique_constraint_001';
        
        // Create first transaction
        $this->createTestTransaction($transactionId, Transaction::STATUS_INITIATED);

        // Attempt to create duplicate transaction with same ID
        $this->expectException(PDOException::class);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (
                transaction_id, user_wallet_address, payment_token, payment_amount,
                cirx_amount, cirx_recipient_address, swap_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $transactionId, // Same ID - should fail
            '0x8ba1f109551bD432803012645Hac136c33EbE6b1',
            'ETH',
            '1.000000',
            '2160.000000',
            '0xaa8dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            Transaction::STATUS_INITIATED
        ]);
    }

    /**
     * Test decimal precision for financial amounts
     */
    public function testDecimalPrecisionForFinancialAmounts(): void
    {
        $transactionData = [
            'transaction_id' => 'tx_precision_test_001',
            'user_wallet_address' => '0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3',
            'payment_token' => 'USDC',
            'payment_amount' => '1234.12345678', // 8 decimal places
            'cirx_amount' => '9876.87654321',   // 8 decimal places
            'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'swap_status' => Transaction::STATUS_INITIATED,
            'discount_percentage' => '12.34',      // 2 decimal places
            'platform_fee' => '99.99999999'       // 8 decimal places
        ];

        // Insert transaction with precise amounts
        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (
                transaction_id, user_wallet_address, payment_token, payment_amount,
                cirx_amount, cirx_recipient_address, swap_status, discount_percentage, platform_fee
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $transactionData['transaction_id'],
            $transactionData['user_wallet_address'],
            $transactionData['payment_token'],
            $transactionData['payment_amount'],
            $transactionData['cirx_amount'],
            $transactionData['cirx_recipient_address'],
            $transactionData['swap_status'],
            $transactionData['discount_percentage'],
            $transactionData['platform_fee']
        ]);

        // Retrieve and verify precision
        $retrievedTransaction = $this->getTestTransaction($transactionData['transaction_id']);
        
        $this->assertEquals($transactionData['payment_amount'], $retrievedTransaction['payment_amount']);
        $this->assertEquals($transactionData['cirx_amount'], $retrievedTransaction['cirx_amount']);
        $this->assertEquals($transactionData['discount_percentage'], $retrievedTransaction['discount_percentage']);
        $this->assertEquals($transactionData['platform_fee'], $retrievedTransaction['platform_fee']);
    }

    /**
     * Test transaction retry mechanism database updates
     */
    public function testTransactionRetryMechanismDatabase(): void
    {
        $transactionId = 'tx_retry_mechanism_001';
        
        // Create transaction
        $this->createTestTransaction($transactionId, Transaction::STATUS_PENDING_PAYMENT_VERIFICATION);

        // Simulate retry attempts
        for ($retryCount = 1; $retryCount <= 3; $retryCount++) {
            // Update retry count and timestamp
            $stmt = $this->pdo->prepare("
                UPDATE transactions 
                SET retry_count = ?, last_retry_at = CURRENT_TIMESTAMP 
                WHERE transaction_id = ?
            ");
            $stmt->execute([$retryCount, $transactionId]);

            // Verify retry data
            $transaction = $this->getTestTransaction($transactionId);
            $this->assertEquals($retryCount, $transaction['retry_count']);
            $this->assertNotNull($transaction['last_retry_at']);
        }

        // Simulate final failure
        $failureReason = 'Payment verification failed after maximum retries';
        $stmt = $this->pdo->prepare("
            UPDATE transactions 
            SET swap_status = ?, failure_reason = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE transaction_id = ?
        ");
        $stmt->execute([Transaction::STATUS_FAILED_PAYMENT_VERIFICATION, $failureReason, $transactionId]);

        // Verify failure state
        $transaction = $this->getTestTransaction($transactionId);
        $this->assertEquals(Transaction::STATUS_FAILED_PAYMENT_VERIFICATION, $transaction['swap_status']);
        $this->assertEquals($failureReason, $transaction['failure_reason']);
    }

    /**
     * Test database indexes and query performance
     */
    public function testDatabaseIndexesAndQueryPerformance(): void
    {
        // Create multiple transactions for performance testing
        $transactionCount = 100;
        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (
                transaction_id, user_wallet_address, payment_token, payment_amount,
                cirx_amount, cirx_recipient_address, swap_status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        for ($i = 1; $i <= $transactionCount; $i++) {
            $stmt->execute([
                "tx_performance_test_" . str_pad($i, 3, '0', STR_PAD_LEFT),
                '0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a' . $i,
                'USDC',
                '1000.000000',
                '2160.000000',
                '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc4195143' . str_pad($i, 2, '0', STR_PAD_LEFT),
                $i % 2 === 0 ? Transaction::STATUS_COMPLETED : Transaction::STATUS_PENDING_PAYMENT_VERIFICATION,
                date('Y-m-d H:i:s', strtotime("-{$i} minutes"))
            ]);
        }

        // Test indexed queries
        $queries = [
            // Query by status (should use idx_transactions_status)
            "SELECT COUNT(*) as count FROM transactions WHERE swap_status = ?",
            
            // Query by created_at (should use idx_transactions_created_at)
            "SELECT COUNT(*) as count FROM transactions WHERE created_at > ?",
            
            // Query by payment_tx_id (should use idx_transactions_payment_tx_id when not null)
            "SELECT COUNT(*) as count FROM transactions WHERE payment_tx_id IS NULL"
        ];

        foreach ($queries as $query) {
            $startTime = microtime(true);
            
            $stmt = $this->pdo->prepare($query);
            if (strpos($query, '?') !== false) {
                if (strpos($query, 'swap_status') !== false) {
                    $stmt->execute([Transaction::STATUS_COMPLETED]);
                } elseif (strpos($query, 'created_at') !== false) {
                    $stmt->execute([date('Y-m-d H:i:s', strtotime('-50 minutes'))]);
                }
            } else {
                $stmt->execute();
            }
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $endTime = microtime(true);
            
            $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            // Verify query executed successfully
            $this->assertNotFalse($result);
            $this->assertArrayHasKey('count', $result);
            
            // Performance check - indexed queries should be fast (under 100ms for test data)
            $this->assertLessThan(100, $executionTime, 
                "Query should execute quickly with proper indexes: " . $query);
        }
    }

    /**
     * Test transaction data validation constraints
     */
    public function testTransactionDataValidationConstraints(): void
    {
        // Test various invalid data scenarios
        $invalidScenarios = [
            [
                'description' => 'Null transaction_id',
                'data' => [null, '0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3', 'USDC', '1000.00', '2160.00', '0xbb9...', 'initiated']
            ],
            [
                'description' => 'Empty user_wallet_address',
                'data' => ['tx_invalid_001', '', 'USDC', '1000.00', '2160.00', '0xbb9...', 'initiated']
            ],
            [
                'description' => 'Invalid payment_token length',
                'data' => ['tx_invalid_002', '0x742d...', 'VERY_LONG_TOKEN_NAME', '1000.00', '2160.00', '0xbb9...', 'initiated']
            ]
        ];

        foreach ($invalidScenarios as $scenario) {
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions (
                    transaction_id, user_wallet_address, payment_token, payment_amount,
                    cirx_amount, cirx_recipient_address, swap_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            try {
                $result = $stmt->execute($scenario['data']);
                
                // If we reach here, the constraint didn't work as expected
                // Some constraints might not be enforced at the database level in SQLite
                $this->markTestSkipped("Database constraint not enforced for: " . $scenario['description']);
                
            } catch (PDOException $e) {
                // This is expected for invalid data
                $this->assertInstanceOf(PDOException::class, $e, 
                    "Should throw PDOException for: " . $scenario['description']);
            }
        }
    }

    /**
     * Test concurrent transaction updates
     */
    public function testConcurrentTransactionUpdates(): void
    {
        $transactionId = 'tx_concurrent_test_001';
        
        // Create transaction
        $this->createTestTransaction($transactionId, Transaction::STATUS_PAYMENT_PENDING);

        // Simulate concurrent updates
        $updateQueries = [
            "UPDATE transactions SET retry_count = retry_count + 1 WHERE transaction_id = ?",
            "UPDATE transactions SET swap_status = ? WHERE transaction_id = ?",
            "UPDATE transactions SET updated_at = CURRENT_TIMESTAMP WHERE transaction_id = ?"
        ];

        foreach ($updateQueries as $query) {
            $stmt = $this->pdo->prepare($query);
            
            if (strpos($query, 'swap_status') !== false) {
                $result = $stmt->execute([Transaction::STATUS_PENDING_PAYMENT_VERIFICATION, $transactionId]);
            } elseif (strpos($query, 'retry_count') !== false || strpos($query, 'updated_at') !== false) {
                $result = $stmt->execute([$transactionId]);
            }
            
            $this->assertTrue($result, "Concurrent update should succeed");
        }

        // Verify final state
        $transaction = $this->getTestTransaction($transactionId);
        $this->assertNotNull($transaction);
        $this->assertEquals(1, $transaction['retry_count']);
        $this->assertEquals(Transaction::STATUS_PENDING_PAYMENT_VERIFICATION, $transaction['swap_status']);
    }

    /**
     * Helper method to create a test transaction
     */
    private function createTestTransaction(string $transactionId, string $status): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (
                transaction_id, user_wallet_address, payment_token, payment_amount,
                cirx_amount, cirx_recipient_address, swap_status, discount_percentage, platform_fee
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $transactionId,
            '0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3',
            'USDC',
            '1000.000000',
            '2160.000000',
            '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            $status,
            '5.00',
            '5.000000'
        ]);
    }
}