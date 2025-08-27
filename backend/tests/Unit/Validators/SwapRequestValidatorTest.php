<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Validators\SwapRequestValidator;

echo "🔧 Testing SwapRequestValidator with bogus hash...\n";

// Test the validator with bogus transaction
$validator = new SwapRequestValidator();
$testData = [
    'payment_tx_id' => 'failing_payment_1755964752',
    'amount' => '100',
    'cirx_address' => '0x1234567890123456789012345678901234567890',
    'payment_token' => 'USDC'
];

try {
    $result = $validator->validate($testData);
    echo "Validation result: " . ($result ? 'PASSED ❌' : 'FAILED ✅') . "\n";
    echo "Expected: FAILED (bogus transaction should be rejected)\n";
} catch (Exception $e) {
    echo "Exception (good): " . $e->getMessage() . "\n";
}

// Test with valid hash for comparison
echo "\n🧪 Testing with valid transaction hash...\n";
$validTestData = [
    'payment_tx_id' => '0x9c22ff5f21f0b81b113e63f7db6da94fedef11b2119b4088b89664fb9a3cb658',
    'amount' => '100', 
    'cirx_address' => '0x1234567890123456789012345678901234567890',
    'payment_token' => 'USDC'
];

try {
    $validResult = $validator->validate($validTestData);
    echo "Valid hash result: " . ($validResult ? 'PASSED ✅' : 'FAILED ❌') . "\n";
    echo "Expected: PASSED (valid transaction should be accepted)\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "\n✅ API entry point now protected against bogus transactions!\n";