<?php

/**
 * Update existing transactions with sender address
 * This script adds the sender_address column if it doesn't exist and updates all existing transactions
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
try {
    $dotenv->load();
    echo "✅ Environment loaded successfully\n";
} catch (Exception $e) {
    echo "⚠️ Environment loading failed: " . $e->getMessage() . "\n";
}

// Set up database connection
$capsule = new Capsule();
$capsule->addConnection([
    'driver' => $_ENV['DB_CONNECTION'] ?? 'sqlite',
    'database' => $_ENV['DB_DATABASE'] ?? __DIR__ . '/storage/database.sqlite',
    'username' => $_ENV['DB_USERNAME'] ?? '',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'host' => $_ENV['DB_HOST'] ?? '',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    echo "🗄️  Updating Sender Addresses for Existing Transactions\n";
    echo "=====================================================\n\n";

    $schema = Capsule::schema();
    $connection = Capsule::connection();

    // First, check if sender_address column exists, if not add it
    if (!$schema->hasColumn('transactions', 'sender_address')) {
        echo "1. Adding sender_address column...\n";
        $schema->table('transactions', function ($table) {
            $table->string('sender_address', 255)->nullable()->after('payment_chain');
            $table->index('sender_address', 'idx_sender_address');
            $table->index(['sender_address', 'swap_status'], 'idx_sender_status');
        });
        echo "   ✅ Added sender_address column with indexes\n\n";
    } else {
        echo "1. sender_address column already exists\n\n";
    }

    // Get all transactions without sender_address
    $transactions = $connection->table('transactions')
        ->whereNull('sender_address')
        ->get();

    $updateCount = count($transactions);
    
    if ($updateCount > 0) {
        echo "2. Found {$updateCount} transactions without sender address\n";
        echo "   Updating all to: 0x11fB73daa15C84c6166BF20e435396c8f08bFEc9\n\n";

        // Update all transactions with the sender address
        $updated = $connection->table('transactions')
            ->whereNull('sender_address')
            ->update([
                'sender_address' => '0x11fB73daa15C84c6166BF20e435396c8f08bFEc9',
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        echo "   ✅ Updated {$updated} transactions with sender address\n\n";
    } else {
        echo "2. All transactions already have sender addresses\n\n";
    }

    // Show final status
    $totalTransactions = $connection->table('transactions')->count();
    $withSenderAddress = $connection->table('transactions')->whereNotNull('sender_address')->count();
    
    echo "📊 Final Status:\n";
    echo "   Total transactions: {$totalTransactions}\n";
    echo "   With sender address: {$withSenderAddress}\n";
    echo "   Missing sender address: " . ($totalTransactions - $withSenderAddress) . "\n\n";

    if ($withSenderAddress === $totalTransactions) {
        echo "🎉 All transactions now have sender addresses!\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}