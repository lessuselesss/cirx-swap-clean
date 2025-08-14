<?php

namespace App\Exceptions;

use Exception;

class PaymentVerificationException extends Exception
{
    private ?string $transactionHash;
    private ?string $chain;

    public function __construct(
        string $message = "",
        int $code = 0,
        Exception $previous = null,
        ?string $transactionHash = null,
        ?string $chain = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->transactionHash = $transactionHash;
        $this->chain = $chain;
    }

    public function getTransactionHash(): ?string
    {
        return $this->transactionHash;
    }

    public function getChain(): ?string
    {
        return $this->chain;
    }

    public static function apiError(string $message, ?string $transactionHash = null, ?string $chain = null): self
    {
        return new self("Failed to verify payment: {$message}", 1001, null, $transactionHash, $chain);
    }

    public static function transactionNotFound(string $transactionHash, string $chain): self
    {
        return new self("Transaction not found: {$transactionHash}", 1002, null, $transactionHash, $chain);
    }

    public static function verificationFailed(string $reason, string $transactionHash, string $chain): self
    {
        return new self("Payment verification failed: {$reason}", 1003, null, $transactionHash, $chain);
    }
}