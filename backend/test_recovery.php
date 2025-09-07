<?php

require_once 'vendor/autoload.php';

use App\Models\Transaction;
use App\Workers\StuckTransactionRecoveryWorker;

// Set up environment
$_ENV['APP_ENV'] = 'testing';
if (!file_exists('.env')) {
    echo "Creating test .env file...\n";
    file_put_contents('.env', "DB_DATABASE=test_database.sqlite\nAPP_ENV=testing\n");
}

// Load environment
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Initialize database
$databasePath = $_ENV['DB_DATABASE'] ?? 'test_database.sqlite';
if (file_exists($databasePath)) {
    $db = new PDO("sqlite:{$databasePath}");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set up Eloquent
    $capsule = new \Illuminate\Database\Capsule\Manager;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => $databasePath,
    ]);
    
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
    echo "🔄 Testing Stuck Transaction Recovery Worker\n";
    echo "========================================\n\n";
    
    // Check for stuck transactions
    $stuckTransactions = Transaction::whereIn('swap_status', [
        'transfer_failed',
        Transaction::STATUS_FAILED_CIRX_TRANSFER,
        Transaction::STATUS_PAYMENT_VERIFIED,
        Transaction::STATUS_CIRX_TRANSFER_PENDING,
        Transaction::STATUS_NEED_CIRX_WALLET_TOP_UP
    ])->get();
    
    echo "Found {$stuckTransactions->count()} potentially stuck transactions:\n";
    foreach ($stuckTransactions as $tx) {
        echo "- ID: {$tx->id}, Status: {$tx->swap_status}, Amount: {$tx->amount_paid} {$tx->payment_token}\n";
    }
    echo "\n";
    
    // Create and run recovery worker
    $recoveryWorker = new StuckTransactionRecoveryWorker();
    
    echo "🚀 Starting recovery process...\n\n";
    $stats = $recoveryWorker->recoverStuckTransactions();
    
    echo "\n📊 Recovery Results:\n";
    echo "===================\n";
    echo "Total Processed: {$stats['total_processed']}\n";
    echo "Phase 1 Recovered (Payment Confirmations): {$stats['phase1_recovered']}\n";
    echo "Phase 2 Recovered (CIRX Transfers): {$stats['phase2_recovered']}\n";
    echo "Phase 3 Recovered (Wallet Funding): {$stats['phase3_recovered']}\n";
    echo "Phase 4 Recovered (Transfer Verification): {$stats['phase4_recovered']}\n";
    echo "Phase 5 Recovered (Hash Validation): {$stats['phase5_recovered']}\n";
    echo "Marked for Top Up: {$stats['marked_for_topup']}\n";
    echo "Errors: {$stats['errors']}\n";
    
    $totalRecovered = $stats['phase1_recovered'] + $stats['phase2_recovered'] + $stats['phase3_recovered'] + $stats['phase4_recovered'] + $stats['phase5_recovered'];
    $successRate = $stats['total_processed'] > 0 
        ? round(($totalRecovered / $stats['total_processed']) * 100, 2) 
        : 0;
    echo "\n✅ Overall Success Rate: {$successRate}%\n";
    
    // Show final transaction states
    echo "\n📋 Final Transaction States:\n";
    echo "============================\n";
    $allTx = Transaction::all()->groupBy('swap_status');
    foreach ($allTx as $status => $transactions) {
        echo "- {$status}: {$transactions->count()}\n";
    }
    
} else {
    echo "❌ Database not found: {$databasePath}\n";
    echo "Please ensure the backend is properly set up.\n";
}

echo "\n🏁 Recovery test complete!\n";