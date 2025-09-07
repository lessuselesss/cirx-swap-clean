<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\DatabaseConnection;
use App\Models\Transaction;
use App\Blockchain\CircularProtocolClient;

// Set up environment
$_ENV['APP_ENV'] = 'development';
$_ENV['CIRX_ENVIRONMENT'] = 'development';
$_ENV['CIRX_WALLET_ADDRESS'] = 'your_wallet_address';
$_ENV['CIRX_WALLET_PRIVATE_KEY'] = 'your_private_key';

echo "🔍 Testing CIRX Transaction Receipt Structure\n";
echo "============================================\n\n";

// Test transaction hashes from the issue
$testTransactions = [
    'ff3a2357-fa94-472b-896b-a7b29716c510' => '8f9a3c6b2d7fe49652163b9e2d280663ec50fb92ca2638b003f65eaa55f0c0d4',
    'ba7e8475-96bf-4d1c-a346-ac5766a490cb' => 'b17b9e23874d439532734d99fd1500072cdc14718efc608c1c5a380ae29b8f5c'
];

try {
    // Initialize database connection
    DatabaseConnection::setTestConfig();
    DatabaseConnection::initialize();

    // Create CIRX client
    $cirxClient = new CircularProtocolClient(
        'development',  // environment
        $_ENV['CIRX_WALLET_ADDRESS'] ?? '',
        $_ENV['CIRX_WALLET_PRIVATE_KEY'] ?? null
    );

    foreach ($testTransactions as $transactionId => $txHash) {
        echo "📋 Testing Transaction: $transactionId\n";
        echo "🔗 TX Hash: $txHash\n";
        
        try {
            // Get raw transaction data
            echo "\n1. Raw Transaction Data:\n";
            $rawTransaction = $cirxClient->getTransaction($txHash);
            echo "Raw transaction structure:\n";
            print_r($rawTransaction);
            
            // Get transaction receipt
            echo "\n2. Transaction Receipt:\n";
            $receipt = $cirxClient->getTransactionReceipt($txHash);
            echo "Receipt structure:\n";
            print_r($receipt);
            
            // Check what fields indicate success
            echo "\n3. Status Analysis:\n";
            if ($receipt) {
                echo "Fields available in receipt:\n";
                foreach ($receipt as $key => $value) {
                    echo "  $key => " . (is_array($value) ? 'array(' . count($value) . ')' : json_encode($value)) . "\n";
                }
                
                // Check various possible status fields
                $statusFields = ['status', 'Status', 'result', 'Result', 'success', 'Success', 'confirmed', 'Confirmed'];
                foreach ($statusFields as $field) {
                    if (isset($receipt[$field])) {
                        echo "  STATUS FIELD FOUND: $field => " . json_encode($receipt[$field]) . "\n";
                    }
                }
            } else {
                echo "No receipt returned!\n";
            }
            
            // Check transaction confirmations
            echo "\n4. Transaction Confirmations:\n";
            $confirmations = $cirxClient->getTransactionConfirmations($txHash);
            echo "Confirmations: $confirmations\n";
            
        } catch (Exception $e) {
            echo "❌ Error testing transaction $transactionId: " . $e->getMessage() . "\n";
        }
        
        echo "\n" . str_repeat("-", 80) . "\n\n";
    }

} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "✅ CIRX receipt structure test completed!\n";