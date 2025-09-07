<?php

require_once 'vendor/autoload.php';

use App\Models\Transaction;
use App\Workers\StuckTransactionRecoveryWorker;

// Set up test environment - this will use test mode for faster execution
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
    
    echo "🔄 Testing Single Transaction Recovery (Test Mode)\n";
    echo "=================================================\n\n";
    
    // Find one failed transaction to test with
    $failedTransaction = Transaction::where('swap_status', 'failed_cirx_transfer')->first();
    
    if (!$failedTransaction) {
        echo "❌ No failed transactions found for testing.\n";
        exit(1);
    }
    
    echo "🎯 Testing with transaction ID: {$failedTransaction->id}\n";
    echo "   Status: {$failedTransaction->swap_status}\n";
    echo "   Amount: {$failedTransaction->amount_paid} {$failedTransaction->payment_token}\n";
    echo "   Recipient: {$failedTransaction->cirx_recipient_address}\n\n";
    
    // Create recovery worker with minimal processing
    $recoveryWorker = new StuckTransactionRecoveryWorker();
    
    echo "🚀 Testing Phase 2 recovery on single transaction...\n\n";
    
    try {
        // Test just the Phase 2 method directly on this transaction
        $method = new \ReflectionMethod($recoveryWorker, 'processPhase2Transaction');
        $method->setAccessible(true);
        
        echo "⏰ Before recovery - Status: {$failedTransaction->swap_status}\n";
        
        // Call the recovery method
        $method->invoke($recoveryWorker, $failedTransaction);
        
        // Refresh the transaction from database
        $failedTransaction->refresh();
        
        echo "✅ After recovery - Status: {$failedTransaction->swap_status}\n";
        
        // Check if there's a CIRX transfer TX ID
        if ($failedTransaction->cirx_transfer_tx_id) {
            echo "🔗 CIRX Transfer TX ID: {$failedTransaction->cirx_transfer_tx_id}\n";
        }
        
        // Show any error message
        if ($failedTransaction->failure_reason) {
            echo "❌ Failure Reason: {$failedTransaction->failure_reason}\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ Recovery failed with exception: " . $e->getMessage() . "\n";
        echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
    echo "\n🏁 Single transaction recovery test complete!\n";
    
} else {
    echo "❌ Database not found: {$databasePath}\n";
    echo "Please ensure the backend is properly set up.\n";
}

echo "\n";