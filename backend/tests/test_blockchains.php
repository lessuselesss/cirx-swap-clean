<?php

require_once 'vendor/autoload.php';

use CircularProtocol\Api\CircularProtocolAPI;

echo "Testing available blockchains...\n";

try {
    $api = new CircularProtocolAPI();
    $api->setNAGURL('https://nag.circularlabs.io/NAG.php?cep=');
    
    echo "Getting available blockchains...\n";
    $blockchains = $api->getBlockchains();
    
    echo "Raw response:\n";
    var_dump($blockchains);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}