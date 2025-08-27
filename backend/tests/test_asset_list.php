<?php

require_once 'vendor/autoload.php';

use CircularProtocol\Api\CircularProtocolAPI;

echo "Testing getAssetList to find valid asset names...\n";

$api = new CircularProtocolAPI();
$api->setNAGURL('https://nag.circularlabs.io/NAG.php?cep=');
$blockchainId = '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2'; // Circular SandBox

try {
    echo "\nCalling getAssetList for Circular SandBox:\n";
    $response = $api->getAssetList($blockchainId);
    echo "Response: ";
    var_dump($response);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}