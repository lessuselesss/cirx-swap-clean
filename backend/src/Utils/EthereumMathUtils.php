<?php

namespace App\Utils;

use InvalidArgumentException;

/**
 * Ethereum Math Utilities
 * 
 * Centralized utility class for all Ethereum/EVM token mathematical operations.
 * Consolidates 10+ duplicate BC math implementations throughout the codebase.
 * 
 * Supported tokens:
 * - ETH (18 decimals) - Native Ethereum
 * - USDC (6 decimals) - Centre USD Coin
 * - USDT (6 decimals) - Tether USD
 * 
 * This replaces duplicate math logic in:
 * - PaymentVerificationService
 * - CircularProtocolClient  
 * - BlockchainTestUtils
 * - Various test files
 */
class EthereumMathUtils
{
    /**
     * Token decimal places mapping
     */
    private const TOKEN_DECIMALS = [
        'ETH' => 18,
        'USDC' => 6,
        'USDT' => 6,
    ];

    /**
     * Convert token amount to smallest unit (wei for ETH, units for USDC/USDT)
     * 
     * @param string $amount Token amount (e.g., "1.5")
     * @param string $token Token symbol (ETH, USDC, USDT)
     * @return string Amount in smallest unit
     * @throws InvalidArgumentException If token is not supported
     */
    public static function convertToSmallestUnit(string $amount, string $token): string
    {
        $token = strtoupper($token);
        
        if (!isset(self::TOKEN_DECIMALS[$token])) {
            throw new InvalidArgumentException("Unsupported token: {$token}");
        }

        $decimals = self::TOKEN_DECIMALS[$token];
        $multiplier = bcpow('10', (string)$decimals);
        
        return bcmul($amount, $multiplier, 0); // No decimal places for smallest unit
    }

    /**
     * Convert from smallest unit to token amount
     * 
     * @param string $smallestUnit Amount in smallest unit (wei, etc.)
     * @param string $token Token symbol (ETH, USDC, USDT)
     * @param int $precision Decimal places to return (default: token's natural precision)
     * @return string Token amount
     * @throws InvalidArgumentException If token is not supported
     */
    public static function convertFromSmallestUnit(string $smallestUnit, string $token, ?int $precision = null): string
    {
        $token = strtoupper($token);
        
        if (!isset(self::TOKEN_DECIMALS[$token])) {
            throw new InvalidArgumentException("Unsupported token: {$token}");
        }

        $decimals = self::TOKEN_DECIMALS[$token];
        $divisor = bcpow('10', (string)$decimals);
        
        // Use token's natural precision if not specified
        $precision = $precision ?? $decimals;
        
        return bcdiv($smallestUnit, $divisor, $precision);
    }

    /**
     * Compare two token amounts with proper precision
     * 
     * @param string $amount1 First amount
     * @param string $amount2 Second amount
     * @param string $token Token symbol for decimal precision
     * @return int -1 if amount1 < amount2, 0 if equal, 1 if amount1 > amount2
     * @throws InvalidArgumentException If token is not supported
     */
    public static function compareAmounts(string $amount1, string $amount2, string $token): int
    {
        $token = strtoupper($token);
        
        if (!isset(self::TOKEN_DECIMALS[$token])) {
            throw new InvalidArgumentException("Unsupported token: {$token}");
        }

        $precision = self::TOKEN_DECIMALS[$token];
        return bccomp($amount1, $amount2, $precision);
    }

    /**
     * Add two token amounts with proper precision
     * 
     * @param string $amount1 First amount
     * @param string $amount2 Second amount
     * @param string $token Token symbol for decimal precision
     * @return string Sum of amounts
     * @throws InvalidArgumentException If token is not supported
     */
    public static function addAmounts(string $amount1, string $amount2, string $token): string
    {
        $token = strtoupper($token);
        
        if (!isset(self::TOKEN_DECIMALS[$token])) {
            throw new InvalidArgumentException("Unsupported token: {$token}");
        }

        $precision = self::TOKEN_DECIMALS[$token];
        return bcadd($amount1, $amount2, $precision);
    }

    /**
     * Subtract two token amounts with proper precision
     * 
     * @param string $amount1 First amount (minuend)
     * @param string $amount2 Second amount (subtrahend)
     * @param string $token Token symbol for decimal precision
     * @return string Difference of amounts
     * @throws InvalidArgumentException If token is not supported
     */
    public static function subtractAmounts(string $amount1, string $amount2, string $token): string
    {
        $token = strtoupper($token);
        
        if (!isset(self::TOKEN_DECIMALS[$token])) {
            throw new InvalidArgumentException("Unsupported token: {$token}");
        }

        $precision = self::TOKEN_DECIMALS[$token];
        return bcsub($amount1, $amount2, $precision);
    }

    /**
     * Multiply token amount by a factor with proper precision
     * 
     * @param string $amount Token amount
     * @param string $multiplier Multiplication factor
     * @param string $token Token symbol for decimal precision
     * @return string Product
     * @throws InvalidArgumentException If token is not supported
     */
    public static function multiplyAmount(string $amount, string $multiplier, string $token): string
    {
        $token = strtoupper($token);
        
        if (!isset(self::TOKEN_DECIMALS[$token])) {
            throw new InvalidArgumentException("Unsupported token: {$token}");
        }

        $precision = self::TOKEN_DECIMALS[$token];
        return bcmul($amount, $multiplier, $precision);
    }

    /**
     * Divide token amount by a divisor with proper precision
     * 
     * @param string $amount Token amount
     * @param string $divisor Division factor
     * @param string $token Token symbol for decimal precision
     * @return string Quotient
     * @throws InvalidArgumentException If token is not supported or divisor is zero
     */
    public static function divideAmount(string $amount, string $divisor, string $token): string
    {
        $token = strtoupper($token);
        
        if (!isset(self::TOKEN_DECIMALS[$token])) {
            throw new InvalidArgumentException("Unsupported token: {$token}");
        }

        if (bccomp($divisor, '0', 18) === 0) {
            throw new InvalidArgumentException("Division by zero");
        }

        $precision = self::TOKEN_DECIMALS[$token];
        return bcdiv($amount, $divisor, $precision);
    }

    /**
     * Get decimal places for a token
     * 
     * @param string $token Token symbol
     * @return int Number of decimal places
     * @throws InvalidArgumentException If token is not supported
     */
    public static function getTokenDecimals(string $token): int
    {
        $token = strtoupper($token);
        
        if (!isset(self::TOKEN_DECIMALS[$token])) {
            throw new InvalidArgumentException("Unsupported token: {$token}");
        }

        return self::TOKEN_DECIMALS[$token];
    }

    /**
     * Get all supported tokens
     * 
     * @return array Array of supported token symbols
     */
    public static function getSupportedTokens(): array
    {
        return array_keys(self::TOKEN_DECIMALS);
    }

    /**
     * Check if a token is supported
     * 
     * @param string $token Token symbol to check
     * @return bool True if supported, false otherwise
     */
    public static function isTokenSupported(string $token): bool
    {
        return isset(self::TOKEN_DECIMALS[strtoupper($token)]);
    }

    /**
     * Format token amount for display (removes trailing zeros)
     * 
     * @param string $amount Token amount
     * @param string $token Token symbol for decimal precision
     * @param int|null $maxDecimals Maximum decimal places to display (null = token's natural precision)
     * @return string Formatted amount
     * @throws InvalidArgumentException If token is not supported
     */
    public static function formatAmount(string $amount, string $token, ?int $maxDecimals = null): string
    {
        $token = strtoupper($token);
        
        if (!isset(self::TOKEN_DECIMALS[$token])) {
            throw new InvalidArgumentException("Unsupported token: {$token}");
        }

        $precision = min($maxDecimals ?? self::TOKEN_DECIMALS[$token], self::TOKEN_DECIMALS[$token]);
        
        // Round to specified precision
        $rounded = bcadd($amount, '0', $precision);
        
        // Remove trailing zeros
        return rtrim(rtrim($rounded, '0'), '.');
    }

    /**
     * Validate token amount format
     * 
     * @param string $amount Amount to validate
     * @param string $token Token symbol for validation rules
     * @return bool True if valid, false otherwise
     * @throws InvalidArgumentException If token is not supported
     */
    public static function isValidAmount(string $amount, string $token): bool
    {
        $token = strtoupper($token);
        
        if (!isset(self::TOKEN_DECIMALS[$token])) {
            throw new InvalidArgumentException("Unsupported token: {$token}");
        }

        // Check if it's a valid numeric string
        if (!is_numeric($amount)) {
            return false;
        }

        // Check if it's positive
        if (bccomp($amount, '0', 18) <= 0) {
            return false;
        }

        // Check decimal places don't exceed token precision
        $parts = explode('.', $amount);
        if (count($parts) > 2) {
            return false;
        }

        if (count($parts) === 2) {
            $decimals = strlen($parts[1]);
            if ($decimals > self::TOKEN_DECIMALS[$token]) {
                return false;
            }
        }

        return true;
    }
}