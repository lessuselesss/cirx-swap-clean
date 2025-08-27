<?php

require_once 'vendor/autoload.php';

use CircularProtocol\Api\CircularProtocolAPI;

echo "Testing with proper Circular Protocol wallet address...\n";

$api = new CircularProtocolAPI();
$api->setNAGURL('https://nag.circularlabs.io/NAG.php?cep=');

// Generate proper Circular wallet like the test does
$keys = $api->keysFromSeedPhrase('test_seed_phrase_for_integration_testing');
echo "Generated wallet address: " . $keys['walletAddress'] . "\n";

echo "\n=== Testing with blockchain NAME + proper wallet ===\n";
try {
    $result = $api->getWalletBalance('Circular', $keys['walletAddress'], 'CIRX');
    echo "Result: ";
    var_dump($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Testing with blockchain ADDRESS + proper wallet ===\n"; 
try {
    $result = $api->getWalletBalance('8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2', $keys['walletAddress'], 'CIRX');
    echo "Result: ";
    var_dump($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}