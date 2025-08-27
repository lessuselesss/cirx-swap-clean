<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Validators\SwapRequestValidator;

echo "🔧 Debugging SwapRequestValidator...\n";

$validator = new SwapRequestValidator();

// Test isValidTxId directly using reflection
$reflection = new ReflectionClass(SwapRequestValidator::class);
$method = $reflection->getMethod('isValidTxId');
$method->setAccessible(true);

echo "Testing isValidTxId directly:\n";
$result = $method->invoke($validator, 'failing_payment_1755964752');
echo "isValidTxId('failing_payment_1755964752'): " . ($result ? 'VALID ❌' : 'INVALID ✅') . "\n";

// Test with 0x prefix version
$result2 = $method->invoke($validator, '0xfailing_payment_1755964752');
echo "isValidTxId('0xfailing_payment_1755964752'): " . ($result2 ? 'VALID ❌' : 'INVALID ✅') . "\n";

// Test full validation
echo "\nTesting full validator:\n";
$testData = [
    'txId' => 'failing_payment_1755964752',
    'amountPaid' => '100',
    'cirxRecipientAddress' => '0x1234567890123456789012345678901234567890',
    'paymentToken' => 'USDC',
    'paymentChain' => 'ethereum'
];

try {
    $fullResult = $validator->validate($testData);
    echo "Full validation result: " . json_encode($fullResult, JSON_PRETTY_PRINT) . "\n";
    
    if ($fullResult['valid']) {
        echo "Validation PASSED ❌ - This should have FAILED!\n";
    } else {
        echo "Validation FAILED ✅ - Security fix working!\n";
        echo "Errors: " . json_encode($fullResult['errors'], JSON_PRETTY_PRINT) . "\n";
    }
} catch (Exception $e) {
    echo "Full validation exception: " . $e->getMessage() . " ✅\n";
}

// Test with valid Ethereum transaction hash  
echo "\nTesting with VALID Ethereum hash:\n";
$validTxId = '0x8e7a9f2e4b3c1d6a8f5e3c2b1a9d8c7e6f5a4b3c2d1e9f8a7b6c5d4e3f2a1b95';
echo "Hash length: " . strlen($validTxId) . " (should be 66)\n";
echo "Hash without prefix length: " . strlen(substr($validTxId, 2)) . " (should be 64)\n";

// Test individual validation
$validTxIdResult = $method->invoke($validator, $validTxId);
echo "isValidTxId('$validTxId'): " . ($validTxIdResult ? 'VALID ✅' : 'INVALID ❌') . "\n";

// Test HashUtils directly
$hashUtilsResult = \App\Utils\HashUtils::validateTransactionHash($validTxId, true);
echo "HashUtils validation: " . ($hashUtilsResult ? 'VALID ✅' : 'INVALID ❌') . "\n";

// Get validation error
$validationError = \App\Utils\HashUtils::getValidationError($validTxId);
echo "Validation error: $validationError\n";

$validTestData = [
    'txId' => $validTxId,
    'amountPaid' => '100',
    'cirxRecipientAddress' => '0x1234567890123456789012345678901234567890',
    'paymentToken' => 'USDC',
    'paymentChain' => 'ethereum'
];

$validResult = $validator->validate($validTestData);
echo "Full validation result: " . json_encode($validResult, JSON_PRETTY_PRINT) . "\n";

if ($validResult['valid']) {
    echo "Valid hash PASSED ✅ - Legitimate transactions work!\n";
} else {
    echo "Valid hash FAILED ❌ - Something is wrong with validation!\n";
}

echo "\nDone.\n";