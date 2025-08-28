<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "🔧 Fixing bogus test transaction...\n\n";

try {
    // Connect directly to SQLite database
    $dbPath = __DIR__ . '/storage/database.sqlite';
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check the bogus transaction
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE payment_tx_id = 'failing_payment_1755964752'");
    $stmt->execute();
    $bogusTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bogusTransaction) {
        echo "Found bogus test transaction:\n";
        echo "  ID: " . $bogusTransaction['id'] . "\n";
        echo "  Payment Hash: " . $bogusTransaction['payment_tx_id'] . "\n";
        echo "  Status: " . $bogusTransaction['swap_status'] . "\n";
        echo "  Created: " . $bogusTransaction['created_at'] . "\n\n";
        
        // Delete the bogus transaction
        $deleteStmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
        $deleteStmt->execute([$bogusTransaction['id']]);
        
        echo "✅ Deleted bogus test transaction\n\n";
    } else {
        echo "ℹ️  Bogus test transaction not found (might already be deleted)\n\n";
    }
    
    // Show current transaction summary
    echo "📊 Current transaction status summary:\n";
    $statusStmt = $pdo->prepare("SELECT swap_status, COUNT(*) as count FROM transactions GROUP BY swap_status ORDER BY count DESC");
    $statusStmt->execute();
    $statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($statuses as $status) {
        echo "  " . $status['swap_status'] . ": " . $status['count'] . "\n";
    }
    
    echo "\n📝 Recent transactions:\n";
    $recentStmt = $pdo->prepare("SELECT id, payment_tx_id, swap_status, created_at FROM transactions ORDER BY created_at DESC LIMIT 5");
    $recentStmt->execute();
    $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recent as $tx) {
        echo "  " . substr($tx['id'], 0, 8) . "... | " . $tx['swap_status'] . " | " . $tx['created_at'] . "\n";
    }
    
    echo "\n🎉 Cleanup completed!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}