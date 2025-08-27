<?php

require_once 'vendor/autoload.php';

use CircularProtocol\Api\CircularProtocolAPI;

echo "Testing with CIRX_TOKEN as asset name...\n";

$api = new CircularProtocolAPI();
$api->setNAGURL('https://nag.circularlabs.io/NAG.php?cep=');
$blockchainId = '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2'; // Circular SandBox
$testAddress = '0xe184d1a551b4c0a5a21a90c72e238692c1bb84b5c06b832c37cc0f397ab28443';

try {
    echo "\nTesting with asset='CIRX_TOKEN':\n";
    $response = $api->getWalletBalance($blockchainId, $testAddress, 'CIRX_TOKEN');
    echo "Response: ";
    var_dump($response);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Also try some other variations
$variations = ['CIRX TOKEN', 'CirxToken', 'cirx_token', 'CIRXTOKEN'];

foreach ($variations as $asset) {
    try {
        echo "\nTesting with asset='$asset':\n";
        $response = $api->getWalletBalance($blockchainId, $testAddress, $asset);
        echo "Response: ";
        var_dump($response);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}