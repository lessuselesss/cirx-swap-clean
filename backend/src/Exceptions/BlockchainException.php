<?php

namespace App\Exceptions;

use Exception;

/**
 * Base blockchain exception
 */
class BlockchainException extends Exception
{
    protected string $blockchainType;
    protected string $operation;
    protected array $context;

    public function __construct(
        string $message = "",
        int $code = 0,
        ?Exception $previous = null,
        string $blockchainType = '',
        string $operation = '',
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->blockchainType = $blockchainType;
        $this->operation = $operation;
        $this->context = $context;
    }

    public function getBlockchainType(): string
    {
        return $this->blockchainType;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create exception for API connection failures
     */
    public static function connectionFailed(
        string $blockchainType,
        string $endpoint,
        string $reason,
        ?Exception $previous = null
    ): self {
        return new self(
            "Failed to connect to {$blockchainType} blockchain at {$endpoint}: {$reason}",
            500,
            $previous,
            $blockchainType,
            'connection',
            ['endpoint' => $endpoint, 'reason' => $reason]
        );
    }

    /**
     * Create exception for transaction not found
     */
    public static function transactionNotFound(
        string $blockchainType,
        string $txHash
    ): self {
        return new self(
            "Transaction {$txHash} not found on {$blockchainType} blockchain",
            404,
            null,
            $blockchainType,
            'get_transaction',
            ['tx_hash' => $txHash]
        );
    }

    /**
     * Create exception for invalid response
     */
    public static function invalidResponse(
        string $blockchainType,
        string $operation,
        string $response,
        ?Exception $previous = null
    ): self {
        return new self(
            "Invalid response from {$blockchainType} blockchain for {$operation}: {$response}",
            422,
            $previous,
            $blockchainType,
            $operation,
            ['response' => $response]
        );
    }

    /**
     * Create exception for insufficient balance
     */
    public static function insufficientBalance(
        string $blockchainType,
        string $walletAddress,
        string $required,
        string $available
    ): self {
        return new self(
            "Insufficient balance in {$walletAddress} on {$blockchainType}. Required: {$required}, Available: {$available}",
            400,
            null,
            $blockchainType,
            'balance_check',
            [
                'wallet_address' => $walletAddress,
                'required' => $required,
                'available' => $available
            ]
        );
    }

    /**
     * Create exception for transaction failures
     */
    public static function transactionFailed(
        string $blockchainType,
        string $txHash,
        string $reason,
        ?Exception $previous = null
    ): self {
        return new self(
            "Transaction {$txHash} failed on {$blockchainType}: {$reason}",
            400,
            $previous,
            $blockchainType,
            'send_transaction',
            ['tx_hash' => $txHash, 'reason' => $reason]
        );
    }
}