<?php

require_once 'vendor/autoload.php';

use CircularProtocol\Api\CircularProtocolAPI;

echo "Testing balance check with blockchain addresses...\n";

$api = new CircularProtocolAPI();
$api->setNAGURL('https://nag.circularlabs.io/NAG.php?cep=');
$testAddress = '0xe184d1a551b4c0a5a21a90c72e238692c1bb84b5c06b832c37cc0f397ab28443';

// Blockchain addresses from getBlockchains response
$blockchains = [
    'Circular Main Public' => '714d2ac07a826b66ac56752eebd7c77b58d2ee842e523d913fd0ef06e6bdfcae',
    'Circular Secondary Public' => 'acb8a9b79f3c663aa01be852cd42725f9e0e497fd849b436df51c5e074ebeb28',
    'Circular Documark Public' => 'e087257c48a949710b48bc725b8d90066871fa08f7bbe75d6b140d50119c481f',
    'Circular SandBox' => '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2'
];

foreach ($blockchains as $name => $address) {
    try {
        echo "\nTesting blockchain: $name\n";
        echo "Using address: $address\n";
        $response = $api->getWalletBalance($address, $testAddress, 0);
        echo "Response: ";
        var_dump($response);
        
    } catch (Exception $e) {
        echo "Error with $name: " . $e->getMessage() . "\n";
    }
}