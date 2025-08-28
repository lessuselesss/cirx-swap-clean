<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\Transaction;

// Initialize database connection
$capsule = new Capsule;

$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => __DIR__ . '/storage/database.sqlite',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "🔧 Starting cleanup of stuck transactions...\n\n";

try {
    // Find transactions that are stuck in failed states with the old dechex error
    $stuckTransactions = Transaction::whereIn('swap_status', [
        Transaction::STATUS_FAILED_PAYMENT_VERIFICATION,
        Transaction::STATUS_FAILED_CIRX_TRANSFER
    ])
    ->where('failure_reason', 'LIKE', '%dechex%')
    ->orWhere('failure_reason', 'LIKE', '%must be of type int%')
    ->get();

    echo "Found " . count($stuckTransactions) . " stuck transactions with dechex errors\n";

    foreach ($stuckTransactions as $transaction) {
        echo "Processing transaction: " . $transaction->id . "\n";
        echo "  Current status: " . $transaction->swap_status . "\n";
        echo "  Failure reason: " . $transaction->failure_reason . "\n";
        
        // Reset the transaction to pending payment verification so it can be retried with the fixed code
        $transaction->swap_status = Transaction::STATUS_PENDING_PAYMENT_VERIFICATION;
        $transaction->failure_reason = null;
        $transaction->retry_count = 0;
        $transaction->last_retry_at = null;
        $transaction->save();
        
        echo "  ✅ Reset to pending for retry\n\n";
    }
    
    // Also find transactions that might be stuck in processing states for too long
    $oldProcessingTransactions = Transaction::whereIn('swap_status', [
        Transaction::STATUS_CIRX_TRANSFER_PENDING,
        Transaction::STATUS_CIRX_TRANSFER_INITIATED
    ])
    ->where('updated_at', '<', date('Y-m-d H:i:s', strtotime('-1 hour')))
    ->get();
    
    echo "Found " . count($oldProcessingTransactions) . " transactions stuck in processing states\n";
    
    foreach ($oldProcessingTransactions as $transaction) {
        echo "Processing old transaction: " . $transaction->id . "\n";
        echo "  Current status: " . $transaction->swap_status . "\n";
        echo "  Last updated: " . $transaction->updated_at . "\n";
        
        // Reset to payment verified so they can be retried
        $transaction->swap_status = Transaction::STATUS_PAYMENT_VERIFIED;
        $transaction->retry_count = 0;
        $transaction->last_retry_at = null;
        $transaction->save();
        
        echo "  ✅ Reset to payment verified for retry\n\n";
    }
    
    echo "🎉 Cleanup completed successfully!\n";
    
    // Show current transaction status summary
    echo "\n📊 Current transaction status summary:\n";
    $statusCounts = Transaction::selectRaw('swap_status, COUNT(*) as count')
        ->groupBy('swap_status')
        ->get();
    
    foreach ($statusCounts as $status) {
        echo "  " . $status->swap_status . ": " . $status->count . "\n";
    }

} catch (Exception $e) {
    echo "❌ Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}