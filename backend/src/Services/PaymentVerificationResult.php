<?php

namespace App\Services;

class PaymentVerificationResult
{
    private bool $valid;
    private string $transactionHash;
    private string $actualAmount;
    private string $recipientAddress;
    private int $confirmations;
    private ?string $errorMessage;
    private array $metadata;

    public function __construct(
        bool $valid,
        string $transactionHash,
        string $actualAmount = '0',
        string $recipientAddress = '',
        int $confirmations = 0,
        ?string $errorMessage = null,
        array $metadata = []
    ) {
        $this->valid = $valid;
        $this->transactionHash = $transactionHash;
        $this->actualAmount = $actualAmount;
        $this->recipientAddress = $recipientAddress;
        $this->confirmations = $confirmations;
        $this->errorMessage = $errorMessage;
        $this->metadata = $metadata;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getTransactionHash(): string
    {
        return $this->transactionHash;
    }

    /**
     * Alias for getTransactionHash() for integration tests
     */
    public function getTransactionId(): string
    {
        return $this->transactionHash;
    }

    /**
     * Get status string based on validation result
     */
    public function getStatus(): string
    {
        return $this->valid ? 'verified' : 'failed';
    }

    public function getActualAmount(): string
    {
        return $this->actualAmount;
    }

    public function getRecipientAddress(): string
    {
        return $this->recipientAddress;
    }

    public function getConfirmations(): int
    {
        return $this->confirmations;
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
     * Create a successful verification result
     */
    public static function success(
        string $transactionHash,
        string $actualAmount,
        string $recipientAddress,
        int $confirmations,
        array $metadata = []
    ): self {
        return new self(
            true,
            $transactionHash,
            $actualAmount,
            $recipientAddress,
            $confirmations,
            null,
            $metadata
        );
    }

    /**
     * Create a failed verification result
     */
    public static function failure(
        string $transactionHash,
        string $errorMessage,
        array $metadata = []
    ): self {
        return new self(
            false,
            $transactionHash,
            '0',
            '',
            0,
            $errorMessage,
            $metadata
        );
    }

    /**
     * Create an invalid verification result (alias for failure)
     */
    public static function invalid(
        string $transactionHash,
        string $errorMessage,
        array $metadata = []
    ): self {
        return self::failure($transactionHash, $errorMessage, $metadata);
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'transactionHash' => $this->transactionHash,
            'actualAmount' => $this->actualAmount,
            'recipientAddress' => $this->recipientAddress,
            'confirmations' => $this->confirmations,
            'errorMessage' => $this->errorMessage,
            'metadata' => $this->metadata
        ];
    }
}