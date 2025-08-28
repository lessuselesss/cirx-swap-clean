<?php
/**
 * Test script for Transaction Readiness Endpoint
 * 
 * This script tests the new /api/v1/health/transaction-ready endpoint
 * to ensure it properly validates all transaction processing components.
 */

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\TransactionReadinessService;
use Illuminate\Database\Capsule\Manager as Capsule;

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
    'database' => $_ENV['DB_DATABASE'] ?? ':memory:',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'username' => $_ENV['DB_USERNAME'] ?? '',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "\n=== Transaction Readiness Test ===\n\n";

try {
    // Test the TransactionReadinessService directly
    $readinessService = new TransactionReadinessService();
    $result = $readinessService->assessTransactionReadiness();
    
    echo "Status: " . ($result['transaction_ready'] ? '✅ READY' : '❌ NOT READY') . "\n";
    echo "Overall Status: " . $result['status'] . "\n";
    echo "Duration: " . $result['duration_ms'] . "ms\n";
    
    if (!empty($result['critical_issues'])) {
        echo "\n🚨 Critical Issues:\n";
        foreach ($result['critical_issues'] as $issue) {
            echo "   • " . $issue . "\n";
        }
    }
    
    if (!empty($result['warnings'])) {
        echo "\n⚠️ Warnings:\n";
        foreach ($result['warnings'] as $warning) {
            echo "   • " . $warning . "\n";
        }
    }
    
    echo "\n📊 Check Summary:\n";
    echo "   Total Checks: " . $result['summary']['total_checks'] . "\n";
    echo "   Healthy: " . $result['summary']['healthy_checks'] . "\n";
    echo "   Degraded: " . $result['summary']['degraded_checks'] . "\n";
    echo "   Critical: " . $result['summary']['critical_checks'] . "\n";
    echo "   Health Percentage: " . $result['summary']['health_percentage'] . "%\n";
    
    echo "\n🔍 Detailed Check Results:\n";
    foreach ($result['checks'] as $checkName => $checkResult) {
        $statusEmoji = match($checkResult['status']) {
            'healthy' => '✅',
            'degraded' => '⚠️',
            'critical' => '❌',
            default => '❓'
        };
        
        echo "   {$statusEmoji} " . ucwords(str_replace('_', ' ', $checkName)) . ": " . $checkResult['message'] . "\n";
        
        if (isset($checkResult['details']) && is_array($checkResult['details'])) {
            foreach ($checkResult['details'] as $key => $value) {
                if (is_array($value)) {
                    continue; // Skip complex nested data for readability
                }
                echo "      └─ " . ucwords(str_replace('_', ' ', $key)) . ": " . $value . "\n";
            }
        }
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    
    if ($result['transaction_ready']) {
        echo "✅ SYSTEM IS TRANSACTION READY\n";
        echo "The backend can guarantee successful transaction processing from payment to CIRX delivery.\n";
    } else {
        echo "❌ SYSTEM NOT READY FOR TRANSACTIONS\n";
        echo "Critical issues must be resolved before processing transactions.\n";
    }
    
    // Test HTTP endpoint if we have curl available
    echo "\n🌐 Testing HTTP Endpoint...\n";
    $testUrl = 'http://localhost:8080/v1/health/transaction-ready';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $httpResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo "   ⚠️ HTTP test failed (server may not be running): {$curlError}\n";
        echo "   To test the HTTP endpoint, start the server with:\n";
        echo "   cd backend && nix run nixpkgs#php -- -S localhost:8080 public/index.php\n";
    } else {
        echo "   HTTP Status: {$httpCode}\n";
        if ($httpCode === 200) {
            echo "   ✅ Endpoint accessible and transaction-ready\n";
        } elseif ($httpCode === 503) {
            echo "   ⚠️ Endpoint accessible but system not transaction-ready\n";
        } else {
            echo "   ❌ Unexpected HTTP status code\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Test failed with exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";