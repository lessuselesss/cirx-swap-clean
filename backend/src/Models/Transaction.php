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
    const STATUS_PAYMENT_PENDING = 'payment_pending';
    const STATUS_PENDING_PAYMENT_VERIFICATION = 'pending_payment_verification';
    const STATUS_PAYMENT_VERIFIED = 'payment_verified';
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
        'swap_status' => 'required|in:' . self::STATUS_PENDING_PAYMENT_VERIFICATION . ',' .
                        self::STATUS_PAYMENT_VERIFIED . ',' .
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
     * Scope queries to pending payment verification
     */
    public function scopePendingPaymentVerification(Builder $query): Builder
    {
        return $query->where('swap_status', self::STATUS_PENDING_PAYMENT_VERIFICATION);
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
            self::STATUS_PENDING_PAYMENT_VERIFICATION,
            self::STATUS_PAYMENT_VERIFIED,
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
     */
    public function markCompleted(): bool
    {
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
     * Get human-readable status
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->swap_status) {
            self::STATUS_PENDING_PAYMENT_VERIFICATION => 'Payment Verification Pending',
            self::STATUS_PAYMENT_VERIFIED => 'Payment Verified',
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