<?php

require_once 'vendor/autoload.php';

echo "Testing different asset formats with production endpoint...\n\n";

$blockchainId = '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2';
$testAddress = '0xe184d1a551b4c0a5a21a90c72e238692c1bb84b5c06b832c37cc0f397ab28443';
$url = 'https://nag.circularlabs.io/NAG.php?cep=Circular_GetWalletBalance_';

// Try different asset values
$assets = [
    'CIRX' => 'CIRX',
    'Asset key' => 'Asset',
    'Lowercase asset' => 'asset', 
    'No asset key' => null,
    'Numeric 0' => '0',
    'Empty string' => ''
];

foreach ($assets as $label => $assetValue) {
    echo "Testing: $label ";
    if ($assetValue !== null) {
        echo "(value: '$assetValue')";
    } else {
        echo "(no asset key in request)";
    }
    echo "\n";
    
    $data = [
        "Blockchain" => $blockchainId,
        "Address" => $testAddress,
        "Version" => "1.0.8"
    ];
    
    // Only add asset key if not null
    if ($assetValue !== null) {
        $data["asset"] = $assetValue;
    }
    
    $options = [
        'http' => [
            'header' => "Content-type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data),
            'timeout' => 5
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    $decoded = json_decode($result);
    
    if ($decoded && isset($decoded->Result)) {
        echo "  Result: " . $decoded->Result;
        if (isset($decoded->Response)) {
            echo " - " . $decoded->Response;
        } elseif (isset($decoded->ERROR)) {
            echo " - " . $decoded->ERROR;
        }
    } else {
        echo "  No response";
    }
    echo "\n\n";
}