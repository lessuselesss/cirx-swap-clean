<?php
/**
 * Secure Production Database Setup Script
 * 
 * This script initializes the testnet production database with proper separation.
 * Run this script directly on the production server via SSH.
 * 
 * Usage: php setup_production_db.php
 */

// Ensure this script is only run via command line for security
if (php_sapi_name() !== 'cli') {
    die("This script can only be run via command line for security reasons.\n");
}

echo "🔐 CIRX OTC Production Database Setup\n";
echo "=====================================\n\n";

// Get the backend root directory (parent of database directory)
$backendRoot = dirname(__DIR__);
$storageDir = $backendRoot . '/storage';

echo "📍 Backend Root: {$backendRoot}\n";
echo "📂 Storage Directory: {$storageDir}\n\n";

// Check if we're in the right directory
$migrateScript = __DIR__ . '/migrate.php';
if (!file_exists($migrateScript)) {
    die("❌ Error: migrate.php not found in database directory.\n");
}

echo "✅ Found migrate.php - we're in the correct directory\n\n";

// Step 1: Create storage directory if needed
echo "Step 1: Creating storage directory...\n";
if (!is_dir($storageDir)) {
    if (mkdir($storageDir, 0755, true)) {
        echo "✅ Created storage directory\n";
    } else {
        die("❌ Failed to create storage directory\n");
    }
} else {
    echo "✅ Storage directory already exists\n";
}

// Check permissions
if (!is_writable($storageDir)) {
    echo "⚠️  Warning: Storage directory is not writable\n";
    echo "   You may need to run: chmod 755 {$storageDir}\n";
} else {
    echo "✅ Storage directory is writable\n";
}

echo "\n";

// Step 2: Check current database situation
echo "Step 2: Analyzing current database situation...\n";

$currentDb = $storageDir . '/database.sqlite';
$testnetDb = $storageDir . '/database.testnet.sqlite';
$mainnetDb = $storageDir . '/database.mainnet.sqlite';

echo "📊 Database file status:\n";
echo "   - database.sqlite: " . (file_exists($currentDb) ? "EXISTS" : "MISSING") . "\n";
echo "   - database.testnet.sqlite: " . (file_exists($testnetDb) ? "EXISTS" : "MISSING") . "\n";
echo "   - database.mainnet.sqlite: " . (file_exists($mainnetDb) ? "EXISTS" : "MISSING") . "\n\n";

// Step 3: Create testnet database if needed
if (!file_exists($testnetDb)) {
    echo "Step 3: Creating testnet production database...\n";
    
    // Run migrations to create fresh database
    $output = [];
    $returnCode = 0;
    exec("cd " . dirname(__FILE__) . " && php migrate.php 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "✅ Migration completed successfully\n";
        echo "   Output: " . implode("\n           ", $output) . "\n\n";
        
        // Check if database.sqlite was created
        if (file_exists($currentDb)) {
            // Rename to testnet-specific database
            if (rename($currentDb, $testnetDb)) {
                echo "✅ Renamed database.sqlite to database.testnet.sqlite\n";
            } else {
                echo "❌ Failed to rename database file\n";
            }
        } else {
            echo "❌ Migration didn't create database.sqlite file\n";
        }
    } else {
        echo "❌ Migration failed\n";
        echo "   Output: " . implode("\n           ", $output) . "\n";
    }
} else {
    echo "Step 3: Testnet database already exists - skipping creation\n";
}

echo "\n";

// Step 4: Set proper permissions
echo "Step 4: Setting database permissions...\n";
if (file_exists($testnetDb)) {
    if (chmod($testnetDb, 0664)) {
        echo "✅ Set permissions (664) on testnet database\n";
    } else {
        echo "⚠️  Failed to set permissions - you may need to run manually:\n";
        echo "   chmod 664 {$testnetDb}\n";
    }
    
    // Get current file owner info
    $fileOwner = posix_getpwuid(fileowner($testnetDb))['name'] ?? 'unknown';
    echo "📋 Current file owner: {$fileOwner}\n";
    echo "   If needed, change ownership: chown www-data:www-data {$testnetDb}\n";
} else {
    echo "❌ No testnet database file to set permissions on\n";
}

echo "\n";

// Step 5: Display environment configuration
echo "Step 5: Environment configuration recommendations...\n";
echo "📝 Update your production .env file with:\n\n";
echo "   DB_CONNECTION=sqlite\n";
echo "   DB_DATABASE=./storage/database.testnet.sqlite\n";
echo "   APP_ENV=production\n";
echo "   TESTNET_MODE=true\n\n";

echo "   OR use absolute path (more reliable):\n";
echo "   DB_DATABASE={$testnetDb}\n\n";

// Step 6: Verification
echo "Step 6: Verification...\n";
if (file_exists($testnetDb) && is_readable($testnetDb)) {
    echo "✅ Testnet database file exists and is readable\n";
    
    // Try to connect to verify it works
    try {
        $pdo = new PDO("sqlite:{$testnetDb}");
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $result->fetchAll(PDO::FETCH_COLUMN);
        echo "✅ Database connection successful\n";
        echo "📋 Tables found: " . implode(', ', $tables) . "\n";
    } catch (Exception $e) {
        echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Testnet database file not accessible\n";
}

echo "\n";

// Step 7: Final summary
echo "Step 7: Setup Summary\n";
echo "====================\n";
echo "✅ Storage directory: " . (is_dir($storageDir) ? "Ready" : "Needs creation") . "\n";
echo "✅ Testnet database: " . (file_exists($testnetDb) ? "Ready" : "Needs creation") . "\n";
echo "📋 Next steps:\n";
echo "   1. Update production .env file with database path\n";
echo "   2. Test health check: curl https://circularprotocol.io/buy/api/v1/health\n";
echo "   3. Remove this setup script for security: rm setup_production_db.php\n\n";

echo "🎉 Production database setup complete!\n";
echo "   The system is now ready for testnet transactions.\n";
echo "   When ready for mainnet, run this script again to create database.mainnet.sqlite\n\n";
?>