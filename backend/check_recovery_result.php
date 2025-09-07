<?php

require_once 'vendor/autoload.php';

use App\Models\Transaction;

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
    
    echo "📊 Recovery Result Check\n";
    echo "========================\n\n";
    
    // Check the specific transaction we recovered
    $recoveredTx = Transaction::find('ba7e8475-96bf-4d1c-a346-ac5766a490cb');
    
    if ($recoveredTx) {
        echo "✅ Recovered Transaction Found:\n";
        echo "   ID: {$recoveredTx->id}\n";
        echo "   Status: {$recoveredTx->swap_status}\n";
        echo "   CIRX Transfer TX ID: " . ($recoveredTx->cirx_transfer_tx_id ?? 'None') . "\n";
        echo "   Failure Reason: " . ($recoveredTx->failure_reason ?? 'None') . "\n";
        echo "   Created: {$recoveredTx->created_at}\n";
        echo "   Updated: {$recoveredTx->updated_at}\n\n";
    } else {
        echo "❌ Recovered transaction not found!\n\n";
    }
    
    // Show overall statistics
    echo "📈 Overall Transaction Statistics:\n";
    echo "==================================\n";
    $allTx = Transaction::all()->groupBy('swap_status');
    foreach ($allTx as $status => $transactions) {
        echo "- {$status}: {$transactions->count()}\n";
    }
    
    // Count how many failed transactions remain
    $remainingFailed = Transaction::where('swap_status', 'failed_cirx_transfer')->count();
    echo "\n🔄 Remaining Failed Transactions: {$remainingFailed}\n";
    
    // Show completed transactions
    $completed = Transaction::where('swap_status', 'completed')->count();
    $initiated = Transaction::where('swap_status', 'cirx_transfer_initiated')->count();
    echo "✅ Completed Transactions: {$completed}\n";
    echo "🚀 Initiated Transfers: {$initiated}\n";
    
} else {
    echo "❌ Database not found: {$databasePath}\n";
}

echo "\n";