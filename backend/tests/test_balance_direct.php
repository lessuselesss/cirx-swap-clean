<?php

require_once 'vendor/autoload.php';

use CircularProtocol\Api\CircularProtocolAPI;

echo "Testing balance check with different blockchain names...\n";

$api = new CircularProtocolAPI();
$api->setNAGURL('https://nag.circularlabs.io/NAG.php?cep=');
$testAddress = '0xe184d1a551b4c0a5a21a90c72e238692c1bb84b5c06b832c37cc0f397ab28443';

$blockchains = [
    'Circular Main Public',
    'Circular Secondary Public', 
    'Circular Documark Public',
    'Circular SandBox'
];

foreach ($blockchains as $blockchain) {
    try {
        echo "\nTesting blockchain: $blockchain\n";
        $response = $api->getWalletBalance($blockchain, $testAddress, 0);
        echo "Response: ";
        var_dump($response);
        
    } catch (Exception $e) {
        echo "Error with $blockchain: " . $e->getMessage() . "\n";
    }
}