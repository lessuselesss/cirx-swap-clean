<?php

namespace App\Services;

class CirxTransferResult
{
    private bool $success;
    private ?string $transactionHash;
    private string $recipientAddress;
    private string $amount;
    private ?string $errorMessage;
    private array $metadata;

    public function __construct(
        bool $success,
        ?string $transactionHash,
        string $recipientAddress,
        string $amount,
        ?string $errorMessage = null,
        array $metadata = []
    ) {
        $this->success = $success;
        $this->transactionHash = $transactionHash;
        $this->recipientAddress = $recipientAddress;
        $this->amount = $amount;
        $this->errorMessage = $errorMessage;
        $this->metadata = $metadata;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getTransactionHash(): ?string
    {
        return $this->transactionHash;
    }

    public function getRecipientAddress(): string
    {
        return $this->recipientAddress;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Create a successful transfer result
     */
    public static function success(
        string $transactionHash,
        string $recipientAddress,
        string $amount,
        array $metadata = []
    ): self {
        return new self(
            true,
            $transactionHash,
            $recipientAddress,
            $amount,
            null,
            $metadata
        );
    }

    /**
     * Create a failed transfer result
     */
    public static function failure(
        string $recipientAddress,
        string $amount,
        string $errorMessage,
        array $metadata = []
    ): self {
        return new self(
            false,
            null,
            $recipientAddress,
            $amount,
            $errorMessage,
            $metadata
        );
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'transactionHash' => $this->transactionHash,
            'recipientAddress' => $this->recipientAddress,
            'amount' => $this->amount,
            'errorMessage' => $this->errorMessage,
            'metadata' => $this->metadata
        ];
    }
}