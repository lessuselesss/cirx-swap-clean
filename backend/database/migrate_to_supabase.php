#!/usr/bin/env php
<?php
/**
 * Migration script from SQLite to Supabase PostgreSQL
 * 
 * Usage: php migrate_to_supabase.php [--test] [--clean]
 * Options:
 *   --test   Run in test mode (dry run)
 *   --clean  Drop existing tables in Supabase before migration
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Parse command line arguments
$testMode = in_array('--test', $argv);
$cleanMode = in_array('--clean', $argv);

echo "===========================================\n";
echo "CIRX OTC - SQLite to Supabase Migration\n";
echo "===========================================\n";
echo "Test Mode: " . ($testMode ? 'YES' : 'NO') . "\n";
echo "Clean Mode: " . ($cleanMode ? 'YES' : 'NO') . "\n\n";

// Step 1: Connect to SQLite source database
$sqlitePath = $_ENV['SQLITE_PATH'] ?? __DIR__ . '/storage/database.sqlite';

if (!file_exists($sqlitePath)) {
    die("❌ SQLite database not found at: $sqlitePath\n");
}

echo "✅ Found SQLite database at: $sqlitePath\n";

try {
    $sqlite = new PDO('sqlite:' . $sqlitePath);
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connected to SQLite database\n";
} catch (PDOException $e) {
    die("❌ Failed to connect to SQLite: " . $e->getMessage() . "\n");
}

// Step 2: Connect to Supabase PostgreSQL
$dbUrl = $_ENV['DATABASE_URL'] ?? null;

if (!$dbUrl) {
    die("❌ DATABASE_URL not found in environment variables\n");
}

// Parse the DATABASE_URL
$parsed = parse_url($dbUrl);

if (!$parsed) {
    die("❌ Invalid DATABASE_URL format\n");
}

$pgHost = $parsed['host'] ?? '';
$pgPort = $parsed['port'] ?? 5432;
$pgDb = ltrim($parsed['path'] ?? '', '/');
$pgUser = $parsed['user'] ?? '';
$pgPass = $parsed['pass'] ?? '';

// Parse query parameters for SSL mode
$pgParams = [];
if (isset($parsed['query'])) {
    parse_str($parsed['query'], $pgParams);
}

$sslMode = $pgParams['sslmode'] ?? 'require';

echo "\n📡 Connecting to Supabase PostgreSQL...\n";
echo "   Host: $pgHost\n";
echo "   Port: $pgPort\n";
echo "   Database: $pgDb\n";
echo "   User: $pgUser\n";
echo "   SSL Mode: $sslMode\n\n";

try {
    $dsn = "pgsql:host=$pgHost;port=$pgPort;dbname=$pgDb;sslmode=$sslMode";
    $pgsql = new PDO($dsn, $pgUser, $pgPass);
    $pgsql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connected to Supabase PostgreSQL\n\n";
} catch (PDOException $e) {
    die("❌ Failed to connect to PostgreSQL: " . $e->getMessage() . "\n");
}

// Step 3: Get SQLite data
echo "📊 Reading data from SQLite...\n";

try {
    $sqliteData = $sqlite->query("SELECT * FROM transactions")->fetchAll(PDO::FETCH_ASSOC);
    $count = count($sqliteData);
    echo "✅ Found $count transactions to migrate\n\n";
} catch (PDOException $e) {
    die("❌ Failed to read SQLite data: " . $e->getMessage() . "\n");
}

if ($testMode) {
    echo "🔍 TEST MODE - Showing first 5 records:\n";
    $preview = array_slice($sqliteData, 0, 5);
    foreach ($preview as $i => $row) {
        echo "   Record " . ($i + 1) . ": ID=" . $row['id'] . ", Status=" . $row['swap_status'] . "\n";
    }
    echo "\n⚠️  Test mode enabled - no data will be migrated\n";
    exit(0);
}

// Step 4: Clean existing PostgreSQL data if requested
if ($cleanMode) {
    echo "🧹 Cleaning existing PostgreSQL data...\n";
    try {
        $pgsql->exec("TRUNCATE TABLE transactions CASCADE");
        echo "✅ Existing data cleaned\n\n";
    } catch (PDOException $e) {
        echo "⚠️  Could not clean existing data: " . $e->getMessage() . "\n\n";
    }
}

// Step 5: Prepare PostgreSQL insert statement
$insertSql = "INSERT INTO transactions (
    id,
    payment_tx_id,
    payment_chain,
    cirx_recipient_address,
    sender_address,
    amount_paid,
    payment_token,
    swap_status,
    cirx_transfer_tx_id,
    failure_reason,
    retry_count,
    last_retry_at,
    recovery_attempts,
    last_recovery_at,
    is_test_transaction,
    created_at,
    updated_at
) VALUES (
    :id,
    :payment_tx_id,
    :payment_chain,
    :cirx_recipient_address,
    :sender_address,
    :amount_paid,
    :payment_token,
    :swap_status,
    :cirx_transfer_tx_id,
    :failure_reason,
    :retry_count,
    :last_retry_at,
    :recovery_attempts,
    :last_recovery_at,
    :is_test_transaction,
    :created_at,
    :updated_at
) ON CONFLICT (payment_tx_id) DO UPDATE SET
    swap_status = EXCLUDED.swap_status,
    updated_at = EXCLUDED.updated_at";

$stmt = $pgsql->prepare($insertSql);

// Step 6: Migrate data
echo "🚀 Starting migration...\n";
$success = 0;
$failed = 0;
$errors = [];

foreach ($sqliteData as $row) {
    try {
        // Convert SQLite datetime to PostgreSQL timestamp
        $row['created_at'] = $row['created_at'] ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : null;
        $row['updated_at'] = $row['updated_at'] ? date('Y-m-d H:i:s', strtotime($row['updated_at'])) : null;
        $row['last_retry_at'] = $row['last_retry_at'] ? date('Y-m-d H:i:s', strtotime($row['last_retry_at'])) : null;
        $row['last_recovery_at'] = $row['last_recovery_at'] ? date('Y-m-d H:i:s', strtotime($row['last_recovery_at'])) : null;
        
        // Convert boolean values
        $row['is_test_transaction'] = $row['is_test_transaction'] ? 'true' : 'false';
        
        // If ID is not a valid UUID, generate one
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $row['id'])) {
            // Generate UUID v4 from the original ID
            $row['id'] = sprintf(
                '%08x-%04x-4%03x-%04x-%012x',
                crc32($row['id']),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xfff),
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffffffffffff)
            );
        }
        
        $stmt->execute($row);
        $success++;
        
        if ($success % 100 == 0) {
            echo "   Migrated $success records...\n";
        }
    } catch (PDOException $e) {
        $failed++;
        $errors[] = "Record " . $row['id'] . ": " . $e->getMessage();
    }
}

echo "\n===========================================\n";
echo "Migration Complete!\n";
echo "===========================================\n";
echo "✅ Successfully migrated: $success records\n";

if ($failed > 0) {
    echo "❌ Failed to migrate: $failed records\n";
    echo "\nErrors:\n";
    foreach (array_slice($errors, 0, 10) as $error) {
        echo "   - $error\n";
    }
    if (count($errors) > 10) {
        echo "   ... and " . (count($errors) - 10) . " more errors\n";
    }
}

// Step 7: Verify migration
echo "\n🔍 Verifying migration...\n";

try {
    $pgCount = $pgsql->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    echo "✅ PostgreSQL has $pgCount records\n";
    
    if ($pgCount == $count) {
        echo "✅ All records migrated successfully!\n";
    } else {
        echo "⚠️  Record count mismatch (SQLite: $count, PostgreSQL: $pgCount)\n";
    }
} catch (PDOException $e) {
    echo "❌ Failed to verify migration: " . $e->getMessage() . "\n";
}

echo "\n✨ Migration process completed!\n";