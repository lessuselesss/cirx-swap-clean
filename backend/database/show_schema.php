<?php

require_once 'vendor/autoload.php';

$dbPath = __DIR__ . '/storage/database.sqlite';

if (!file_exists($dbPath)) {
    die("Database not found at: $dbPath\n");
}

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Database tables:\n";
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table';");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "\n=== Table: $table ===\n";
        $stmt = $db->query("PRAGMA table_info($table);");
        while ($column = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo sprintf("  %-20s %s\n", $column['name'], $column['type']);
        }
        
        // Show row count
        $countStmt = $db->query("SELECT COUNT(*) as count FROM $table;");
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "  Rows: $count\n";
        
        // Show sample data if it's transactions table
        if ($table === 'transactions' && $count > 0) {
            echo "  Sample data:\n";
            $sampleStmt = $db->query("SELECT * FROM $table ORDER BY id DESC LIMIT 3;");
            $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($samples)) {
                $columns = array_keys($samples[0]);
                echo "    " . implode(' | ', $columns) . "\n";
                foreach ($samples as $sample) {
                    $values = array_map(function($v) { return substr($v ?? 'NULL', 0, 20); }, array_values($sample));
                    echo "    " . implode(' | ', $values) . "\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}