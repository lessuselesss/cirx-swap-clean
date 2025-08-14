<?php

namespace App\Validators;

class SwapRequestValidator
{
    private array $supportedChains = [
        'ethereum',
        'polygon',
        'solana',
        'binance-smart-chain'
    ];

    private array $supportedTokens = [
        'ETH',
        'USDC',
        'USDT',
        'BNB',
        'MATIC',
        'SOL'
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
     */
    private function isValidTxId(string $txId): bool
    {
        // Basic validation for hex string starting with 0x
        // Ethereum-like transactions: 0x followed by 64 hex characters
        // Solana transactions: base58 string
        
        if (str_starts_with($txId, '0x')) {
            // Ethereum-like transaction hash
            return preg_match('/^0x[a-fA-F0-9]{64}$/', $txId) === 1;
        } else {
            // Solana transaction signature (base58)
            return preg_match('/^[1-9A-HJ-NP-Za-km-z]{87,88}$/', $txId) === 1;
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
            // Solana address (base58)
            return preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address) === 1;
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