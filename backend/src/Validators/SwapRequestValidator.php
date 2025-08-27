<?php

namespace App\Validators;

class SwapRequestValidator
{
    private array $supportedChains = [
        'ethereum',
        'polygon',
        'binance-smart-chain'
    ];

    private array $supportedTokens = [
        'ETH',
        'USDC',
        'USDT',
        'BNB',
        'MATIC'
    ];

    /**
     * Validate swap request data
     */
    public function validate(?array $data): array
    {
        $errors = [];

        if (!$data) {
            return ['valid' => false, 'errors' => ['Request body is required']];
        }

        // Validate txId
        if (!isset($data['txId']) || empty($data['txId'])) {
            $errors['txId'] = 'Transaction ID is required';
        } elseif (!$this->isValidTxId($data['txId'])) {
            $errors['txId'] = 'Invalid transaction ID format';
        }

        // Validate paymentChain
        if (!isset($data['paymentChain']) || empty($data['paymentChain'])) {
            $errors['paymentChain'] = 'Payment chain is required';
        } elseif (!in_array($data['paymentChain'], $this->supportedChains)) {
            $errors['paymentChain'] = 'Unsupported payment chain';
        }

        // Validate cirxRecipientAddress
        if (!isset($data['cirxRecipientAddress']) || empty($data['cirxRecipientAddress'])) {
            $errors['cirxRecipientAddress'] = 'CIRX recipient address is required';
        } elseif (!$this->isValidAddress($data['cirxRecipientAddress'])) {
            $errors['cirxRecipientAddress'] = 'Invalid CIRX recipient address format';
        }

        // Validate amountPaid
        if (!isset($data['amountPaid']) || $data['amountPaid'] === '') {
            $errors['amountPaid'] = 'Amount paid is required';
        } elseif (!is_numeric($data['amountPaid']) || (float)$data['amountPaid'] <= 0) {
            $errors['amountPaid'] = 'Amount paid must be a positive number';
        }

        // Validate paymentToken
        if (!isset($data['paymentToken']) || empty($data['paymentToken'])) {
            $errors['paymentToken'] = 'Payment token is required';
        } elseif (!in_array($data['paymentToken'], $this->supportedTokens)) {
            $errors['paymentToken'] = 'Unsupported payment token';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate transaction ID format
     * Only supports Ethereum-style 0x-prefixed transaction hashes
     */
    private function isValidTxId(string $txId): bool
    {
        if (str_starts_with($txId, '0x')) {
            // Use HashUtils for comprehensive validation (prevents bogus patterns)
            return \App\Utils\HashUtils::validateTransactionHash($txId, true);
        } else {
            // Non-0x hashes not supported (removes security bypass)
            return false;
        }
    }

    /**
     * Validate address format
     */
    private function isValidAddress(string $address): bool
    {
        // Validate different address formats
        
        if (str_starts_with($address, '0x')) {
            // Check if it's a standard EVM address (40 hex chars after 0x)
            if (preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1) {
                return true;
            }
            // Check if it's a Circular Protocol address (64 hex chars after 0x)
            if (preg_match('/^0x[a-fA-F0-9]{64}$/', $address) === 1) {
                return true;
            }
            return false;
        } else {
            // Non-0x addresses not supported
            return false;
        }
    }

    /**
     * Get supported chains
     */
    public function getSupportedChains(): array
    {
        return $this->supportedChains;
    }

    /**
     * Get supported tokens
     */
    public function getSupportedTokens(): array
    {
        return $this->supportedTokens;
    }
}