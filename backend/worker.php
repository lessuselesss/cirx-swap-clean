<?php

require __DIR__ . '/vendor/autoload.php';

use App\Workers\PaymentVerificationWorker;
use App\Workers\CirxTransferWorker;
use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
try {
    $dotenv->load();
} catch (Exception $e) {
    // Environment file might not exist in some deployments
}

// Set up database connection
$capsule = new Capsule();
$capsule->addConnection([
    'driver' => $_ENV['DB_CONNECTION'] ?? 'sqlite',
    'database' => $_ENV['DB_DATABASE'] ?? __DIR__ . '/storage/database.sqlite',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

/**
 * Simple worker runner script
 * 
 * Usage:
 *   php worker.php payment-verification
 *   php worker.php cirx-transfer
 *   php worker.php both
 *   php worker.php stats
 */

function runWorkers($type = 'both') {
    $paymentWorker = new PaymentVerificationWorker();
    $cirxWorker = new CirxTransferWorker();
    
    echo "=== CIRX OTC Workers Started at " . date('Y-m-d H:i:s') . " ===\n";
    
    if ($type === 'payment-verification' || $type === 'both') {
        echo "\n--- Payment Verification Worker ---\n";
        
        // Process pending transactions
        $results = $paymentWorker->processPendingTransactions();
        echo "Processed: {$results['processed']}, Verified: {$results['verified']}, Failed: {$results['failed']}, Retried: {$results['retried']}\n";
        
        if (!empty($results['errors'])) {
            echo "Errors:\n";
            foreach ($results['errors'] as $error) {
                echo "  - " . json_encode($error) . "\n";
            }
        }
        
        // Process retries
        $retryResults = $paymentWorker->processRetryTransactions();
        echo "Retries - Processed: {$retryResults['processed']}, Verified: {$retryResults['verified']}, Failed: {$retryResults['failed']}\n";
    }
    
    if ($type === 'cirx-transfer' || $type === 'both') {
        echo "\n--- CIRX Transfer Worker ---\n";
        
        // Process ready transactions
        $results = $cirxWorker->processReadyTransactions();
        echo "Processed: {$results['processed']}, Completed: {$results['completed']}, Failed: {$results['failed']}, Retried: {$results['retried']}\n";
        
        if (!empty($results['errors'])) {
            echo "Errors:\n";
            foreach ($results['errors'] as $error) {
                echo "  - " . json_encode($error) . "\n";
            }
        }
        
        // Process stuck transactions
        $stuckResults = $cirxWorker->processStuckTransactions();
        echo "Stuck transactions - Processed: {$stuckResults['processed']}, Reset: {$stuckResults['reset']}, Failed: {$stuckResults['failed']}\n";
        
        // Automatic recovery results will be included in the main processReadyTransactions output
        // but we can also call it directly for visibility during manual runs
        $recoveryResults = $cirxWorker->recoverInconsistentTransactions();
        if ($recoveryResults['scanned'] > 0) {
            echo "Inconsistent state recovery - Scanned: {$recoveryResults['scanned']}, Recovered: {$recoveryResults['recovered']}, Failed: {$recoveryResults['failed']}\n";
        }
        
        // Try batch processing
        $batchResults = $cirxWorker->processBatchTransfer();
        echo "Batch processing - Processed: {$batchResults['processed']}, Completed: {$batchResults['completed']}, Failed: {$batchResults['failed']}\n";
    }
    
    echo "\n=== Workers Completed at " . date('Y-m-d H:i:s') . " ===\n\n";
}

function showStats() {
    $paymentWorker = new PaymentVerificationWorker();
    $cirxWorker = new CirxTransferWorker();
    
    echo "=== Transaction Statistics ===\n";
    
    echo "\nPayment Verification:\n";
    $paymentStats = $paymentWorker->getStatistics();
    foreach ($paymentStats as $key => $value) {
        echo sprintf("  %-20s: %d\n", ucwords(str_replace('_', ' ', $key)), $value);
    }
    
    echo "\nCIRX Transfer:\n";
    $cirxStats = $cirxWorker->getStatistics();
    foreach ($cirxStats as $key => $value) {
        echo sprintf("  %-20s: %d\n", ucwords(str_replace('_', ' ', $key)), $value);
    }
    
    echo "\n";
}

// Parse command line arguments
$command = $argv[1] ?? 'both';

switch ($command) {
    case 'payment-verification':
    case 'payment':
        runWorkers('payment-verification');
        break;
        
    case 'cirx-transfer':
    case 'cirx':
        runWorkers('cirx-transfer');
        break;
        
    case 'both':
    case 'all':
        runWorkers('both');
        break;
        
    case 'stats':
    case 'statistics':
        showStats();
        break;
        
    case 'loop':
        // Continuous processing loop
        echo "Starting continuous worker loop. Press Ctrl+C to stop.\n";
        while (true) {
            runWorkers('both');
            sleep(30); // Wait 30 seconds between runs
        }
        break;
        
    case 'help':
    case '--help':
    case '-h':
        echo "CIRX OTC Background Worker\n\n";
        echo "Usage: php worker.php [command]\n\n";
        echo "Commands:\n";
        echo "  payment-verification  Run only payment verification worker\n";
        echo "  cirx-transfer        Run only CIRX transfer worker\n";
        echo "  both                 Run both workers (default)\n";
        echo "  stats                Show transaction statistics\n";
        echo "  loop                 Run workers continuously every 30 seconds\n";
        echo "  help                 Show this help message\n";
        echo "\n";
        break;
        
    default:
        echo "Unknown command: {$command}\n";
        echo "Use 'php worker.php help' for available commands.\n";
        exit(1);
}