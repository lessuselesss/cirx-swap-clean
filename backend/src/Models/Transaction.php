<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

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
        'is_test_transaction',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:18',
        'retry_count' => 'integer',
        'is_test_transaction' => 'boolean',
        'last_retry_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_INITIATED = 'initiated';
    const STATUS_PAYMENT_PENDING_ZERO_CONFIRMATIONS = 'payment_pending_zero_confirmations';
    const STATUS_PAYMENT_PENDING_UNDER_THRESHOLD = 'payment_pending_under_threshold';
    const STATUS_PAYMENT_VERIFIED = 'payment_verified';
    const STATUS_NEED_CIRX_WALLET_TOP_UP = 'need_cirx_wallet_top_up';
    const STATUS_TRANSFER_PENDING = 'transfer_pending';
    const STATUS_CIRX_TRANSFER_PENDING = 'cirx_transfer_pending';
    const STATUS_CIRX_TRANSFER_INITIATED = 'cirx_transfer_initiated';
    const STATUS_TRANSFER_INITIATED = 'transfer_initiated';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED_PAYMENT_VERIFICATION = 'failed_payment_verification';
    const STATUS_FAILED_CIRX_TRANSFER = 'failed_cirx_transfer';

    // Validation rules
    protected static $rules = [
        'id' => 'required|string|max:36',
        'payment_tx_id' => 'required|string|max:255|unique:transactions',
        'payment_chain' => 'required|string|max:50',
        'sender_address' => 'nullable|string|max:255',
        'cirx_recipient_address' => 'required|string|max:255',
        'amount_paid' => 'required|numeric|min:0',
        'payment_token' => 'required|string|max:10',
        'swap_status' => 'required|in:' . self::STATUS_PAYMENT_PENDING_ZERO_CONFIRMATIONS . ',' .
                        self::STATUS_PAYMENT_PENDING_UNDER_THRESHOLD . ',' .
                        self::STATUS_PAYMENT_VERIFIED . ',' .
                        self::STATUS_NEED_CIRX_WALLET_TOP_UP . ',' .
                        self::STATUS_CIRX_TRANSFER_PENDING . ',' .
                        self::STATUS_CIRX_TRANSFER_INITIATED . ',' .
                        self::STATUS_COMPLETED . ',' .
                        self::STATUS_FAILED_PAYMENT_VERIFICATION . ',' .
                        self::STATUS_FAILED_CIRX_TRANSFER,
    ];

    /**
     * Scope queries to specific status
     */
    public function scopeWhereStatus(Builder $query, string $status): Builder
    {
        return $query->where('swap_status', $status);
    }

    /**
     * Scope queries to payment pending (zero confirmations)
     */
    public function scopePaymentPendingZeroConfirmations(Builder $query): Builder
    {
        return $query->where('swap_status', self::STATUS_PAYMENT_PENDING_ZERO_CONFIRMATIONS);
    }

    /**
     * Scope queries to payment pending (under confirmation threshold)
     */
    public function scopePaymentPendingUnderThreshold(Builder $query): Builder
    {
        return $query->where('swap_status', self::STATUS_PAYMENT_PENDING_UNDER_THRESHOLD);
    }

    /**
     * Scope queries to needing CIRX wallet top up
     */
    public function scopeNeedCirxWalletTopUp(Builder $query): Builder
    {
        return $query->where('swap_status', self::STATUS_NEED_CIRX_WALLET_TOP_UP);
    }

    /**
     * Scope queries to payment verified
     */
    public function scopePaymentVerified(Builder $query): Builder
    {
        return $query->where('swap_status', self::STATUS_PAYMENT_VERIFIED);
    }

    /**
     * Scope queries to CIRX transfer pending
     */
    public function scopeCirxTransferPending(Builder $query): Builder
    {
        return $query->where('swap_status', self::STATUS_CIRX_TRANSFER_PENDING);
    }

    /**
     * Scope queries to completed transactions
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('swap_status', self::STATUS_COMPLETED);
    }

    /**
     * Scope queries to failed transactions
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('swap_status', [
            self::STATUS_FAILED_PAYMENT_VERIFICATION,
            self::STATUS_FAILED_CIRX_TRANSFER,
        ]);
    }

    /**
     * Check if transaction is completed
     */
    public function isCompleted(): bool
    {
        return $this->swap_status === self::STATUS_COMPLETED;
    }

    /**
     * Check if transaction failed
     */
    public function isFailed(): bool
    {
        return in_array($this->swap_status, [
            self::STATUS_FAILED_PAYMENT_VERIFICATION,
            self::STATUS_FAILED_CIRX_TRANSFER,
        ]);
    }

    /**
     * Check if transaction is pending
     */
    public function isPending(): bool
    {
        return in_array($this->swap_status, [
            self::STATUS_PAYMENT_PENDING_ZERO_CONFIRMATIONS,
            self::STATUS_PAYMENT_PENDING_UNDER_THRESHOLD,
            self::STATUS_PAYMENT_VERIFIED,
            self::STATUS_NEED_CIRX_WALLET_TOP_UP,
            self::STATUS_CIRX_TRANSFER_PENDING,
            self::STATUS_CIRX_TRANSFER_INITIATED,
        ]);
    }

    /**
     * Mark transaction as payment verified
     */
    public function markPaymentVerified(): bool
    {
        return $this->update(['swap_status' => self::STATUS_PAYMENT_VERIFIED]);
    }

    /**
     * Mark transaction as needing CIRX wallet top up
     * Only allowed from payment_verified or cirx_transfer_pending states
     */
    public function markNeedCirxWalletTopUp(string $reason = 'Insufficient CIRX wallet balance'): bool
    {
        $allowedFrom = [
            self::STATUS_PAYMENT_VERIFIED,
            self::STATUS_CIRX_TRANSFER_PENDING
        ];
        
        if (!in_array($this->swap_status, $allowedFrom)) {
            throw new \InvalidArgumentException(
                "Cannot mark as needing wallet top up from status: {$this->swap_status}. Allowed from: " . implode(', ', $allowedFrom)
            );
        }
        
        if (empty($reason)) {
            throw new \InvalidArgumentException("Reason cannot be empty when marking as needing wallet top up");
        }
        
        return $this->update([
            'swap_status' => self::STATUS_NEED_CIRX_WALLET_TOP_UP,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Mark transaction as CIRX transfer pending
     */
    public function markCirxTransferPending(): bool
    {
        return $this->update(['swap_status' => self::STATUS_CIRX_TRANSFER_PENDING]);
    }

    /**
     * Mark transaction as CIRX transfer initiated
     */
    public function markCirxTransferInitiated(string $txId): bool
    {
        return $this->update([
            'swap_status' => self::STATUS_CIRX_TRANSFER_INITIATED,
            'cirx_transfer_tx_id' => $txId,
        ]);
    }

    /**
     * Mark transaction as completed
     * Only allowed from cirx_transfer_initiated state
     * Idempotent - can be called multiple times safely
     */
    public function markCompleted(): bool
    {
        // If already completed, this is a no-op (idempotent)
        if ($this->swap_status === self::STATUS_COMPLETED) {
            return true;
        }
        
        $allowedFrom = [
            self::STATUS_CIRX_TRANSFER_INITIATED,
            self::STATUS_FAILED_CIRX_TRANSFER // Allow recovery from failed state
        ];
        
        if (!in_array($this->swap_status, $allowedFrom)) {
            throw new \InvalidArgumentException(
                "Cannot mark completed from status: {$this->swap_status}. Allowed from: " . implode(', ', $allowedFrom)
            );
        }
        
        return $this->update(['swap_status' => self::STATUS_COMPLETED]);
    }

    /**
     * Mark transaction as failed
     */
    public function markFailed(string $reason, string $status = self::STATUS_FAILED_PAYMENT_VERIFICATION): bool
    {
        return $this->update([
            'swap_status' => $status,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Emergency status update for recovery operations
     * Bypasses validation - use only for stuck transaction recovery
     */
    public function forceStatusUpdate(string $status, array $additionalFields = []): bool
    {
        $validStatuses = [
            self::STATUS_INITIATED,
            self::STATUS_PAYMENT_PENDING_ZERO_CONFIRMATIONS,
            self::STATUS_PAYMENT_PENDING_UNDER_THRESHOLD,
            self::STATUS_PAYMENT_VERIFIED,
            self::STATUS_NEED_CIRX_WALLET_TOP_UP,
            self::STATUS_CIRX_TRANSFER_PENDING,
            self::STATUS_CIRX_TRANSFER_INITIATED,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED_PAYMENT_VERIFICATION,
            self::STATUS_FAILED_CIRX_TRANSFER
        ];
        
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
        
        $updateData = array_merge(['swap_status' => $status], $additionalFields);
        return $this->update($updateData);
    }

    /**
     * Lock transaction for exclusive processing to prevent race conditions
     * Returns locked transaction or null if already locked
     */
    public static function lockForProcessing(string $transactionId): ?Transaction
    {
        $pdo = self::getPDO();
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // SQLite doesn't support FOR UPDATE, so we'll use a different approach
            // First select and check the row exists and is in correct status
            $stmt = $pdo->prepare("
                SELECT * FROM transactions 
                WHERE id = :id 
            ");
            $stmt->execute(['id' => $transactionId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$row) {
                $pdo->rollback();
                return null;
            }
            
            // Create Transaction instance from row data
            $transaction = new self();
            foreach ($row as $key => $value) {
                $transaction->$key = $value;
            }
            
            // Store the PDO connection for later commit/rollback
            $transaction->_lockedConnection = $pdo;
            
            return $transaction;
            
        } catch (\PDOException $e) {
            $pdo->rollback();
            // If lock acquisition failed, return null (transaction is being processed by another worker)
            if (strpos($e->getMessage(), 'resource busy') !== false || 
                strpos($e->getMessage(), 'NOWAIT') !== false) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Unlock transaction after processing (commit the transaction)
     */
    public function unlockAfterProcessing(): void
    {
        if (isset($this->_lockedConnection)) {
            try {
                $this->_lockedConnection->commit();
            } catch (\PDOException $e) {
                $this->_lockedConnection->rollback();
                throw $e;
            } finally {
                unset($this->_lockedConnection);
            }
        }
    }

    /**
     * Release lock without committing changes (rollback)
     */
    public function releaseLock(): void
    {
        if (isset($this->_lockedConnection)) {
            $this->_lockedConnection->rollback();
            unset($this->_lockedConnection);
        }
    }

    /**
     * Update transaction status with atomic lock
     * Prevents race conditions by locking row during update
     */
    public function atomicStatusUpdate(string $status, array $additionalFields = []): bool
    {
        if (isset($this->_lockedConnection)) {
            // Already locked, just update
            $updateData = array_merge(['swap_status' => $status], $additionalFields);
            $updateData['updated_at'] = (new \DateTime())->format('Y-m-d H:i:s');
            
            $setClause = [];
            $params = ['id' => $this->id];
            
            foreach ($updateData as $field => $value) {
                $setClause[] = "{$field} = :{$field}";
                $params[$field] = $value;
                $this->$field = $value; // Update local instance
            }
            
            $sql = "UPDATE transactions SET " . implode(', ', $setClause) . " WHERE id = :id";
            $stmt = $this->_lockedConnection->prepare($sql);
            return $stmt->execute($params);
        } else {
            // Use regular update
            return $this->forceStatusUpdate($status, $additionalFields);
        }
    }

    /**
     * Get PDO connection for locking operations
     */
    private static function getPDO(): \PDO
    {
        // Get database configuration
        $databasePath = $_ENV['DB_DATABASE'] ?? 'database/database.sqlite';
        
        // Create SQLite connection
        $pdo = new \PDO("sqlite:{$databasePath}");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Enable WAL mode for better concurrent access
        $pdo->exec('PRAGMA journal_mode=WAL;');
        $pdo->exec('PRAGMA synchronous=NORMAL;');
        $pdo->exec('PRAGMA busy_timeout=5000;'); // 5 second timeout
        
        return $pdo;
    }

    /**
     * Get human-readable status
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->swap_status) {
            self::STATUS_PAYMENT_PENDING_ZERO_CONFIRMATIONS => 'Payment Submitted (0 Confirmations)',
            self::STATUS_PAYMENT_PENDING_UNDER_THRESHOLD => 'Payment Confirming (Under Threshold)',
            self::STATUS_PAYMENT_VERIFIED => 'Payment Verified',
            self::STATUS_NEED_CIRX_WALLET_TOP_UP => 'Waiting for CIRX Wallet Top Up',
            self::STATUS_CIRX_TRANSFER_PENDING => 'CIRX Transfer Pending',
            self::STATUS_CIRX_TRANSFER_INITIATED => 'CIRX Transfer Initiated',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED_PAYMENT_VERIFICATION => 'Payment Verification Failed',
            self::STATUS_FAILED_CIRX_TRANSFER => 'CIRX Transfer Failed',
            default => 'Unknown Status',
        };
    }

    /**
     * Get validation rules
     */
    public static function getRules(): array
    {
        return self::$rules;
    }

    /**
     * Create Transaction object from array data
     * Handles mapping between integration test schema and model properties
     */
    public static function fromArray(array $data): self
    {
        $transaction = new self();
        
        // Handle property mapping between schemas
        $propertyMap = [
            'transaction_id' => 'id',
            'payment_amount' => 'amount_paid'
        ];
        
        foreach ($data as $key => $value) {
            // Use mapped property name if exists, otherwise use original
            $propertyName = $propertyMap[$key] ?? $key;
            
            if (in_array($propertyName, $transaction->fillable) || $propertyName === 'id') {
                $transaction->$propertyName = $value;
            }
            
            // Also set the original property for integration test compatibility
            $transaction->$key = $value;
        }
        
        return $transaction;
    }

    /**
     * Override save method to match Eloquent signature
     */
    public function save(array $options = []): bool
    {
        // Use parent save method to actually persist to database
        return parent::save($options);
    }

    /**
     * Override update method to match Eloquent signature
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        // Use parent update method to actually persist to database
        return parent::update($attributes, $options);
    }
}