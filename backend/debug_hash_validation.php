<?php

require __DIR__ . '/vendor/autoload.php';

use App\Utils\HashUtils;

echo "🔍 DEBUGGING HASH VALIDATION\n";
echo "============================\n\n";

$testHashes = [
    '0x7d91a34d069755a93b6538d81cf79be14faaa9f4247402ded5142d23d8aad0c4',
    '0xbc5209f62fb9ae3893aaff6c3d18498ac27fba54178c9aea017c5e1342c45861',
    '0x8ceeea05e0754cc8f4d32154d595c6611e244728cab37d7ab88fa23b245abec5',
    '0x9ca1027b272dca98a7a64d2b0f42a82de9d7eb5299cc8d7f72a22f2d8ff6af6e',
];

foreach ($testHashes as $hash) {
    echo "Testing hash: $hash\n";
    echo "  Length: " . strlen($hash) . "\n";
    echo "  Length without 0x: " . strlen(substr($hash, 2)) . "\n";
    echo "  Starts with 0x: " . (str_starts_with($hash, '0x') ? 'YES' : 'NO') . "\n";
    echo "  Is hex: " . (ctype_xdigit(substr($hash, 2)) ? 'YES' : 'NO') . "\n";
    echo "  Validation result: " . (HashUtils::validateTransactionHash($hash) ? 'PASS' : 'FAIL') . "\n";
    if (!HashUtils::validateTransactionHash($hash)) {
        echo "  Error: " . HashUtils::getValidationError($hash) . "\n";
    }
    echo "\n";
}

echo "Testing isNotObviousFake method directly...\n";
$reflection = new ReflectionClass('App\Utils\HashUtils');
$method = $reflection->getMethod('isNotObviousFake');
$method->setAccessible(true);

foreach ($testHashes as $hash) {
    $result = $method->invoke(null, $hash);
    echo "Hash $hash -> isNotObviousFake: " . ($result ? 'TRUE' : 'FALSE') . "\n";
}