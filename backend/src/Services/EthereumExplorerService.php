<?php

namespace App\Services;

/**
 * Ethereum Explorer Service
 * 
 * Centralized service for generating Ethereum/EVM-compatible chain explorer URLs.
 * This replaces 6+ duplicate URL generation implementations throughout the codebase.
 * 
 * Supports: Ethereum, Polygon, BSC, Arbitrum, Optimism, Avalanche + CIRX Protocol
 * 
 * Consolidates logic from:
 * - TransactionStatusController::getEtherscanUrl()
 * - TransactionStatusController::getCircularExplorerUrl()
 * - Frontend URL generation in multiple components
 */
class EthereumExplorerService
{
    /**
     * Get Ethereum/EVM explorer URL for a transaction hash
     */
    public static function getTransactionUrl(string $txHash, string $chain = 'ethereum'): string
    {
        // Handle CIRX/Circular Protocol transactions
        if (strtolower($chain) === 'cirx' || strtolower($chain) === 'circular') {
            return self::getCircularExplorerUrl($txHash);
        }

        // Handle Ethereum/EVM-compatible chains
        return self::getEtherscanUrl($txHash, $chain);
    }

    /**
     * Get Etherscan URL for a transaction hash on various EVM networks
     */
    public static function getEtherscanUrl(string $txHash, string $chain): string
    {
        // For Ethereum chains, determine the correct network based on environment
        $chainKey = strtolower($chain);
        if (in_array($chainKey, ['ethereum', 'eth'])) {
            // Check environment to determine mainnet vs testnet
            $network = $_ENV['ETHEREUM_NETWORK'] ?? 'mainnet';
            $chainKey = $network; // Use the actual network (sepolia, mainnet, etc.)
        }

        $baseUrls = [
            'mainnet' => 'https://etherscan.io/tx/',
            'sepolia' => 'https://sepolia.etherscan.io/tx/',
            'goerli' => 'https://goerli.etherscan.io/tx/',
            'polygon' => 'https://polygonscan.com/tx/',
            'matic' => 'https://polygonscan.com/tx/',
            'bsc' => 'https://bscscan.com/tx/',
            'binance' => 'https://bscscan.com/tx/',
            'binance-smart-chain' => 'https://bscscan.com/tx/',
            'arbitrum' => 'https://arbiscan.io/tx/',
            'optimism' => 'https://optimistic.etherscan.io/tx/',
            'avalanche' => 'https://snowtrace.io/tx/',
            'avax' => 'https://snowtrace.io/tx/'
        ];

        $baseUrl = $baseUrls[$chainKey] ?? $baseUrls['mainnet']; // Default to mainnet

        return $baseUrl . $txHash;
    }

    /**
     * Get Circular Protocol explorer URL for a transaction hash
     */
    public static function getCircularExplorerUrl(string $txHash): string
    {
        // Determine environment for correct explorer
        $environment = $_ENV['APP_ENV'] ?? 'development';
        
        switch ($environment) {
            case 'production':
                return "https://explorer.circular.net/tx/{$txHash}";
            case 'staging':
                return "https://staging-explorer.circular.net/tx/{$txHash}";
            default:
                return "https://sandbox-explorer.circular.net/tx/{$txHash}";
        }
    }

    /**
     * Get Ethereum/EVM address explorer URL for a wallet address
     */
    public static function getAddressUrl(string $address, string $chain = 'ethereum'): string
    {
        // Handle CIRX/Circular Protocol addresses
        if (strtolower($chain) === 'cirx' || strtolower($chain) === 'circular') {
            return self::getCircularAddressUrl($address);
        }

        // Handle Ethereum/EVM-compatible chains
        return self::getEtherscanAddressUrl($address, $chain);
    }

    /**
     * Get Etherscan address URL for wallet addresses
     */
    private static function getEtherscanAddressUrl(string $address, string $chain): string
    {
        $chainKey = strtolower($chain);
        if (in_array($chainKey, ['ethereum', 'eth'])) {
            $network = $_ENV['ETHEREUM_NETWORK'] ?? 'mainnet';
            $chainKey = $network;
        }

        $baseUrls = [
            'mainnet' => 'https://etherscan.io/address/',
            'sepolia' => 'https://sepolia.etherscan.io/address/',
            'goerli' => 'https://goerli.etherscan.io/address/',
            'polygon' => 'https://polygonscan.com/address/',
            'matic' => 'https://polygonscan.com/address/',
            'bsc' => 'https://bscscan.com/address/',
            'binance' => 'https://bscscan.com/address/',
            'binance-smart-chain' => 'https://bscscan.com/address/'
        ];

        $baseUrl = $baseUrls[$chainKey] ?? $baseUrls['mainnet'];
        return $baseUrl . $address;
    }

    /**
     * Get Circular Protocol address explorer URL
     */
    private static function getCircularAddressUrl(string $address): string
    {
        $environment = $_ENV['APP_ENV'] ?? 'development';
        
        switch ($environment) {
            case 'production':
                return "https://explorer.circular.net/address/{$address}";
            case 'staging':
                return "https://staging-explorer.circular.net/address/{$address}";
            default:
                return "https://sandbox-explorer.circular.net/address/{$address}";
        }
    }

    /**
     * Get supported Ethereum/EVM chains for URL generation
     */
    public static function getSupportedChains(): array
    {
        return [
            'ethereum',
            'sepolia', 
            'goerli',
            'polygon',
            'bsc',
            'binance-smart-chain',
            'arbitrum',
            'optimism',
            'avalanche',
            'cirx',
            'circular'
        ];
    }

    /**
     * Check if a chain is supported
     */
    public static function isChainSupported(string $chain): bool
    {
        return in_array(strtolower($chain), self::getSupportedChains());
    }
}