<?php

require_once 'vendor/autoload.php';

echo "Testing sandbox endpoint directly...\n\n";

$blockchainId = '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2'; // Circular SandBox
$testAddress = '0xe184d1a551b4c0a5a21a90c72e238692c1bb84b5c06b832c37cc0f397ab28443';

// Test the production endpoint that CircularProtocolAPI uses
$data1 = [
    "Blockchain" => $blockchainId,
    "Address" => $testAddress,
    "asset" => "CIRX",
    "Version" => "1.0.8"
];

$url1 = 'https://nag.circularlabs.io/NAG.php?cep=Circular_GetWalletBalance_';

echo "Testing production endpoint: $url1\n";
$options1 = [
    'http' => [
        'header' => "Content-type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($data1)
    ]
];
$context1 = stream_context_create($options1);
$result1 = @file_get_contents($url1, false, $context1);
echo "Result: ";
var_dump(json_decode($result1));

echo "\n\n=================================\n\n";

// Test the sandbox-specific endpoint from DebugController
$data2 = [
    "Blockchain" => $blockchainId,
    "Address" => $testAddress,
    "asset" => "CIRX",
    "Version" => "1.0.8"
];

$url2 = 'https://nag.circularlabs.io/NAG.php?cep=Sandbox_Account_Balance';

echo "Testing sandbox endpoint: $url2\n";
$options2 = [
    'http' => [
        'header' => "Content-type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($data2)
    ]
];
$context2 = stream_context_create($options2);
$result2 = @file_get_contents($url2, false, $context2);
echo "Result: ";
var_dump(json_decode($result2));