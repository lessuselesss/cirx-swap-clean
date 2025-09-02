<?php

namespace App\Workers;

use App\Models\Transaction;
use App\Services\PaymentVerificationService;
use App\Services\LoggerService;
use Exception;

// No helper functions needed - will use direct DateTime calls

/**
 * Background worker for verifying payments on various blockchains
 * 
 * This worker processes transactions in "pending_payment_verification" status,
 * verifies the payment on the blockchain, and updates the transaction status.
 */
class PaymentVerificationWorker
{
    private PaymentVerificationService $paymentVerificationService;
    private int $maxRetries;
    private int $retryDelay;

    public function __construct(
        ?PaymentVerificationService $paymentVerificationService = null,
        int $maxRetries = 3,
        int $retryDelay = 30
    ) {
        $this->paymentVerificationService = $paymentVerificationService ?? new PaymentVerificationService();
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
    }

    /**
     * Process pending payment verification transactions
     */
    public function processPendingTransactions(): array
    {
        $results = [
            'processed' => 0,
            'verified' => 0,
            'failed' => 0,
            'retried' => 0,
            'errors' => []
        ];

        try {
            // Get transactions pending payment verification
            $pendingTransactions = Transaction::where('swap_status', Transaction::STATUS_PENDING_PAYMENT_VERIFICATION)
                ->orderBy('created_at', 'asc')
                ->take(50) // Process in batches
                ->get();

            foreach ($pendingTransactions as $transaction) {
                $result = $this->processTransaction($transaction);
                $results['processed']++;
                
                switch ($result['status']) {
                    case 'verified':
                        $results['verified']++;
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
     * Process a single transaction for payment verification
     */
    public function processTransaction(Transaction $transaction): array
    {
        try {
            // Verify the payment on the blockchain
            $verificationResult = $this->paymentVerificationService->verifyTransactionPayment($transaction);
            
            if ($verificationResult->isValid()) {
                // Payment verified - update status to trigger next step
                $transaction->swap_status = Transaction::STATUS_PAYMENT_VERIFIED;
                $transaction->save();
                
                $this->logTransactionEvent($transaction, 'payment_verified', 'Payment successfully verified on blockchain');
                
                return [
                    'status' => 'verified',
                    'message' => 'Payment verified and status updated'
                ];
            } else {
                // Payment verification failed
                return $this->handleVerificationFailure($transaction, $verificationResult->getErrorMessage());
            }

        } catch (Exception $e) {
            return $this->handleException($transaction, $e);
        }
    }

    /**
     * Handle payment verification failure with retry logic
     */
    private function handleVerificationFailure(Transaction $transaction, string $error): array
    {
        $retryCount = $transaction->retry_count ?? 0;
        
        if ($retryCount < $this->maxRetries) {
            // Increment retry count and schedule for retry
            $transaction->retry_count = $retryCount + 1;
            $transaction->last_retry_at = (new \DateTime())->format('Y-m-d H:i:s');
            $transaction->save();
            
            $retryNumber = $retryCount + 1;
            $this->logTransactionEvent($transaction, 'verification_retry', "Payment verification failed, retry {$retryNumber}/{$this->maxRetries}: {$error}");
            
            return [
                'status' => 'retried',
                'message' => "Scheduled for retry {$retryNumber}/{$this->maxRetries}",
                'error' => $error
            ];
        } else {
            // Max retries reached - mark as failed
            $transaction->markFailed("Payment verification failed after {$this->maxRetries} attempts: {$error}", Transaction::STATUS_FAILED_PAYMENT_VERIFICATION);
            
            $this->logTransactionEvent($transaction, 'verification_failed', "Payment verification failed permanently: {$error}");
            
            return [
                'status' => 'failed',
                'message' => 'Payment verification failed permanently',
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
            $transaction->markFailed("Worker exception after {$this->maxRetries} attempts: {$e->getMessage()}", Transaction::STATUS_FAILED_PAYMENT_VERIFICATION);
            
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
            'verified' => 0,
            'failed' => 0,
            'retried' => 0,
            'errors' => []
        ];

        try {
            // Get transactions that are due for retry (waiting period has passed)
            $retryTransactions = Transaction::where('swap_status', Transaction::STATUS_PENDING_PAYMENT_VERIFICATION)
                ->where('retry_count', '>', 0)
                ->where(function($query) {
                    $query->whereNull('last_retry_at')
                          ->orWhere('last_retry_at', '<', (new \DateTime())->sub(new \DateInterval('PT' . $this->retryDelay . 'S'))->format('Y-m-d H:i:s'));
                })
                ->orderBy('last_retry_at', 'asc')
                ->take(20)
                ->get();

            foreach ($retryTransactions as $transaction) {
                $result = $this->processTransaction($transaction);
                $results['processed']++;
                
                switch ($result['status']) {
                    case 'verified':
                        $results['verified']++;
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
     * Get statistics about pending transactions
     */
    public function getStatistics(): array
    {
        return [
            'pending_verification' => Transaction::where('swap_status', Transaction::STATUS_PENDING_PAYMENT_VERIFICATION)->count(),
            'pending_retries' => Transaction::where('swap_status', Transaction::STATUS_PENDING_PAYMENT_VERIFICATION)
                ->where('retry_count', '>', 0)->count(),
            'failed_verification' => Transaction::where('swap_status', Transaction::STATUS_FAILED_PAYMENT_VERIFICATION)->count(),
            'payment_verified' => Transaction::where('swap_status', Transaction::STATUS_PAYMENT_VERIFIED)->count(),
        ];
    }

    /**
     * Log transaction events using structured logging
     */
    private function logTransactionEvent(Transaction $transaction, string $event, string $message): void
    {
        LoggerService::logWorkerActivity('PaymentVerificationWorker', $event, [
            'transaction_id' => $transaction->id,
            'message' => $message,
            'payment_chain' => $transaction->payment_chain,
            'payment_token' => $transaction->payment_token,
            'amount_paid' => $transaction->amount_paid,
            'retry_count' => $transaction->retry_count ?? 0
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
}