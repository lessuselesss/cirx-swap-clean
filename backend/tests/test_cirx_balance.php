<?php

require_once 'vendor/autoload.php';

use App\Blockchain\CircularProtocolClient;
use App\Services\LoggerService;

// Manually set environment variables for testing
$_ENV['CIRX_WALLET_ADDRESS'] = '0xe184d1a551b4c0a5a21a90c72e238692c1bb84b5c06b832c37cc0f397ab28443';
$_ENV['CIRX_WALLET_PRIVATE_KEY'] = '694b4d58eec60f2b78f5d45da87d02612b09a7779aede6c08082f514e736c5c7';

echo "Testing CIRX Balance Check with Working NAG...\n";

try {
    $client = new CircularProtocolClient(
        'development',
        $_ENV['CIRX_WALLET_ADDRESS'],
        $_ENV['CIRX_WALLET_PRIVATE_KEY'],
        18,
        LoggerService::getLogger('cirx_test')
    );
    
    echo "Client initialized successfully\n";
    
    // Test health check
    $isHealthy = $client->isHealthy();
    echo "NAG Health Check: " . ($isHealthy ? "✓ PASS" : "✗ FAIL") . "\n";
    
    // Test balance check
    $balance = $client->getBalance($_ENV['CIRX_WALLET_ADDRESS']);
    echo "CIRX Balance: $balance CIRX\n";
    
    // Test another wallet for comparison
    $testAddress = '0x0000000000000000000000000000000000000001';
    $testBalance = $client->getBalance($testAddress);
    echo "Test Address Balance: $testBalance CIRX\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}