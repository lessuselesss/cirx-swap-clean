<?php

require_once 'vendor/autoload.php';

use App\Validators\SwapRequestValidator;

$validator = new SwapRequestValidator();

$testData = [
    'txId' => '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234',
    'paymentChain' => 'ethereum',
    'cirxRecipientAddress' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
    'amountPaid' => '1.0',
    'paymentToken' => 'ETH'
];

$result = $validator->validate($testData);

echo "Validation result:\n";
print_r($result);

echo "\nSupported chains:\n";
print_r($validator->getSupportedChains());

echo "\nSupported tokens:\n";
print_r($validator->getSupportedTokens());