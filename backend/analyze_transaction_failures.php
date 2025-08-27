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

echo "📊 TRANSACTION FAILURE PATTERN ANALYSIS\n";
echo "=====================================\n\n";

try {
    // 1. Overall transaction status summary
    echo "1. OVERALL TRANSACTION STATUS SUMMARY\n";
    echo "------------------------------------\n";
    $statusCounts = Transaction::selectRaw('swap_status, COUNT(*) as count')
        ->groupBy('swap_status')
        ->orderBy('count', 'desc')
        ->get();
    
    $totalTransactions = 0;
    foreach ($statusCounts as $status) {
        $totalTransactions += $status->count;
        echo sprintf("  %-35s: %d\n", $status->swap_status, $status->count);
    }
    echo "  " . str_repeat("-", 50) . "\n";
    echo sprintf("  %-35s: %d\n\n", "TOTAL TRANSACTIONS", $totalTransactions);

    // 2. Failed transaction analysis
    echo "2. FAILED TRANSACTION BREAKDOWN\n";
    echo "------------------------------\n";
    $failedTransactions = Transaction::whereIn('swap_status', [
        Transaction::STATUS_FAILED_PAYMENT_VERIFICATION,
        Transaction::STATUS_FAILED_CIRX_TRANSFER
    ])->get();
    
    $failuresByStatus = [];
    $failuresByReason = [];
    
    foreach ($failedTransactions as $transaction) {
        // Count by status
        if (!isset($failuresByStatus[$transaction->swap_status])) {
            $failuresByStatus[$transaction->swap_status] = 0;
        }
        $failuresByStatus[$transaction->swap_status]++;
        
        // Count by reason
        $reason = $transaction->failure_reason ?: 'No reason specified';
        if (!isset($failuresByReason[$reason])) {
            $failuresByReason[$reason] = 0;
        }
        $failuresByReason[$reason]++;
    }
    
    echo "Failed by Status:\n";
    foreach ($failuresByStatus as $status => $count) {
        echo sprintf("  %-35s: %d\n", $status, $count);
    }
    
    echo "\nFailed by Reason (Top 10):\n";
    arsort($failuresByReason);
    $reasonCount = 0;
    foreach ($failuresByReason as $reason => $count) {
        if ($reasonCount >= 10) break;
        $truncatedReason = strlen($reason) > 60 ? substr($reason, 0, 57) . '...' : $reason;
        echo sprintf("  %-60s: %d\n", $truncatedReason, $count);
        $reasonCount++;
    }
    echo "\n";

    // 3. Recent failures (last 24-48 hours)
    echo "3. RECENT FAILURES ANALYSIS (Last 48 Hours)\n";
    echo "------------------------------------------\n";
    $recentFailures = Transaction::whereIn('swap_status', [
        Transaction::STATUS_FAILED_PAYMENT_VERIFICATION,
        Transaction::STATUS_FAILED_CIRX_TRANSFER
    ])
    ->where('updated_at', '>=', date('Y-m-d H:i:s', strtotime('-48 hours')))
    ->orderBy('updated_at', 'desc')
    ->get();
    
    echo "Recent failures count: " . count($recentFailures) . "\n";
    
    $recentFailuresByHour = [];
    foreach ($recentFailures as $transaction) {
        $hour = date('Y-m-d H:00', strtotime($transaction->updated_at));
        if (!isset($recentFailuresByHour[$hour])) {
            $recentFailuresByHour[$hour] = 0;
        }
        $recentFailuresByHour[$hour]++;
    }
    
    echo "\nFailures by hour (Last 48h):\n";
    ksort($recentFailuresByHour);
    foreach ($recentFailuresByHour as $hour => $count) {
        echo sprintf("  %s: %d failures\n", $hour, $count);
    }
    
    if (count($recentFailures) > 0) {
        echo "\nMost recent failure examples:\n";
        $exampleCount = 0;
        foreach ($recentFailures as $transaction) {
            if ($exampleCount >= 5) break;
            echo sprintf("  ID: %s | Status: %s | Time: %s\n", 
                $transaction->id, 
                $transaction->swap_status, 
                $transaction->updated_at
            );
            if ($transaction->failure_reason) {
                echo sprintf("     Reason: %s\n", 
                    strlen($transaction->failure_reason) > 80 ? 
                        substr($transaction->failure_reason, 0, 77) . '...' : 
                        $transaction->failure_reason
                );
            }
            $exampleCount++;
        }
    }
    echo "\n";

    // 4. Stuck transactions analysis
    echo "4. STUCK TRANSACTIONS ANALYSIS\n";
    echo "-----------------------------\n";
    
    // Transactions stuck in processing for too long
    $stuckProcessing = Transaction::whereIn('swap_status', [
        Transaction::STATUS_CIRX_TRANSFER_PENDING,
        Transaction::STATUS_CIRX_TRANSFER_INITIATED,
        Transaction::STATUS_PENDING_PAYMENT_VERIFICATION
    ])
    ->where('updated_at', '<', date('Y-m-d H:i:s', strtotime('-2 hours')))
    ->get();
    
    echo "Transactions stuck in processing (>2 hours): " . count($stuckProcessing) . "\n";
    
    if (count($stuckProcessing) > 0) {
        $stuckByStatus = [];
        foreach ($stuckProcessing as $transaction) {
            if (!isset($stuckByStatus[$transaction->swap_status])) {
                $stuckByStatus[$transaction->swap_status] = 0;
            }
            $stuckByStatus[$transaction->swap_status]++;
        }
        
        echo "Stuck by status:\n";
        foreach ($stuckByStatus as $status => $count) {
            echo sprintf("  %-35s: %d\n", $status, $count);
        }
        
        echo "\nOldest stuck transactions:\n";
        $oldestStuck = $stuckProcessing->sortBy('updated_at')->take(5);
        foreach ($oldestStuck as $transaction) {
            echo sprintf("  ID: %s | Status: %s | Last Update: %s\n",
                $transaction->id,
                $transaction->swap_status,
                $transaction->updated_at
            );
        }
    }
    echo "\n";

    // 5. Retry analysis
    echo "5. RETRY PATTERNS ANALYSIS\n";
    echo "-------------------------\n";
    $retriedTransactions = Transaction::where('retry_count', '>', 0)->get();
    echo "Transactions with retries: " . count($retriedTransactions) . "\n";
    
    if (count($retriedTransactions) > 0) {
        $retryStats = [
            'max_retries' => $retriedTransactions->max('retry_count'),
            'avg_retries' => round($retriedTransactions->avg('retry_count'), 2),
            'total_retries' => $retriedTransactions->sum('retry_count')
        ];
        
        echo sprintf("  Max retries for single transaction: %d\n", $retryStats['max_retries']);
        echo sprintf("  Average retries per transaction: %.2f\n", $retryStats['avg_retries']);
        echo sprintf("  Total retry attempts: %d\n", $retryStats['total_retries']);
        
        // Retry count distribution
        $retryDistribution = [];
        foreach ($retriedTransactions as $transaction) {
            $count = $transaction->retry_count;
            if (!isset($retryDistribution[$count])) {
                $retryDistribution[$count] = 0;
            }
            $retryDistribution[$count]++;
        }
        
        echo "\nRetry count distribution:\n";
        ksort($retryDistribution);
        foreach ($retryDistribution as $retryCount => $transactionCount) {
            echo sprintf("  %d retries: %d transactions\n", $retryCount, $transactionCount);
        }
    }
    echo "\n";

    // 6. Specific error patterns mentioned in cleanup script
    echo "6. SPECIFIC ERROR PATTERNS (From Cleanup Script)\n";
    echo "-----------------------------------------------\n";
    
    // dechex errors
    $dechexErrors = Transaction::where('failure_reason', 'LIKE', '%dechex%')
        ->orWhere('failure_reason', 'LIKE', '%must be of type int%')
        ->get();
    
    echo "Transactions with dechex/type errors: " . count($dechexErrors) . "\n";
    
    if (count($dechexErrors) > 0) {
        echo "Sample dechex errors:\n";
        foreach ($dechexErrors->take(3) as $transaction) {
            echo sprintf("  ID: %s | Status: %s\n", $transaction->id, $transaction->swap_status);
            echo sprintf("     Error: %s\n", 
                strlen($transaction->failure_reason) > 80 ? 
                    substr($transaction->failure_reason, 0, 77) . '...' : 
                    $transaction->failure_reason
            );
        }
    }
    echo "\n";

    // 7. Transaction flow success rates
    echo "7. TRANSACTION FLOW SUCCESS RATES\n";
    echo "--------------------------------\n";
    $completedCount = Transaction::where('swap_status', Transaction::STATUS_COMPLETED)->count();
    $failedCount = Transaction::whereIn('swap_status', [
        Transaction::STATUS_FAILED_PAYMENT_VERIFICATION,
        Transaction::STATUS_FAILED_CIRX_TRANSFER
    ])->count();
    
    $successRate = $totalTransactions > 0 ? ($completedCount / $totalTransactions) * 100 : 0;
    $failureRate = $totalTransactions > 0 ? ($failedCount / $totalTransactions) * 100 : 0;
    
    echo sprintf("Success rate: %.2f%% (%d completed out of %d total)\n", $successRate, $completedCount, $totalTransactions);
    echo sprintf("Failure rate: %.2f%% (%d failed out of %d total)\n", $failureRate, $failedCount, $totalTransactions);
    
    // Transaction age analysis
    echo "\n8. TRANSACTION AGE ANALYSIS\n";
    echo "--------------------------\n";
    $oldestTransaction = Transaction::orderBy('created_at', 'asc')->first();
    $newestTransaction = Transaction::orderBy('created_at', 'desc')->first();
    
    if ($oldestTransaction && $newestTransaction) {
        echo sprintf("Oldest transaction: %s (created %s)\n", 
            $oldestTransaction->id, 
            $oldestTransaction->created_at
        );
        echo sprintf("Newest transaction: %s (created %s)\n", 
            $newestTransaction->id, 
            $newestTransaction->created_at
        );
        
        $daysDiff = date_diff(date_create($oldestTransaction->created_at), date_create($newestTransaction->created_at))->days;
        echo sprintf("Transaction history spans: %d days\n", $daysDiff);
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Analysis completed successfully! 📊\n";

} catch (Exception $e) {
    echo "❌ Error during analysis: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}