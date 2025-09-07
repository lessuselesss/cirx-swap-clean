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
    
    echo "🔍 Testing Phase 4 Recovery on Stuck 'Initiated' Transactions\n";
    echo "============================================================\n\n";
    
    // Find transactions stuck in cirx_transfer_initiated status
    $initiatedTransactions = Transaction::where('swap_status', Transaction::STATUS_CIRX_TRANSFER_INITIATED)->get();
    
    echo "Found {$initiatedTransactions->count()} transactions in 'cirx_transfer_initiated' status:\n";
    foreach ($initiatedTransactions as $tx) {
        echo "- ID: {$tx->id}\n";
        echo "  Updated: {$tx->updated_at}\n";  
        echo "  CIRX TX: " . ($tx->cirx_transfer_tx_id ?? 'None') . "\n\n";
    }
    
    if ($initiatedTransactions->isEmpty()) {
        echo "✅ No transactions stuck in initiated status!\n";
        exit(0);
    }
    
    // Create recovery worker and test Phase 4 specifically
    $recoveryWorker = new StuckTransactionRecoveryWorker();
    
    echo "🚀 Testing Phase 4 recovery specifically...\n\n";
    
    try {
        // Access Phase 4 method using reflection
        $method = new \ReflectionMethod($recoveryWorker, 'recoverPhase4TransferVerification');
        $method->setAccessible(true);
        
        // Call Phase 4 recovery
        $method->invoke($recoveryWorker);
        
        echo "✅ Phase 4 recovery completed!\n\n";
        
        // Check results
        echo "📊 Results after Phase 4:\n";
        echo "========================\n";
        
        $updatedTransactions = Transaction::whereIn('id', $initiatedTransactions->pluck('id'))->get();
        
        foreach ($updatedTransactions as $tx) {
            $tx->refresh(); // Make sure we get latest status
            echo "- ID: {$tx->id}\n";
            echo "  Status: {$tx->swap_status}\n";
            echo "  Updated: {$tx->updated_at}\n\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ Phase 4 recovery failed: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
} else {
    echo "❌ Database not found: {$databasePath}\n";
}

echo "\n";