<?php

namespace App\Exceptions;

use Exception;

class CirxTransferException extends Exception
{
    private ?string $recipientAddress;
    private ?string $amount;

    public function __construct(
        string $message = "",
        int $code = 0,
        Exception $previous = null,
        ?string $recipientAddress = null,
        ?string $amount = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->recipientAddress = $recipientAddress;
        $this->amount = $amount;
    }

    public function getRecipientAddress(): ?string
    {
        return $this->recipientAddress;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public static function transferFailed(string $message, string $recipientAddress, string $amount): self
    {
        return new self("CIRX transfer failed: {$message}", 2001, null, $recipientAddress, $amount);
    }

    public static function insufficientBalance(string $recipientAddress, string $requestedAmount, string $availableBalance): self
    {
        return new self(
            "Insufficient CIRX balance: requested {$requestedAmount}, available {$availableBalance}",
            2002,
            null,
            $recipientAddress,
            $requestedAmount
        );
    }

    public static function walletNotConfigured(): self
    {
        return new self("CIRX wallet not configured or private key not available", 2003);
    }

    public static function invalidAddress(string $address): self
    {
        return new self("Invalid Circular Protocol address format: {$address}", 2004, null, $address);
    }

    public static function networkError(string $message, string $recipientAddress, string $amount): self
    {
        return new self("Network error during CIRX transfer: {$message}", 2005, null, $recipientAddress, $amount);
    }
}