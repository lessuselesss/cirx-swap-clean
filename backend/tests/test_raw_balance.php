<?php

require_once 'vendor/autoload.php';

use CircularProtocol\Api\CircularProtocolAPI;

echo "Testing RAW balance response from NAG...\n";

$api = new CircularProtocolAPI();
$api->setNAGURL('https://nag.circularlabs.io/NAG.php?cep=');
$blockchainId = '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2'; // Circular SandBox
$address = '0xe184d1a551b4c0a5a21a90c72e238692c1bb84b5c06b832c37cc0f397ab28443';

try {
    echo "\nCalling NAG directly with correct capitalization:\n";
    $data = [
        'Blockchain' => $blockchainId,
        'Address' => $address,
        'Asset' => 'CIRX',
        'Version' => '1.0.8'
    ];
    
    $response = $api->fetch($api->getNAGURL() . 'Circular_GetWalletBalance_', $data);
    echo "Raw NAG Response:\n";
    var_dump($response);
    
    if (isset($response->Result) && $response->Result === 200 && isset($response->Response->Balance)) {
        $rawBalance = $response->Response->Balance;
        echo "\nRaw Balance Value: " . $rawBalance . "\n";
        echo "Raw Balance Type: " . gettype($rawBalance) . "\n";
        
        // Test if this is already in human readable form or wei
        echo "As string: '" . (string)$rawBalance . "'\n";
        echo "As float: " . (float)$rawBalance . "\n";
        
        // Test both conversions
        echo "\nIf already human-readable: " . $rawBalance . " CIRX\n";
        echo "If in wei (divide by 10^18): " . bcdiv((string)$rawBalance, '1000000000000000000', 18) . " CIRX\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
