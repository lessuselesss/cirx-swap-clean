<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Transaction Model for PostgreSQL/Supabase
 * 
 * This model is optimized for PostgreSQL with proper row-level locking
 * using FOR UPDATE NOWAIT for concurrent worker processing.
 */
class Transaction extends Model
{
    protected $table = 'transactions';
    
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'payment_tx_id',
        'payment_chain',
        'sender_address',
        'cirx_recipient_address',
        'amount_paid',
        'payment_token',
        'swap_status',
        'cirx_transfer_tx_id',
        'failure_reason',
        'retry_count',
        'last_retry_at',
        'recovery_attempts',
        'last_recovery_at',
        'is_test_transaction',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:18',
        'retry_count' => 'integer',
        'recovery_attempts' => 'integer',
        'is_test_transaction' => 'boolean',
        'last_retry_at' => 'datetime',
        'last_recovery_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING_PAYMENT_VERIFICATION = 'pending_payment_verification';
    public const STATUS_PAYMENT_VERIFIED = 'payment_verified';
    public const STATUS_CIRX_TRANSFER_PENDING = 'cirx_transfer_pending';
    public const STATUS_CIRX_TRANSFER_INITIATED = 'cirx_transfer_initiated';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED_PAYMENT_VERIFICATION = 'failed_payment_verification';
    public const STATUS_FAILED_CIRX_TRANSFER = 'failed_cirx_transfer';

    /**
     * Lock a transaction for exclusive processing using PostgreSQL FOR UPDATE NOWAIT
     * 
     * @param string $transactionId The transaction ID to lock
     * @return Transaction|null Returns locked transaction or null if already locked
     */
    public static function lockForProcessing(string $transactionId): ?Transaction
    {
        try {
            $connection = self::getConnectionResolver()->connection();
            return $connection->transaction(function () use ($transactionId) {
                // Use PostgreSQL FOR UPDATE NOWAIT for immediate lock acquisition
                $transaction = self::where('id', $transactionId)
                    ->lockForUpdate('nowait')
                    ->first();
                
                if (!$transaction) {
                    return null;
                }
                
                // Mark that this transaction is locked
                $transaction->_isLocked = true;
                return $transaction;
            });
            
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle PostgreSQL lock conflicts
            if (str_contains($e->getMessage(), 'could not obtain lock') ||
                str_contains($e->getMessage(), 'lock not available') ||
                $e->getCode() === '55P03') {
                // Lock acquisition failed - another process is working on this transaction
                return null;
            }
            
            throw $e;
        }
    }

    /**
     * Lock multiple transactions for batch processing
     * 
     * @param array $transactionIds Array of transaction IDs
     * @return array Array of successfully locked transactions
     */
    public static function lockMultipleForProcessing(array $transactionIds): array
    {
        if (empty($transactionIds)) {
            return [];
        }

        try {
            $connection = self::getConnectionResolver()->connection();
            return $connection->transaction(function () use ($transactionIds) {
                $transactions = self::whereIn('id', $transactionIds)
                    ->orderBy('id') // Consistent ordering to prevent deadlocks
                    ->lockForUpdate('nowait')
                    ->get();
                
                // Mark all as locked
                foreach ($transactions as $transaction) {
                    $transaction->_isLocked = true;
                }
                
                return $transactions->toArray();
            });
            
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'could not obtain lock') ||
                $e->getCode() === '55P03') {
                // Return empty array if any lock failed
                return [];
            }
            
            throw $e;
        }
    }

    /**
     * Lock with timeout (PostgreSQL only)
     * 
     * @param string $transactionId Transaction ID to lock
     * @param int $timeoutMs Timeout in milliseconds (default 5 seconds)
     * @return Transaction|null
     */
    public static function lockForProcessingWithTimeout(string $transactionId, int $timeoutMs = 5000): ?Transaction
    {
        try {
            $connection = self::getConnectionResolver()->connection();
            return $connection->transaction(function () use ($transactionId, $timeoutMs, $connection) {
                // Set lock timeout for this transaction
                $connection->statement("SET lock_timeout = '{$timeoutMs}ms'");
                
                $transaction = self::where('id', $transactionId)
                    ->lockForUpdate() // Use timeout instead of nowait
                    ->first();
                
                if ($transaction) {
                    $transaction->_isLocked = true;
                }
                
                return $transaction;
            });
            
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'timeout') ||
                str_contains($e->getMessage(), 'lock not available')) {
                return null;
            }
            
            throw $e;
        } finally {
            // Reset lock timeout to default
            try {
                $connection = self::getConnectionResolver()->connection();
                $connection->statement("SET lock_timeout = DEFAULT");
            } catch (\Exception $e) {
                // Ignore errors when resetting timeout
            }
        }
    }

    // Status management methods
    public function markPending(): void
    {
        $this->update(['swap_status' => self::STATUS_PENDING_PAYMENT_VERIFICATION]);
    }

    public function markPaymentVerified(): void
    {
        $this->update(['swap_status' => self::STATUS_PAYMENT_VERIFIED]);
    }

    public function markCirxTransferPending(): void
    {
        $this->update(['swap_status' => self::STATUS_CIRX_TRANSFER_PENDING]);
    }

    public function markCirxTransferInitiated(string $transferTxId): void
    {
        $this->update([
            'swap_status' => self::STATUS_CIRX_TRANSFER_INITIATED,
            'cirx_transfer_tx_id' => $transferTxId
        ]);
    }

    public function markCompleted(string $transferTxId): void
    {
        $this->update([
            'swap_status' => self::STATUS_COMPLETED,
            'cirx_transfer_tx_id' => $transferTxId
        ]);
    }

    public function markFailed(string $reason, string $status = self::STATUS_FAILED_PAYMENT_VERIFICATION): void
    {
        $this->update([
            'swap_status' => $status,
            'failure_reason' => $reason
        ]);
    }

    // Query scopes for common filters
    public function scopePending(Builder $query): Builder
    {
        return $query->where('swap_status', self::STATUS_PENDING_PAYMENT_VERIFICATION);
    }

    public function scopePaymentVerified(Builder $query): Builder
    {
        return $query->where('swap_status', self::STATUS_PAYMENT_VERIFIED);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('swap_status', self::STATUS_COMPLETED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('swap_status', [
            self::STATUS_FAILED_PAYMENT_VERIFICATION,
            self::STATUS_FAILED_CIRX_TRANSFER
        ]);
    }

    public function scopeReadyForRetry(Builder $query): Builder
    {
        $fifteenMinutesAgo = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        return $query->where('swap_status', self::STATUS_PAYMENT_VERIFIED)
            ->where(function ($q) use ($fifteenMinutesAgo) {
                $q->whereNull('last_retry_at')
                  ->orWhere('last_retry_at', '<', $fifteenMinutesAgo);
            });
    }

    public function scopeStuckTransactions(Builder $query): Builder
    {
        $tenMinutesAgo = date('Y-m-d H:i:s', strtotime('-10 minutes'));
        return $query->where('swap_status', self::STATUS_CIRX_TRANSFER_PENDING)
            ->where('updated_at', '<', $tenMinutesAgo);
    }

    // Statistics and monitoring
    public static function getStatistics(): array
    {
        return [
            'total' => self::count(),
            'pending' => self::pending()->count(),
            'payment_verified' => self::paymentVerified()->count(),
            'completed' => self::completed()->count(),
            'failed' => self::failed()->count(),
            'ready_for_retry' => self::readyForRetry()->count(),
            'stuck' => self::stuckTransactions()->count(),
        ];
    }

    /**
     * Get PostgreSQL lock statistics for monitoring
     */
    public static function getLockStatistics(): array
    {
        try {
            // Use Eloquent's connection directly instead of DB facade
            $connection = self::getConnectionResolver()->connection();
            
            $lockStats = $connection->select("
                SELECT 
                    mode,
                    COUNT(*) as lock_count,
                    COUNT(DISTINCT pid) as process_count
                FROM pg_locks 
                WHERE relation = (
                    SELECT oid FROM pg_class WHERE relname = 'transactions'
                )
                GROUP BY mode
                ORDER BY lock_count DESC
            ");

            $blockedStats = $connection->select("
                SELECT 
                    COUNT(*) as blocked_count,
                    COUNT(DISTINCT pid) as blocked_processes
                FROM pg_stat_activity 
                WHERE wait_event_type = 'Lock' 
                AND query LIKE '%transactions%'
            ");

            return [
                'database_type' => 'postgresql',
                'lock_modes' => $lockStats ?: [],
                'blocked_queries' => isset($blockedStats[0]) ? $blockedStats[0]->blocked_count : 0,
                'blocked_processes' => isset($blockedStats[0]) ? $blockedStats[0]->blocked_processes : 0,
                'timestamp' => date('c')
            ];

        } catch (\Exception $e) {
            return [
                'database_type' => 'postgresql',
                'error' => 'Could not retrieve lock statistics: ' . $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    // Helper methods
    public function isCompleted(): bool
    {
        return $this->swap_status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return in_array($this->swap_status, [
            self::STATUS_FAILED_PAYMENT_VERIFICATION,
            self::STATUS_FAILED_CIRX_TRANSFER
        ]);
    }

    public function isReadyForProcessing(): bool
    {
        return $this->swap_status === self::STATUS_PAYMENT_VERIFIED;
    }

    public function shouldRetry(): bool
    {
        return $this->isReadyForProcessing() && 
               ($this->retry_count ?? 0) < 3 &&
               (!$this->last_retry_at || $this->last_retry_at->addMinutes(15)->isPast());
    }
}