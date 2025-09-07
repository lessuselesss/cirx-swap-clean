<?php

require_once 'vendor/autoload.php';

use App\Blockchain\CircularProtocolClient;

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

echo "🔍 Checking CIRX Wallet Balance\n";
echo "================================\n\n";

$walletAddress = $_ENV['CIRX_WALLET_ADDRESS'] ?? null;
$privateKey = $_ENV['CIRX_WALLET_PRIVATE_KEY'] ?? null;

if (!$walletAddress || !$privateKey) {
    echo "❌ CIRX wallet not configured in .env\n";
    exit(1);
}

echo "Wallet Address: {$walletAddress}\n";
echo "Environment: " . ($_ENV['APP_ENV'] ?? 'development') . "\n";
echo "NAG URL: " . ($_ENV['CIRCULAR_NAG_URL'] ?? 'https://nag.circularlabs.io/NAG.php?cep=') . "\n\n";

try {
    $client = new CircularProtocolClient($walletAddress, $privateKey);
    
    echo "🔄 Fetching balance from Circular Protocol...\n";
    $balance = $client->getCirxBalance($walletAddress);
    
    echo "\n✅ Current CIRX Balance: {$balance} CIRX\n\n";
    
    // Check if it's enough for test transactions
    $requiredAmounts = [
        '0.04 ETH' => '43.2',  // First transaction
        '0.004 ETH (x3)' => '4.3', // Other transactions
    ];
    
    echo "📊 Transaction Requirements:\n";
    foreach ($requiredAmounts as $payment => $cirx) {
        $hasEnough = bccomp($balance, $cirx, 6) >= 0;
        $emoji = $hasEnough ? '✅' : '❌';
        echo "{$emoji} {$payment} requires {$cirx} CIRX - " . ($hasEnough ? 'Sufficient' : 'Insufficient') . "\n";
    }
    
    $totalRequired = bcadd('43.2', bcmul('4.3', '3', 6), 6);
    echo "\nTotal Required: {$totalRequired} CIRX\n";
    
    if (bccomp($balance, $totalRequired, 6) >= 0) {
        echo "✅ Wallet has sufficient balance for all pending transactions\n";
    } else {
        $shortfall = bcsub($totalRequired, $balance, 6);
        echo "❌ Wallet needs {$shortfall} more CIRX\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n🏁 Balance check complete!\n";