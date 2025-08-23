<?php

namespace App\Workers;

use App\Models\Transaction;
use App\Services\CirxTransferService;
use App\Services\LoggerService;
use Exception;

// No helper functions needed - will use direct DateTime calls

/**
 * Background worker for transferring CIRX tokens to users
 * 
 * This worker processes transactions in "payment_verified" status,
 * transfers CIRX tokens to the user, and updates the transaction status.
 */
class CirxTransferWorker
{
    private CirxTransferService $cirxTransferService;
    private int $maxRetries;
    private int $retryDelay;

    public function __construct(
        CirxTransferService $cirxTransferService = null,
        int $maxRetries = 3,
        int $retryDelay = 60
    ) {
        $this->cirxTransferService = $cirxTransferService ?? new CirxTransferService();
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
    }

    /**
     * Process transactions ready for CIRX transfer
     */
    public function processReadyTransactions(): array
    {
        $results = [
            'processed' => 0,
            'completed' => 0,
            'failed' => 0,
            'retried' => 0,
            'errors' => []
        ];

        try {
            // Get transactions with verified payments ready for CIRX transfer
            $readyTransactions = Transaction::where('swap_status', Transaction::STATUS_PAYMENT_VERIFIED)
                ->orderBy('created_at', 'asc')
                ->take(30) // Process in batches
                ->get();

            foreach ($readyTransactions as $transaction) {
                $result = $this->processTransaction($transaction);
                $results['processed']++;
                
                switch ($result['status']) {
                    case 'completed':
                        $results['completed']++;
                        break;
                    case 'failed':
                        $results['failed']++;
                        break;
                    case 'retried':
                        $results['retried']++;
                        break;
                }
                
                if (isset($result['error'])) {
                    $results['errors'][] = [
                        'transaction_id' => $transaction->id,
                        'error' => $result['error']
                    ];
                }
            }

            // Also process any transactions that need retry
            $retryResults = $this->processRetryTransactions();
            $results['processed'] += $retryResults['processed'];
            $results['completed'] += $retryResults['completed'];
            $results['failed'] += $retryResults['failed'];
            $results['retried'] += $retryResults['retried'];
            $results['errors'] = array_merge($results['errors'], $retryResults['errors']);

            // Automatically recover transactions in inconsistent states
            $recoveryResults = $this->recoverInconsistentTransactions();
            if ($recoveryResults['recovered'] > 0) {
                $results['recovered'] = $recoveryResults['recovered'];
                $results['errors'] = array_merge($results['errors'], $recoveryResults['errors']);
            }

        } catch (Exception $e) {
            $results['errors'][] = [
                'worker_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        return $results;
    }

    /**
     * Process a single transaction for CIRX transfer
     */
    public function processTransaction(Transaction $transaction): array
    {
        try {
            // Validate transaction is ready for transfer BEFORE changing status
            if ($transaction->swap_status !== Transaction::STATUS_PAYMENT_VERIFIED) {
                return [
                    'status' => 'failed',
                    'message' => 'Transaction not in valid state for transfer',
                    'error' => 'Expected payment_verified status, got: ' . $transaction->swap_status
                ];
            }
            
            // Update status to indicate transfer is in progress
            $transaction->swap_status = Transaction::STATUS_CIRX_TRANSFER_PENDING;
            $transaction->save();
            
            $this->logTransactionEvent($transaction, 'transfer_started', 'CIRX transfer initiated');
            
            // Perform the CIRX transfer
            $transferResult = $this->cirxTransferService->transferCirxToUser($transaction);
            
            if ($transferResult->isSuccess()) {
                // Transfer completed successfully
                $this->logTransactionEvent($transaction, 'transfer_completed', "CIRX transfer successful, TX: {$transferResult->getTransactionHash()}");
                
                return [
                    'status' => 'completed',
                    'message' => 'CIRX transfer completed successfully',
                    'transaction_hash' => $transferResult->getTransactionHash()
                ];
            } else {
                // Transfer failed
                return $this->handleTransferFailure($transaction, $transferResult->getErrorMessage());
            }

        } catch (Exception $e) {
            return $this->handleException($transaction, $e);
        }
    }

    /**
     * Handle CIRX transfer failure with retry logic
     */
    private function handleTransferFailure(Transaction $transaction, string $error): array
    {
        $retryCount = $transaction->retry_count ?? 0;
        
        // Check if this is a permanent failure that shouldn't be retried
        if ($this->isPermanentFailure($error)) {
            $transaction->markFailed("CIRX transfer failed permanently: {$error}", Transaction::STATUS_FAILED_CIRX_TRANSFER);
            $this->logTransactionEvent($transaction, 'transfer_failed_permanent', "Permanent failure: {$error}");
            
            return [
                'status' => 'failed',
                'message' => 'CIRX transfer failed permanently',
                'error' => $error
            ];
        }
        
        if ($retryCount < $this->maxRetries) {
            // Increment retry count and schedule for retry
            $transaction->retry_count = $retryCount + 1;
            $transaction->last_retry_at = (new \DateTime())->format('Y-m-d H:i:s');
            $transaction->swap_status = Transaction::STATUS_PAYMENT_VERIFIED; // Reset to verified for retry
            $transaction->save();
            
            $retryNumber = $retryCount + 1;
            $this->logTransactionEvent($transaction, 'transfer_retry', "CIRX transfer failed, retry {$retryNumber}/{$this->maxRetries}: {$error}");
            
            return [
                'status' => 'retried',
                'message' => "Scheduled for retry {$retryNumber}/{$this->maxRetries}",
                'error' => $error
            ];
        } else {
            // Max retries reached - mark as failed
            $transaction->markFailed("CIRX transfer failed after {$this->maxRetries} attempts: {$error}", Transaction::STATUS_FAILED_CIRX_TRANSFER);
            
            $this->logTransactionEvent($transaction, 'transfer_failed', "CIRX transfer failed permanently after retries: {$error}");
            
            return [
                'status' => 'failed',
                'message' => 'CIRX transfer failed permanently after retries',
                'error' => $error
            ];
        }
    }

    /**
     * Handle exceptions during processing
     */
    private function handleException(Transaction $transaction, Exception $e): array
    {
        $retryCount = $transaction->retry_count ?? 0;
        
        if ($retryCount < $this->maxRetries) {
            // Increment retry count for exceptions too
            $transaction->retry_count = $retryCount + 1;
            $transaction->last_retry_at = (new \DateTime())->format('Y-m-d H:i:s');
            $transaction->swap_status = Transaction::STATUS_PAYMENT_VERIFIED; // Reset for retry
            $transaction->save();
            
            $retryNumber = $retryCount + 1;
            $this->logTransactionEvent($transaction, 'worker_exception', "Worker exception, retry {$retryNumber}: {$e->getMessage()}");
            
            return [
                'status' => 'retried',
                'message' => "Exception occurred, scheduled for retry",
                'error' => $e->getMessage()
            ];
        } else {
            // Mark as failed due to exceptions
            $transaction->markFailed("Worker exception after {$this->maxRetries} attempts: {$e->getMessage()}", Transaction::STATUS_FAILED_CIRX_TRANSFER);
            
            $this->logTransactionEvent($transaction, 'worker_failed', "Worker failed permanently: {$e->getMessage()}");
            
            return [
                'status' => 'failed',
                'message' => 'Worker failed permanently due to exceptions',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process transactions that are ready for retry
     */
    public function processRetryTransactions(): array
    {
        $results = [
            'processed' => 0,
            'completed' => 0,
            'failed' => 0,
            'retried' => 0,
            'errors' => []
        ];

        try {
            // Get transactions that are due for retry (waiting period has passed)
            $retryTransactions = Transaction::where('swap_status', Transaction::STATUS_PAYMENT_VERIFIED)
                ->where('retry_count', '>', 0)
                ->where(function($query) {
                    $query->whereNull('last_retry_at')
                          ->orWhere('last_retry_at', '<', (new \DateTime())->sub(new \DateInterval('PT' . $this->retryDelay . 'S'))->format('Y-m-d H:i:s'));
                })
                ->orderBy('last_retry_at', 'asc')
                ->take(15)
                ->get();

            foreach ($retryTransactions as $transaction) {
                $result = $this->processTransaction($transaction);
                $results['processed']++;
                
                switch ($result['status']) {
                    case 'completed':
                        $results['completed']++;
                        break;
                    case 'failed':
                        $results['failed']++;
                        break;
                    case 'retried':
                        $results['retried']++;
                        break;
                }
                
                if (isset($result['error'])) {
                    $results['errors'][] = [
                        'transaction_id' => $transaction->id,
                        'error' => $result['error']
                    ];
                }
            }

        } catch (Exception $e) {
            $results['errors'][] = [
                'worker_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        return $results;
    }

    /**
     * Process stuck transactions that have been pending too long
     */
    public function processStuckTransactions(): array
    {
        $results = [
            'processed' => 0,
            'reset' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            // Find transactions stuck in pending state for more than 10 minutes
            $stuckTransactions = Transaction::where('swap_status', Transaction::STATUS_CIRX_TRANSFER_PENDING)
                ->where('updated_at', '<', (new \DateTime())->sub(new \DateInterval('PT10M'))->format('Y-m-d H:i:s'))
                ->take(10)
                ->get();

            foreach ($stuckTransactions as $transaction) {
                $results['processed']++;
                
                $retryCount = $transaction->retry_count ?? 0;
                
                if ($retryCount < $this->maxRetries) {
                    // Reset to verified status for retry
                    $transaction->swap_status = Transaction::STATUS_PAYMENT_VERIFIED;
                    $transaction->retry_count = $retryCount + 1;
                    $transaction->last_retry_at = (new \DateTime())->format('Y-m-d H:i:s');
                    $transaction->save();
                    
                    $this->logTransactionEvent($transaction, 'stuck_reset', "Reset stuck transaction for retry");
                    $results['reset']++;
                } else {
                    // Mark as failed if max retries exceeded
                    $transaction->markFailed("Transaction stuck in pending state", Transaction::STATUS_FAILED_CIRX_TRANSFER);
                    $this->logTransactionEvent($transaction, 'stuck_failed', "Marked stuck transaction as failed");
                    $results['failed']++;
                }
            }

        } catch (Exception $e) {
            $results['errors'][] = [
                'worker_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        return $results;
    }

    /**
     * Batch transfer CIRX to multiple users (optimization for high volume)
     */
    public function processBatchTransfer(): array
    {
        $results = [
            'processed' => 0,
            'completed' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            // Get up to 10 transactions for batch processing
            $batchTransactions = Transaction::where('swap_status', Transaction::STATUS_PAYMENT_VERIFIED)
                ->where(function($query) {
                    $query->whereNull('retry_count')->orWhere('retry_count', 0);
                })
                ->orderBy('created_at', 'asc')
                ->take(10)
                ->get();

            if ($batchTransactions->count() > 0) {
                // Use batch transfer service method
                $batchResults = $this->cirxTransferService->batchTransferCirx($batchTransactions->toArray());
                
                foreach ($batchTransactions as $transaction) {
                    $results['processed']++;
                    $transferResult = $batchResults[$transaction->id] ?? null;
                    
                    if ($transferResult && $transferResult->isSuccess()) {
                        $results['completed']++;
                        $this->logTransactionEvent($transaction, 'batch_completed', "Batch transfer successful");
                    } else {
                        $error = $transferResult ? $transferResult->getErrorMessage() : 'Unknown batch error';
                        $results['failed']++;
                        $results['errors'][] = [
                            'transaction_id' => $transaction->id,
                            'error' => $error
                        ];
                        
                        // Handle failure with retry logic
                        $this->handleTransferFailure($transaction, $error);
                    }
                }
            }

        } catch (Exception $e) {
            $results['errors'][] = [
                'worker_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        return $results;
    }

    /**
     * Get statistics about CIRX transfer transactions
     */
    public function getStatistics(): array
    {
        return [
            'ready_for_transfer' => Transaction::where('swap_status', Transaction::STATUS_PAYMENT_VERIFIED)->count(),
            'transfer_pending' => Transaction::where('swap_status', Transaction::STATUS_CIRX_TRANSFER_PENDING)->count(),
            'transfer_initiated' => Transaction::where('swap_status', Transaction::STATUS_CIRX_TRANSFER_INITIATED)->count(),
            'completed' => Transaction::where('swap_status', Transaction::STATUS_COMPLETED)->count(),
            'failed_transfers' => Transaction::where('swap_status', Transaction::STATUS_FAILED_CIRX_TRANSFER)->count(),
            'pending_retries' => Transaction::where('swap_status', Transaction::STATUS_PAYMENT_VERIFIED)
                ->where('retry_count', '>', 0)->count(),
        ];
    }

    /**
     * Check if an error indicates a permanent failure that shouldn't be retried
     */
    private function isPermanentFailure(string $error): bool
    {
        $permanentErrors = [
            'Invalid Circular Protocol address format',
            'CIRX wallet not configured',
            'Invalid amount',
            'Transaction not ready for CIRX transfer'
        ];
        
        foreach ($permanentErrors as $permanentError) {
            if (strpos($error, $permanentError) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Log transaction events using structured logging
     */
    private function logTransactionEvent(Transaction $transaction, string $event, string $message): void
    {
        LoggerService::logWorkerActivity('CirxTransferWorker', $event, [
            'transaction_id' => $transaction->id,
            'message' => $message,
            'swap_status' => $transaction->swap_status,
            'cirx_recipient_address' => $transaction->cirx_recipient_address,
            'amount_paid' => $transaction->amount_paid,
            'retry_count' => $transaction->retry_count ?? 0,
            'cirx_transfer_tx_id' => $transaction->cirx_transfer_tx_id
        ]);
    }

    /**
     * Set maximum retry attempts
     */
    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = max(0, $maxRetries);
    }

    /**
     * Set retry delay in seconds
     */
    public function setRetryDelay(int $retryDelay): void
    {
        $this->retryDelay = max(0, $retryDelay);
    }

    /**
     * Recover transactions in inconsistent states (pending with failure reasons)
     * This handles the specific case where transactions get stuck with pending status
     * but have failure_reason set, preventing normal processing.
     */
    public function recoverInconsistentTransactions(): array
    {
        $results = [
            'scanned' => 0,
            'recovered' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            // Find transactions with inconsistent states:
            // 1. cirx_transfer_pending status but have failure_reason (should be clean)
            // 2. payment_verified status but have failure_reason (should be clean)  
            // 3. Also catch any other pending statuses with failure reasons
            $inconsistentTransactions = Transaction::where(function($query) {
                $query->where('swap_status', Transaction::STATUS_CIRX_TRANSFER_PENDING)
                      ->whereNotNull('failure_reason');
            })->orWhere(function($query) {
                $query->where('swap_status', Transaction::STATUS_PAYMENT_VERIFIED)
                      ->whereNotNull('failure_reason');
            })->orWhere(function($query) {
                // Catch any other pending status with failure reason (like the test data)
                $query->where('swap_status', 'LIKE', '%pending%')
                      ->whereNotNull('failure_reason');
            })
            ->take(20) // Process in batches
            ->get();

            $results['scanned'] = $inconsistentTransactions->count();

            foreach ($inconsistentTransactions as $transaction) {
                $this->logTransactionEvent($transaction, 'inconsistent_state_detected', 
                    "Detected inconsistent transaction state: {$transaction->swap_status} with failure reason");

                // Check how old the failure is
                $lastUpdate = new \DateTime($transaction->updated_at);
                $now = new \DateTime();
                $minutesSinceUpdate = $now->diff($lastUpdate)->i + ($now->diff($lastUpdate)->h * 60);

                // Only auto-recover if the failure is more than 5 minutes old
                // This prevents interfering with ongoing processing
                if ($minutesSinceUpdate >= 5) {
                    $retryCount = $transaction->retry_count ?? 0;
                    
                    // If we haven't exceeded max retries, clean up and retry
                    if ($retryCount < $this->maxRetries) {
                        // Clean up the inconsistent state
                        $transaction->swap_status = Transaction::STATUS_PAYMENT_VERIFIED;
                        $transaction->failure_reason = null;
                        $transaction->last_retry_at = null;
                        $transaction->retry_count = 0; // Reset retry count for fresh start
                        $transaction->updated_at = (new \DateTime())->format('Y-m-d H:i:s');
                        $transaction->save();

                        $this->logTransactionEvent($transaction, 'inconsistent_state_recovered', 
                            "Recovered inconsistent transaction state - reset to payment_verified for retry");
                        $results['recovered']++;
                    } else {
                        // Mark as permanently failed if max retries exceeded
                        $transaction->markFailed(
                            "Auto-recovery failed after {$this->maxRetries} attempts: " . $transaction->failure_reason, 
                            Transaction::STATUS_FAILED_CIRX_TRANSFER
                        );
                        
                        $this->logTransactionEvent($transaction, 'inconsistent_state_failed', 
                            "Marked inconsistent transaction as permanently failed");
                        $results['failed']++;
                    }
                } else {
                    // Too recent, skip recovery to avoid interfering with active processing
                    $this->logTransactionEvent($transaction, 'inconsistent_state_skipped', 
                        "Skipped recent inconsistent transaction - waiting for {5 - $minutesSinceUpdate} more minutes");
                }
            }

        } catch (Exception $e) {
            $results['errors'][] = [
                'recovery_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        return $results;
    }
}