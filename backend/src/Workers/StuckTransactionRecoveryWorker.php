<?php

namespace App\Workers;

use App\Services\PaymentVerificationService;
use App\Services\CirxTransferService;
use App\Services\LoggerService;
use App\Models\Transaction;
use Exception;

/**
 * Comprehensive Stuck Transaction Recovery Worker
 * 
 * Handles phase-specific recovery for transactions stuck in various states:
 * - Phase 1: Payment confirmation verification (using ENV thresholds)
 * - Phase 2: CIRX transfer initiation with balance checking
 * - Phase 3: Wallet funding monitoring and auto-retry
 * 
 * Uses ENV-configurable confirmation thresholds and proper CIRX decimal handling
 */
class StuckTransactionRecoveryWorker
{
    private PaymentVerificationService $paymentVerificationService;
    private CirxTransferService $cirxTransferService;
    private $logger;
    private array $stats = [
        'total_processed' => 0,
        'phase1_recovered' => 0,
        'phase2_recovered' => 0,
        'phase3_recovered' => 0,
        'phase4_recovered' => 0,
        'phase5_recovered' => 0,
        'errors' => 0
    ];

    public function __construct(
        ?PaymentVerificationService $paymentVerificationService = null,
        ?CirxTransferService $cirxTransferService = null
    ) {
        $this->paymentVerificationService = $paymentVerificationService ?? new PaymentVerificationService();
        $this->cirxTransferService = $cirxTransferService ?? new CirxTransferService();
        $this->logger = LoggerService::getLogger('stuck_transaction_recovery');
    }

    /**
     * Main recovery entry point - processes all stuck transactions
     */
    public function recoverStuckTransactions(): array
    {
        $this->logger->info('🔄 Starting comprehensive stuck transaction recovery');
        $this->resetStats();

        try {
            // Phase 1: Recover transactions pending payment confirmation
            $this->recoverPhase1PaymentConfirmations();

            // Phase 2: Recover transactions with verified payments but failed CIRX transfers
            $this->recoverPhase2CirxTransfers();

            // Phase 3: REMOVED - Wallet monitoring is handled by separate service
            
            // Phase 4: Verify and complete transactions stuck in "initiated" status  
            $this->recoverPhase4TransferVerification();

            // Phase 5: Validate existing transaction hashes and mark complete (final validation)
            $this->recoverPhase5HashValidation();

            $this->logRecoveryStats();
            return $this->stats;

        } catch (Exception $e) {
            $this->logger->error("❌ Stuck transaction recovery failed: " . $e->getMessage());
            $this->stats['errors']++;
            throw $e;
        }
    }

    /**
     * Phase 1: Recover transactions with unconfirmed payments
     * Check blockchain confirmations using ENV-configured thresholds
     */
    private function recoverPhase1PaymentConfirmations(): void
    {
        $this->logger->info('📊 Phase 1: Checking payment confirmations');

        // Get transactions with zero confirmations or under threshold
        $stuckTransactions = Transaction::whereIn('swap_status', [
            Transaction::STATUS_PAYMENT_PENDING_ZERO_CONFIRMATIONS,
            Transaction::STATUS_PAYMENT_PENDING_UNDER_THRESHOLD
        ])->get();

        $this->logger->info("Found {$stuckTransactions->count()} transactions needing confirmation checks");

        foreach ($stuckTransactions as $transaction) {
            // Try to lock transaction for exclusive processing
            $lockedTransaction = Transaction::lockForProcessing($transaction->id);
            if (!$lockedTransaction) {
                // Transaction is being processed by another worker, skip it
                $this->logger->debug("Transaction {$transaction->id} already locked by another worker");
                continue;
            }

            $this->stats['total_processed']++;
            
            try {
                $this->processPhase1TransactionLocked($lockedTransaction);
                $lockedTransaction->unlockAfterProcessing();
            } catch (Exception $e) {
                $lockedTransaction->releaseLock();
                $this->stats['errors']++;
                $this->logger->error("Phase 1 recovery failed for transaction {$transaction->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process individual Phase 1 transaction with database locking
     */
    private function processPhase1TransactionLocked(Transaction $lockedTransaction): void
    {
        $this->logger->debug("🔍 Checking confirmations for transaction {$lockedTransaction->id}");

        $platformWallet = $_ENV['PLATFORM_FEE_WALLET'] ?? '0x834244d016f29d6acb42c1b054a88e2e9b1c9228';

        // Use PaymentVerificationService with new ENV confirmation thresholds
        $verificationResult = $this->paymentVerificationService->verifyPayment(
            $lockedTransaction->payment_tx_id,
            $lockedTransaction->payment_chain,
            $lockedTransaction->amount_paid,
            $lockedTransaction->payment_token,
            $platformWallet
        );

        if ($verificationResult->isValid()) {
            // Payment is now confirmed - move to verified status
            $lockedTransaction->atomicStatusUpdate(Transaction::STATUS_PAYMENT_VERIFIED);
            $this->stats['phase1_recovered']++;
            
            $this->logger->info("✅ Phase 1 recovery: Transaction {$lockedTransaction->id} payment verified");
            
            // Immediately try Phase 2 for this transaction
            $this->processPhase2TransactionLocked($lockedTransaction);
            
        } else {
            // Still waiting for confirmations - update status appropriately
            $errorMessage = $verificationResult->getErrorMessage();
            
            if (strpos($errorMessage, 'confirmations') !== false) {
                // Under threshold - update status if needed
                if ($lockedTransaction->swap_status !== Transaction::STATUS_PAYMENT_PENDING_UNDER_THRESHOLD) {
                    $lockedTransaction->atomicStatusUpdate(Transaction::STATUS_PAYMENT_PENDING_UNDER_THRESHOLD);
                }
            }
            
            $this->logger->debug("⏳ Transaction {$lockedTransaction->id} still waiting: {$errorMessage}");
        }
    }

    /**
     * Process individual Phase 1 transaction
     */
    private function processPhase1Transaction(Transaction $transaction): void
    {
        $this->logger->debug("🔍 Checking confirmations for transaction {$transaction->id}");

        $platformWallet = $_ENV['PLATFORM_FEE_WALLET'] ?? '0x834244d016f29d6acb42c1b054a88e2e9b1c9228';

        // Use PaymentVerificationService with new ENV confirmation thresholds
        $verificationResult = $this->paymentVerificationService->verifyPayment(
            $transaction->payment_tx_id,
            $transaction->payment_chain,
            $transaction->amount_paid,
            $transaction->payment_token,
            $platformWallet
        );

        if ($verificationResult->isValid()) {
            // Payment is now confirmed - move to verified status
            $transaction->markPaymentVerified();
            $this->stats['phase1_recovered']++;
            
            $this->logger->info("✅ Phase 1 recovery: Transaction {$transaction->id} payment verified");
            
            // Immediately try Phase 2 for this transaction
            $this->processPhase2Transaction($transaction);
            
        } else {
            // Still waiting for confirmations - update status appropriately
            $errorMessage = $verificationResult->getErrorMessage();
            
            if (strpos($errorMessage, 'confirmations') !== false) {
                // Under threshold - update status
                if ($transaction->swap_status !== Transaction::STATUS_PAYMENT_PENDING_UNDER_THRESHOLD) {
                    $transaction->update(['swap_status' => Transaction::STATUS_PAYMENT_PENDING_UNDER_THRESHOLD]);
                }
            }
            
            $this->logger->debug("⏳ Transaction {$transaction->id} still waiting: {$errorMessage}");
        }
    }

    /**
     * Phase 2: Recover transactions with verified payments but failed CIRX transfers
     */
    private function recoverPhase2CirxTransfers(): void
    {
        $this->logger->info('💰 Phase 2: Retrying CIRX transfers');

        // Get transactions with verified payments that haven't started transfer OR failed transfers that need retry
        $readyTransactions = Transaction::whereIn('swap_status', [
            Transaction::STATUS_PAYMENT_VERIFIED,
            Transaction::STATUS_CIRX_TRANSFER_PENDING,
            Transaction::STATUS_FAILED_CIRX_TRANSFER,
            'failed_cirx_transfer' // Legacy status from old database entries
        ])->get();

        $this->logger->info("Found {$readyTransactions->count()} transactions ready for CIRX transfer");

        foreach ($readyTransactions as $transaction) {
            $this->stats['total_processed']++;
            
            try {
                $this->processPhase2Transaction($transaction);
            } catch (Exception $e) {
                $this->stats['errors']++;
                $this->logger->error("Phase 2 recovery failed for transaction {$transaction->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process individual Phase 2 transaction with database locking
     */
    private function processPhase2TransactionLocked(Transaction $lockedTransaction): void
    {
        // Check if transaction is already completed - don't interfere with completed transactions
        if ($lockedTransaction->swap_status === Transaction::STATUS_COMPLETED) {
            $this->logger->debug("Transaction {$lockedTransaction->id} already completed, skipping Phase 2 processing");
            return;
        }
        
        $this->logger->debug("💸 Attempting CIRX transfer for transaction {$lockedTransaction->id}");

        // Calculate CIRX amount needed (using proper exchange rate logic)
        $cirxAmount = $this->calculateCirxAmount($lockedTransaction->amount_paid, $lockedTransaction->payment_token);
        
        try {
            // Attempt the CIRX transfer using the correct service method
            $transferResult = $this->cirxTransferService->transferCirxToUser($lockedTransaction);

            if ($transferResult->isSuccess()) {
                // Transfer successful - CirxTransferService already updated status to completed
                $this->stats['phase2_recovered']++;
                
                $this->logger->info("✅ Phase 2 recovery: CIRX transfer completed for transaction {$lockedTransaction->id}");
                
            } else {
                // Transfer failed - check if it's due to insufficient balance
                $errorMessage = $transferResult->getErrorMessage();
                
                if (strpos(strtolower($errorMessage), 'insufficient') !== false) {
                    // Mark as needing wallet top up using atomic update
                    $lockedTransaction->atomicStatusUpdate(
                        Transaction::STATUS_NEED_CIRX_WALLET_TOP_UP,
                        ['failure_reason' => $errorMessage]
                    );
                    $this->stats['marked_for_topup']++;
                    
                    $this->logger->warning("💳 Transaction {$lockedTransaction->id} marked for wallet top up: {$errorMessage}");
                } else {
                    // Other transfer error
                    $lockedTransaction->atomicStatusUpdate(
                        Transaction::STATUS_FAILED_CIRX_TRANSFER,
                        ['failure_reason' => $errorMessage]
                    );
                    $this->logger->error("❌ Transaction {$lockedTransaction->id} transfer failed: {$errorMessage}");
                }
            }

        } catch (Exception $e) {
            $this->logger->error("Exception in Phase 2 for transaction {$lockedTransaction->id}: " . $e->getMessage());
            $lockedTransaction->atomicStatusUpdate(
                Transaction::STATUS_FAILED_CIRX_TRANSFER,
                ['failure_reason' => 'Transfer exception: ' . $e->getMessage()]
            );
            throw $e;
        }
    }

    /**
     * Process individual Phase 2 transaction
     */
    private function processPhase2Transaction(Transaction $transaction): void
    {
        // Refresh transaction from database to get latest status
        $transaction->refresh();
        
        $this->logger->debug("💸 Processing Phase 2 transaction {$transaction->id} (current status: {$transaction->swap_status})");

        // If transaction is in failed_cirx_transfer status, validate payment first and move to payment_verified
        if ($transaction->swap_status === Transaction::STATUS_FAILED_CIRX_TRANSFER || $transaction->swap_status === 'failed_cirx_transfer') {
            if (!empty($transaction->payment_tx_id)) {
                // Validate payment hash
                $paymentValid = $this->validatePaymentHash($transaction);
                
                if ($paymentValid) {
                    // Move to payment_verified to allow proper CIRX transfer processing
                    $transaction->forceStatusUpdate(Transaction::STATUS_PAYMENT_VERIFIED, [
                        'failure_reason' => null
                    ]);
                    
                    // Reload the transaction from database to ensure all attributes are synchronized
                    $transaction = Transaction::find($transaction->id);
                    
                    // Verify the status was actually updated
                    if ($transaction->swap_status !== Transaction::STATUS_PAYMENT_VERIFIED) {
                        $this->logger->error("❌ Phase 2: Status update failed for transaction {$transaction->id} - expected payment_verified, got {$transaction->swap_status}");
                        return;
                    }
                    
                    $this->logger->info("✅ Phase 2: Validated payment and moved transaction {$transaction->id} to payment_verified");
                } else {
                    $this->logger->warning("❌ Phase 2: Payment hash validation failed for transaction {$transaction->id}");
                    return;
                }
            } else {
                $this->logger->warning("❌ Phase 2: No payment hash for failed transaction {$transaction->id}");
                return;
            }
        }

        // Calculate CIRX amount needed (using proper exchange rate logic)
        $cirxAmount = $this->calculateCirxAmount($transaction->amount_paid, $transaction->payment_token);
        
        try {
            // Attempt the CIRX transfer using the correct service method
            $transferResult = $this->cirxTransferService->transferCirxToUser($transaction);

            if ($transferResult->isSuccess()) {
                // Transfer successful - CirxTransferService already updated status to completed
                $this->stats['phase2_recovered']++;
                
                $this->logger->info("✅ Phase 2 recovery: CIRX transfer completed for transaction {$transaction->id}");
                
            } else {
                // Transfer failed - check if it's due to insufficient balance
                $errorMessage = $transferResult->getErrorMessage();
                
                if (strpos(strtolower($errorMessage), 'insufficient') !== false) {
                    // Log insufficient balance error - separate wallet monitoring service should handle alerts
                    $this->logger->critical("🚨 CIRX wallet insufficient balance for transaction {$transaction->id}: {$errorMessage}");
                    // Transaction remains in failed state for retry when wallet is funded
                } else {
                    // Other transfer error - transaction might already be marked as failed by transferCirxToUser
                    $transaction->refresh();
                    if ($transaction->swap_status !== Transaction::STATUS_FAILED_CIRX_TRANSFER) {
                        $transaction->markFailed($errorMessage, Transaction::STATUS_FAILED_CIRX_TRANSFER);
                    }
                    $this->logger->error("❌ Transaction {$transaction->id} transfer failed: {$errorMessage}");
                }
            }

        } catch (Exception $e) {
            $this->logger->error("Exception in Phase 2 for transaction {$transaction->id}: " . $e->getMessage());
            $transaction->markFailed('Transfer exception: ' . $e->getMessage(), Transaction::STATUS_FAILED_CIRX_TRANSFER);
            throw $e;
        }
    }

    /**
     * Phase 3: Monitor transactions waiting for wallet funding and retry when possible
     */
    private function recoverPhase3WalletFundingRetries(): void
    {
        $this->logger->info('🏦 Phase 3: Monitoring wallet funding and retries');

        // Get transactions waiting for wallet top up
        $fundingTransactions = Transaction::needCirxWalletTopUp()->get();

        if ($fundingTransactions->isEmpty()) {
            $this->logger->info('No transactions waiting for wallet funding');
            return;
        }

        $this->logger->info("Found {$fundingTransactions->count()} transactions waiting for wallet funding");

        // Check current CIRX wallet balance
        $currentBalance = $this->cirxTransferService->getCirxWalletBalance();
        $this->logger->info("Current CIRX wallet balance: {$currentBalance}");

        foreach ($fundingTransactions as $transaction) {
            $this->stats['total_processed']++;
            
            try {
                $this->processPhase3Transaction($transaction, $currentBalance);
            } catch (Exception $e) {
                $this->stats['errors']++;
                $this->logger->error("Phase 3 recovery failed for transaction {$transaction->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process individual Phase 3 transaction
     */
    private function processPhase3Transaction(Transaction $transaction, string $currentBalance): void
    {
        $cirxAmount = $this->calculateCirxAmount($transaction->amount_paid, $transaction->payment_token);
        
        $this->logger->debug("🔄 Checking if wallet can fund transaction {$transaction->id} (needs {$cirxAmount} CIRX)");

        // Use proper CIRX decimal comparison (6 decimals, not 18)
        if (bccomp($currentBalance, $cirxAmount, 6) >= 0) {
            // Wallet has sufficient balance now - retry the transfer
            $this->logger->info("💰 Wallet now has sufficient balance for transaction {$transaction->id}");
            
            // Mark as payment verified and let Phase 2 handle the transfer
            // Use forceStatusUpdate since transitioning from need_cirx_wallet_top_up to payment_verified
            $transaction->forceStatusUpdate(Transaction::STATUS_PAYMENT_VERIFIED);
            $this->processPhase2Transaction($transaction);
            
        } else {
            // Still insufficient - log for monitoring
            $needed = bcsub($cirxAmount, $currentBalance, 6);
            $this->logger->debug("⏳ Transaction {$transaction->id} still waiting: need {$needed} more CIRX");
        }
    }

    /**
     * Calculate CIRX amount needed for a transaction
     */
    private function calculateCirxAmount(string $paidAmount, string $paymentToken): string
    {
        // This should use the same logic as SwapController
        // For now, using a simplified 1:1 conversion for testing
        // TODO: Integrate with actual pricing service
        return $paidAmount; // Simplified - needs proper exchange rate logic
    }

    /**
     * Check if a transaction is considered stuck
     */
    private function isTransactionStuck(Transaction $transaction): bool
    {
        // A transaction is stuck if:
        // 1. It's been pending for more than configured timeout
        // 2. It's in a retry loop without progress
        // 3. It's waiting for manual intervention

        $stuckThresholdMinutes = (int)($_ENV['STUCK_TRANSACTION_THRESHOLD_MINUTES'] ?? 30);
        $timeSinceCreated = now()->diffInMinutes($transaction->created_at);
        
        if ($timeSinceCreated > $stuckThresholdMinutes) {
            return true;
        }

        // Check retry patterns
        if ($transaction->retry_count > 5) {
            return true;
        }

        return false;
    }

    /**
     * Phase 4: Verify and complete transactions stuck in "initiated" status
     * Check if CIRX transfers that were initiated actually completed on blockchain
     */
    private function recoverPhase4TransferVerification(): void
    {
        $this->logger->info('🔍 Phase 4: Verifying initiated CIRX transfers');

        // Get transactions that are stuck in "initiated" status for more than 5 minutes
        $initiatedTransactions = Transaction::where('swap_status', Transaction::STATUS_CIRX_TRANSFER_INITIATED)
            ->where('updated_at', '<', (new \DateTime())->sub(new \DateInterval('PT5M'))->format('Y-m-d H:i:s'))
            ->get();

        if ($initiatedTransactions->isEmpty()) {
            $this->logger->info('No transactions stuck in initiated status');
            return;
        }

        $this->logger->info("Found {$initiatedTransactions->count()} transactions stuck in initiated status");

        foreach ($initiatedTransactions as $transaction) {
            $this->stats['total_processed']++;
            
            try {
                $this->processPhase4Transaction($transaction);
            } catch (Exception $e) {
                $this->stats['errors']++;
                $this->logger->error("Phase 4 recovery failed for transaction {$transaction->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process individual Phase 4 transaction - verify if CIRX transfer actually completed
     */
    private function processPhase4Transaction(Transaction $transaction): void
    {
        $this->logger->debug("🔍 Verifying CIRX transfer completion for transaction {$transaction->id}");

        if (!$transaction->cirx_transfer_tx_id) {
            $this->logger->warning("Transaction {$transaction->id} has no CIRX transfer TX ID, marking as failed");
            $transaction->markFailed('No CIRX transfer transaction ID found', Transaction::STATUS_FAILED_CIRX_TRANSFER);
            return;
        }

        try {
            // Check if the CIRX transfer actually completed on blockchain
            $cirxClient = $this->cirxTransferService->getCirxClient();
            
            $transferReceipt = $cirxClient->getTransactionReceipt($transaction->cirx_transfer_tx_id);
            
            // Use robust success detection - check confirmations first, then status
            $isSuccessful = false;
            $confirmations = 0;
            
            try {
                $confirmations = $cirxClient->getTransactionConfirmations($transaction->cirx_transfer_tx_id);
                if ($confirmations > 0) {
                    $isSuccessful = true;
                }
            } catch (Exception $e) {
                $this->logger->debug("Could not get confirmations for transaction {$transaction->id}: " . $e->getMessage());
            }
            
            // Fallback to status check with multiple accepted values
            if (!$isSuccessful && $transferReceipt && isset($transferReceipt['status'])) {
                $successStatuses = ['success', 'confirmed', '1', 'complete', 'completed'];
                if (in_array(strtolower($transferReceipt['status']), $successStatuses)) {
                    $isSuccessful = true;
                }
            }
            
            if ($isSuccessful) {
                // Transfer actually completed, mark as completed
                $transaction->markCompleted();
                $this->stats['phase4_recovered']++;
                
                $this->logger->info("✅ Phase 4 recovery: Transaction {$transaction->id} verified as completed (confirmations: {$confirmations})");
                
            } elseif ($transferReceipt && isset($transferReceipt['status']) && 
                     in_array(strtolower($transferReceipt['status']), ['failed', 'error', 'rejected'])) {
                // Transfer failed on blockchain
                $transaction->markFailed('CIRX transfer failed on blockchain', Transaction::STATUS_FAILED_CIRX_TRANSFER);
                $this->logger->warning("❌ Phase 4: Transaction {$transaction->id} transfer failed on blockchain");
                
            } else {
                // Transfer still pending or not found - leave as initiated for now
                $statusInfo = $transferReceipt ? "status: " . ($transferReceipt['status'] ?? 'unknown') : 'no receipt';
                $this->logger->debug("⏳ Phase 4: Transaction {$transaction->id} transfer still pending verification ({$statusInfo})");
            }
            
        } catch (Exception $e) {
            $this->logger->warning("Phase 4 verification failed for transaction {$transaction->id}: " . $e->getMessage());
            // Don't mark as failed immediately, might be temporary blockchain issue
        }
    }

    /**
     * Reset stats for new recovery run
     */
    private function resetStats(): void
    {
        $this->stats = [
            'total_processed' => 0,
            'phase1_recovered' => 0,
            'phase2_recovered' => 0,
            'phase3_recovered' => 0,
            'phase4_recovered' => 0,
            'phase5_recovered' => 0,
            'marked_for_topup' => 0,
            'errors' => 0
        ];
    }

    /**
     * Log comprehensive recovery statistics
     */
    private function logRecoveryStats(): void
    {
        $this->logger->info('📈 Stuck Transaction Recovery Stats:', $this->stats);
        
        $totalRecovered = $this->stats['phase1_recovered'] + $this->stats['phase2_recovered'] + $this->stats['phase3_recovered'] + $this->stats['phase4_recovered'];
        $successRate = $this->stats['total_processed'] > 0 
            ? round(($totalRecovered / $this->stats['total_processed']) * 100, 2) 
            : 0;
            
        $this->logger->info("✅ Recovery Summary: {$totalRecovered} recovered out of {$this->stats['total_processed']} processed ({$successRate}%)");
        
        if ($this->stats['marked_for_topup'] > 0) {
            $this->logger->warning("💳 {$this->stats['marked_for_topup']} transactions need wallet top up");
        }
        
        if ($this->stats['errors'] > 0) {
            $this->logger->error("❌ {$this->stats['errors']} errors encountered during recovery");
        }
    }

    /**
     * Phase 5: Validate existing transaction hashes and mark complete
     * Handle edge case where transactions have valid hashes but are marked as failed
     */
    private function recoverPhase5HashValidation(): void
    {
        $this->logger->info('🔍 Phase 5: Validating existing transaction hashes (early validation)');
        
        // Find failed transactions that have BOTH payment and CIRX transfer hashes
        $candidateTransactions = Transaction::whereIn('swap_status', [
            Transaction::STATUS_FAILED_CIRX_TRANSFER,
            Transaction::STATUS_FAILED_PAYMENT_VERIFICATION
        ])
        ->whereNotNull('payment_tx_id')
        ->whereNotNull('cirx_transfer_tx_id')
        ->get();
        
        if ($candidateTransactions->isEmpty()) {
            $this->logger->info('No failed transactions with hashes to validate');
            return;
        }
        
        $this->logger->info("Found {$candidateTransactions->count()} failed transactions with hashes to validate");
        
        foreach ($candidateTransactions as $transaction) {
            $this->stats['total_processed']++;
            
            try {
                $this->processPhase5Transaction($transaction);
            } catch (Exception $e) {
                $this->stats['errors']++;
                $this->logger->error("Phase 5 validation failed for transaction {$transaction->id}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Process individual Phase 5 transaction - validate hashes and mark complete if valid
     */
    private function processPhase5Transaction(Transaction $transaction): void
    {
        $this->logger->debug("🔍 Validating hashes for transaction {$transaction->id}");
        
        $paymentValid = false;
        $cirxTransferValid = false;
        
        // Validate payment transaction hash if exists
        if (!empty($transaction->payment_tx_id)) {
            try {
                $paymentValid = $this->validatePaymentHash($transaction);
                if ($paymentValid) {
                    $this->logger->info("✅ Payment hash {$transaction->payment_tx_id} is valid for transaction {$transaction->id}");
                }
            } catch (Exception $e) {
                $this->logger->warning("Failed to validate payment hash for transaction {$transaction->id}: " . $e->getMessage());
            }
        }
        
        // Validate CIRX transfer hash if exists
        if (!empty($transaction->cirx_transfer_tx_id)) {
            try {
                $cirxTransferValid = $this->validateCirxTransferHash($transaction);
                if ($cirxTransferValid) {
                    $this->logger->info("✅ CIRX transfer hash {$transaction->cirx_transfer_tx_id} is valid for transaction {$transaction->id}");
                }
            } catch (Exception $e) {
                $this->logger->warning("Failed to validate CIRX transfer hash for transaction {$transaction->id}: " . $e->getMessage());
            }
        }
        
        // Phase 5 only marks as completed if BOTH payment and CIRX transfer are valid
        if ($paymentValid && $cirxTransferValid) {
            try {
                $transaction->forceStatusUpdate(Transaction::STATUS_COMPLETED, [
                    'failure_reason' => null
                ]);
                
                $this->stats['phase5_recovered']++;
                $this->logger->info("🎯 Phase 5: Marked transaction {$transaction->id} as completed - both payment and CIRX hashes validated");
            } catch (Exception $e) {
                $this->logger->error("Failed to mark transaction {$transaction->id} as completed: " . $e->getMessage());
                throw $e;
            }
        } else {
            // Phase 5 doesn't handle partial validation - let other phases handle it
            $reasons = [];
            if (!$paymentValid) $reasons[] = "payment hash invalid/unverifiable";
            if (!$cirxTransferValid) $reasons[] = "CIRX transfer hash invalid/unverifiable";
            
            $this->logger->debug("Phase 5: Transaction {$transaction->id} not completed - " . implode(', ', $reasons));
        }
    }
    
    /**
     * Validate payment transaction hash on appropriate blockchain
     */
    private function validatePaymentHash(Transaction $transaction): bool
    {
        if (empty($transaction->payment_tx_id) || empty($transaction->payment_chain)) {
            return false;
        }
        
        // For Ethereum chains, use payment verification service
        if (in_array(strtolower($transaction->payment_chain), ['ethereum', 'sepolia', 'mainnet'])) {
            try {
                $result = $this->paymentVerificationService->verifyTransactionPayment($transaction);
                return $result->isValid();
            } catch (Exception $e) {
                $this->logger->warning("Failed to verify payment for transaction {$transaction->id}: " . $e->getMessage());
                return false;
            }
        }
        
        // For other chains, could add additional validation logic here
        return false;
    }
    
    /**
     * Validate CIRX transfer hash on Circular Protocol
     */
    private function validateCirxTransferHash(Transaction $transaction): bool
    {
        if (empty($transaction->cirx_transfer_tx_id)) {
            return false;
        }
        
        // Use CIRX transfer service to validate hash
        $cirxClient = $this->cirxTransferService->getCircularProtocolClient();
        
        try {
            // Validate the client is available
            if (!$cirxClient) {
                $this->logger->warning("CIRX client not available for hash validation");
                return false;
            }
            
            $cirxTransaction = $cirxClient->getTransaction($transaction->cirx_transfer_tx_id);
            
            if ($cirxTransaction && isset($cirxTransaction['Response']['Status'])) {
                $status = $cirxTransaction['Response']['Status'];
                // Consider transaction valid if it's executed or confirmed
                $isValid = in_array($status, ['Executed', 'Confirmed', 'Success']);
                
                if ($isValid) {
                    $this->logger->debug("CIRX hash {$transaction->cirx_transfer_tx_id} validated successfully with status: {$status}");
                } else {
                    $this->logger->debug("CIRX hash {$transaction->cirx_transfer_tx_id} has status: {$status} (not considered valid)");
                }
                
                return $isValid;
            } else {
                $this->logger->debug("CIRX transaction not found or invalid response format for hash: {$transaction->cirx_transfer_tx_id}");
                return false;
            }
            
        } catch (Exception $e) {
            $this->logger->warning("Failed to validate CIRX hash {$transaction->cirx_transfer_tx_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recovery statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }
}