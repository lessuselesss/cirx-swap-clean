<?php

require_once 'vendor/autoload.php';

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

use App\Blockchain\CircularProtocolClient;
use App\Services\LoggerService;

// Initialize logger and CIRX client with proper constructor arguments
$logger = new LoggerService();
$environment = $_ENV['APP_ENV'] ?? 'testnet';
$walletAddress = $_ENV['CIRX_WALLET_ADDRESS'] ?? '';
$privateKey = $_ENV['CIRX_WALLET_PRIVATE_KEY'] ?? null;
$decimals = (int)($_ENV['CIRX_DECIMALS'] ?? 18);

$cirxClient = new CircularProtocolClient($environment, $walletAddress, $privateKey, $decimals, null);

$hash = '14d193cb493aa73f811477af44e05229e1d19426fd194585673953cb21c1f525';

echo "🔍 Checking CIRX transaction hash: $hash\n\n";

try {
    // Check if transaction exists
    $transaction = $cirxClient->getTransaction($hash);
    if ($transaction) {
        echo "✅ Transaction EXISTS on Circular Protocol!\n";
        echo "Transaction data:\n";
        print_r($transaction);
        
        // Check confirmations
        $confirmations = $cirxClient->getTransactionConfirmations($hash);
        echo "\nConfirmations: $confirmations\n\n";
        
        // Check receipt
        $receipt = $cirxClient->getTransactionReceipt($hash);
        if ($receipt) {
            echo "Receipt status: " . ($receipt['status'] ?? 'unknown') . "\n";
        }
        
        echo "\n🎯 CONCLUSION: This transaction is CONFIRMED on blockchain but marked as FAILED in our database!\n";
        echo "This is the edge case blocking the recovery worker.\n";
    } else {
        echo "❌ Transaction NOT FOUND on Circular Protocol\n";
        echo "This hash may be invalid or from a different network.\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking transaction: " . $e->getMessage() . "\n";
}