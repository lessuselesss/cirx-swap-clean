<?php

namespace App\Blockchain;

use App\Blockchain\Exceptions\BlockchainException;

/**
 * Blockchain Client Interface
 * 
 * Defines the standard interface for all blockchain clients
 */
interface BlockchainClientInterface
{
    /**
     * Get transaction by hash
     */
    public function getTransaction(string $txHash): ?array;

    /**
     * Get transaction receipt by hash
     */
    public function getTransactionReceipt(string $txHash): ?array;

    /**
     * Get current block number
     */
    public function getBlockNumber(): int;

    /**
     * Get block by number
     */
    public function getBlock(int $blockNumber): ?array;

    /**
     * Get token balance for an address
     */
    public function getTokenBalance(string $tokenAddress, string $walletAddress): string;

    /**
     * Get native token balance for an address
     */
    public function getNativeBalance(string $walletAddress): string;

    // Transaction sending methods removed - backend is read-only for client-side chains
    // Only CIRX transfers are supported server-side

    /**
     * Check if the blockchain client is healthy
     */
    public function isHealthy(): bool;

    /**
     * Get the chain ID
     */
    public function getChainId(): int;

    /**
     * Get the network name
     */
    public function getNetworkName(): string;

    /**
     * Get transaction confirmation count
     */
    public function getTransactionConfirmations(string $txHash): int;
}