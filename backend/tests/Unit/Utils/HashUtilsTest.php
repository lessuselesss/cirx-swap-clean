<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Utils\HashUtils;

echo "🔧 Testing HashUtils validation...\n\n";

// Test cases
$testCases = [
    // Bogus transaction that should be REJECTED
    'failing_payment_1755964752' => false,
    
    // Valid Ethereum transaction hashes that should be ACCEPTED
    '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef' => true,
    '0x9c22ff5f21f0b81b113e63f7db6da94fedef11b2119b4088b89664fb9a3cb658' => true,
    '0xa1b2c3d4e5f67890a1b2c3d4e5f67890a1b2c3d4e5f67890a1b2c3d4e5f67890' => true,
    
    // Invalid cases that should be REJECTED
    '' => false,
    '0x123' => false, // Too short
    '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdefg' => false, // Invalid char
    'test_transaction_12345678901234567890123456789012345678901234567890' => false,
    '0x0000000000000000000000000000000000000000000000000000000000000000' => false, // All zeros
    '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' => false, // Repeated
];

$passed = 0;
$total = 0;

foreach ($testCases as $hash => $expected) {
    $total++;
    $result = HashUtils::validateTransactionHash($hash);
    $status = $result ? '✅ VALID' : '❌ INVALID';
    $expectedStatus = $expected ? 'VALID' : 'INVALID';
    
    if ($result === $expected) {
        $passed++;
        echo "✅ PASS: '{$hash}' -> {$status} (expected {$expectedStatus})\n";
    } else {
        echo "❌ FAIL: '{$hash}' -> {$status} (expected {$expectedStatus})\n";
        echo "   Error: " . HashUtils::getValidationError($hash) . "\n";
    }
}

echo "\n📊 Results: {$passed}/{$total} tests passed\n\n";

// Test hex conversion
echo "🧮 Testing hex conversion:\n";
try {
    $hex = '0xff';
    $dec = HashUtils::hexToDec($hex);
    $backToHex = HashUtils::decToHex($dec);
    echo "✅ Hex conversion: {$hex} -> {$dec} -> {$backToHex}\n";
} catch (Exception $e) {
    echo "❌ Hex conversion failed: " . $e->getMessage() . "\n";
}

// Test the specific bogus transaction
echo "\n🎯 Testing specific bogus transaction:\n";
$bogusHash = 'failing_payment_1755964752';
$isValid = HashUtils::validateTransactionHash($bogusHash, false); // Don't require 0x prefix
echo "Hash: '{$bogusHash}'\n";
echo "Valid: " . ($isValid ? 'YES ❌' : 'NO ✅') . "\n";
echo "Error: " . HashUtils::getValidationError('0x' . $bogusHash) . "\n";

echo "\n🎉 HashUtils validation test completed!\n";