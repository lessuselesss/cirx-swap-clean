<?php

namespace App\Workers;

use App\Models\Transaction;
use App\Services\PaymentVerificationService;

/**
 * Stuck Transaction Recovery Worker
 * 
 * Periodically reviews transactions that have been stuck in failed states
 * and attempts recovery for those that may have failed due to temporary issues.
 * 
 * This prevents legitimate transactions from being permanently lost due to
 * system downtime, network issues, or configuration problems.
 */
class StuckTransactionRecoveryWorker
{
    private PaymentVerificationService $paymentVerificationService;
    private int $recoveryAgeHours;
    private int $maxRecoveryAttempts;
    
    public function __construct(
        ?PaymentVerificationService $paymentVerificationService = null,
        int $recoveryAgeHours = 24,
        int $maxRecoveryAttempts = 2
    ) {
        $this->paymentVerificationService = $paymentVerificationService ?? new PaymentVerificationService();
        $this->recoveryAgeHours = $recoveryAgeHours;
        $this->maxRecoveryAttempts = $maxRecoveryAttempts;
    }

    /**
     * Process stuck transactions for potential recovery
     */
    public function processStuckTransactions(): array
    {
        $results = [
            'scanned' => 0,
            'recovered' => 0,
            'permanently_failed' => 0,
            'errors' => []
        ];

        try {
            // Find transactions that have been failed for a while and might be recoverable
            $stuckTransactions = $this->findRecoverableTransactions();
            $results['scanned'] = count($stuckTransactions);

            foreach ($stuckTransactions as $transaction) {
                $recoveryResult = $this->attemptTransactionRecovery($transaction);
                
                if ($recoveryResult['recovered']) {
                    $results['recovered']++;
                } elseif ($recoveryResult['permanently_failed']) {
                    $results['permanently_failed']++;
                }
                
                if (!empty($recoveryResult['error'])) {
                    $results['errors'][] = [
                        'transaction_id' => $transaction->id,
                        'error' => $recoveryResult['error']
                    ];
                }
            }

        } catch (\Exception $e) {
            $results['errors'][] = [
                'worker_error' => $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Find transactions that may be recoverable
     */
    private function findRecoverableTransactions(): array
    {
        $cutoffTime = (new \DateTime())
            ->sub(new \DateInterval('PT' . $this->recoveryAgeHours . 'H'))
            ->format('Y-m-d H:i:s');

        // Look for failed payment verification transactions that are old enough
        // and haven't exceeded recovery attempts
        return Transaction::where('swap_status', Transaction::STATUS_FAILED_PAYMENT_VERIFICATION)
            ->where('updated_at', '<', $cutoffTime)
            ->where(function($query) {
                $query->whereNull('recovery_attempts')
                      ->orWhere('recovery_attempts', '<', $this->maxRecoveryAttempts);
            })
            ->orderBy('updated_at', 'asc')
            ->take(10) // Process in batches
            ->get()
            ->toArray();
    }

    /**
     * Attempt to recover a stuck transaction
     */
    private function attemptTransactionRecovery(Transaction $transaction): array
    {
        try {
            // Increment recovery attempt counter
            $recoveryAttempts = ($transaction->recovery_attempts ?? 0) + 1;
            
            // Check if we've exceeded recovery attempts
            if ($recoveryAttempts > $this->maxRecoveryAttempts) {
                return [
                    'recovered' => false,
                    'permanently_failed' => true,
                    'error' => "Exceeded maximum recovery attempts ({$this->maxRecoveryAttempts})"
                ];
            }

            // Get the platform wallet address from environment
            $platformWallet = $_ENV['PLATFORM_FEE_WALLET'] ?? $_ENV['ETHEREUM_PLATFORM_WALLET'] ?? null;
            
            if (!$platformWallet) {
                return [
                    'recovered' => false,
                    'permanently_failed' => false,
                    'error' => 'Platform wallet address not configured'
                ];
            }

            // Attempt fresh payment verification
            $verificationResult = $this->paymentVerificationService->verifyPayment(
                $transaction->payment_tx_id,
                $transaction->payment_chain,
                $transaction->amount_paid,
                $transaction->payment_token,
                $platformWallet
            );

            // Update recovery attempt counter
            $transaction->recovery_attempts = $recoveryAttempts;
            $transaction->last_recovery_at = (new \DateTime())->format('Y-m-d H:i:s');

            if ($verificationResult->isValid()) {
                // Recovery successful! Reset transaction for normal processing
                $transaction->swap_status = Transaction::STATUS_PENDING_PAYMENT_VERIFICATION;
                $transaction->retry_count = 0;
                $transaction->last_retry_at = null;
                $transaction->failure_reason = null;
                $transaction->save();

                $this->logRecoveryEvent($transaction, 'recovery_success', 'Transaction recovered successfully');

                return [
                    'recovered' => true,
                    'permanently_failed' => false,
                    'error' => null
                ];
            } else {
                // Still failing - check if it's a permanent issue
                $errorMessage = $verificationResult->getErrorMessage();
                
                if ($this->isPermanentFailure($errorMessage)) {
                    // Mark as permanently failed to avoid future recovery attempts
                    $transaction->save(); // Save recovery attempt count
                    $this->logRecoveryEvent($transaction, 'recovery_permanent_failure', "Permanent failure confirmed: {$errorMessage}");
                    
                    return [
                        'recovered' => false,
                        'permanently_failed' => true,
                        'error' => $errorMessage
                    ];
                } else {
                    // Temporary failure - save attempt and try again later
                    $transaction->save(); // Save recovery attempt count
                    $this->logRecoveryEvent($transaction, 'recovery_retry', "Recovery attempt {$recoveryAttempts} failed: {$errorMessage}");
                    
                    return [
                        'recovered' => false,
                        'permanently_failed' => false,
                        'error' => $errorMessage
                    ];
                }
            }

        } catch (\Exception $e) {
            return [
                'recovered' => false,
                'permanently_failed' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Determine if a failure is permanent and shouldn't be retried
     */
    private function isPermanentFailure(string $error): bool
    {
        $permanentErrors = [
            'Payment sent to wrong address',
            'Transaction not found',
            'Transaction failed on blockchain',
            'Invalid transaction amount',
            'Transaction too old'
        ];

        foreach ($permanentErrors as $permanentError) {
            if (stripos($error, $permanentError) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log recovery events for monitoring
     */
    private function logRecoveryEvent(Transaction $transaction, string $event, string $message): void
    {
        error_log("[RECOVERY] Transaction {$transaction->id} - {$event}: {$message}");
    }

    /**
     * Get statistics for monitoring
     */
    public function getRecoveryStats(): array
    {
        $oneDayAgo = (new \DateTime())
            ->sub(new \DateInterval('P1D'))
            ->format('Y-m-d H:i:s');

        return [
            'stuck_transactions' => Transaction::where('swap_status', Transaction::STATUS_FAILED_PAYMENT_VERIFICATION)
                ->where('updated_at', '<', $oneDayAgo)
                ->count(),
            'recoverable_transactions' => Transaction::where('swap_status', Transaction::STATUS_FAILED_PAYMENT_VERIFICATION)
                ->where('updated_at', '<', $oneDayAgo)
                ->where(function($query) {
                    $query->whereNull('recovery_attempts')
                          ->orWhere('recovery_attempts', '<', $this->maxRecoveryAttempts);
                })
                ->count(),
            'recovery_exhausted' => Transaction::where('swap_status', Transaction::STATUS_FAILED_PAYMENT_VERIFICATION)
                ->where('recovery_attempts', '>=', $this->maxRecoveryAttempts)
                ->count()
        ];
    }
}