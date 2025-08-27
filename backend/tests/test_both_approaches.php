<?php

require_once 'vendor/autoload.php';

use CircularProtocol\Api\CircularProtocolAPI;

echo "Testing both blockchain approaches...\n";

$api = new CircularProtocolAPI();
$api->setNAGURL('https://nag.circularlabs.io/NAG.php?cep=');
$testAddress = '0xe184d1a551b4c0a5a21a90c72e238692c1bb84b5c06b832c37cc0f397ab28443';

echo "\n=== Testing with blockchain NAME (like the test) ===\n";
try {
    $result = $api->getWalletBalance('Circular', $testAddress, 'CIRX');
    echo "Result: ";
    var_dump($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Testing with blockchain ADDRESS (like our production code) ===\n"; 
try {
    $result = $api->getWalletBalance('8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2', $testAddress, 'CIRX');
    echo "Result: ";
    var_dump($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}