<?php

require_once 'vendor/autoload.php';

$dbPath = __DIR__ . '/storage/database.sqlite';

if (!file_exists($dbPath)) {
    die("Database not found at: $dbPath\n");
}

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking current transaction status...\n\n";
    
    // Get transaction status counts
    $stmt = $db->query("
        SELECT status, COUNT(*) as count 
        FROM transactions 
        GROUP BY status 
        ORDER BY count DESC
    ");
    
    echo "Transaction Status Summary:\n";
    echo "==========================\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%-30s: %d\n", $row['status'], $row['count']);
    }
    
    echo "\nRecent transactions (last 10):\n";
    echo "===============================\n";
    $stmt = $db->query("
        SELECT id, payment_hash, status, cirx_amount, error_message, created_at
        FROM transactions 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf(
            "ID: %d | Status: %s | CIRX: %s | Hash: %s | Error: %s | Time: %s\n",
            $row['id'],
            $row['status'],
            $row['cirx_amount'] ?? 'N/A',
            substr($row['payment_hash'] ?? 'N/A', 0, 10) . '...',
            $row['error_message'] ? substr($row['error_message'], 0, 50) . '...' : 'None',
            $row['created_at']
        );
    }
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}