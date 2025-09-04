<?php

namespace App\Utils;

use InvalidArgumentException;

/**
 * Hash Utilities for Transaction Processing
 * 
 * Consolidated utility class for all hash-related operations including
 * transaction hash validation, hex/decimal conversions, and format validation.
 * 
 * This class replaces the 20+ duplicate hash processing implementations
 * found throughout the codebase to ensure consistent validation and prevent
 * security vulnerabilities like the bogus transaction issue.
 */
class HashUtils
{
    /**
     * Minimum valid transaction hash length (without 0x prefix)
     */
    private const MIN_HASH_LENGTH = 64;

    /**
     * Maximum valid transaction hash length (with 0x prefix)
     */
    private const MAX_HASH_LENGTH = 66;

    /**
     * Valid transaction hash prefix for Ethereum-based chains
     */
    private const VALID_HASH_PREFIX = '0x';

    /**
     * Validate if a string is a proper blockchain transaction hash
     * 
     * @param string $hash The transaction hash to validate
     * @param bool $requirePrefix Whether to require 0x prefix (default: true)
     * @return bool True if hash is valid, false otherwise
     */
    public static function validateTransactionHash(string $hash, bool $requirePrefix = true): bool
    {
        // Check for empty or null hash
        if (empty($hash)) {
            return false;
        }

        // Trim whitespace
        $hash = trim($hash);

        // Auto-detect and handle prefix more elegantly
        $hasPrefix = str_starts_with($hash, self::VALID_HASH_PREFIX);
        
        if ($requirePrefix && !$hasPrefix) {
            return false;
        }

        // Extract hash without prefix regardless of requirement
        $hashWithoutPrefix = $hasPrefix ? substr($hash, 2) : $hash;

        // Check length constraints
        $length = strlen($hashWithoutPrefix);
        if ($length !== self::MIN_HASH_LENGTH) {
            return false;
        }

        // Check if contains only valid hexadecimal characters
        if (!ctype_xdigit($hashWithoutPrefix)) {
            return false;
        }

        // Additional validation: reject obviously fake patterns
        return self::isNotObviousFake($hash);
    }

    /**
     * Convert hexadecimal string to decimal
     * 
     * Note: This is a simple wrapper around PHP's hexdec() that adds input sanitization
     * and validation. For basic hex->decimal conversions, hexdec() would work fine.
     * The main value of this utility class is the hash validation logic that prevents
     * bogus transactions like "failing_payment_1755964752" from bypassing security.
     * 
     * @param string $hex Hexadecimal string (with or without 0x prefix)
     * @return string Decimal string representation
     * @throws InvalidArgumentException If hex string is invalid
     */
    public static function hexToDec(string $hex): string
    {
        $hex = self::sanitizeHexString($hex);
        
        if (!ctype_xdigit($hex)) {
            throw new InvalidArgumentException("Invalid hexadecimal string: {$hex}");
        }

        return (string) hexdec($hex);
    }

    /**
     * Convert decimal to hexadecimal string
     * 
     * @param string|int $decimal Decimal value
     * @param bool $addPrefix Whether to add 0x prefix (default: true)
     * @return string Hexadecimal string representation
     * @throws InvalidArgumentException If decimal value is invalid
     */
    public static function decToHex($decimal, bool $addPrefix = true): string
    {
        if (!is_numeric($decimal)) {
            throw new InvalidArgumentException("Invalid decimal value: {$decimal}");
        }

        $hex = dechex((int) $decimal);
        
        return $addPrefix ? self::VALID_HASH_PREFIX . $hex : $hex;
    }

    /**
     * Sanitize hex string by removing prefix and converting to lowercase
     * 
     * @param string $hex Hexadecimal string
     * @return string Sanitized hex string without prefix
     */
    public static function sanitizeHexString(string $hex): string
    {
        $hex = trim($hex);
        
        if (str_starts_with($hex, self::VALID_HASH_PREFIX)) {
            $hex = substr($hex, 2);
        }

        return strtolower($hex);
    }

    /**
     * Add 0x prefix to hex string if not already present
     * 
     * @param string $hex Hexadecimal string
     * @return string Hex string with 0x prefix
     */
    public static function ensureHexPrefix(string $hex): string
    {
        $hex = trim($hex);
        
        if (!str_starts_with($hex, self::VALID_HASH_PREFIX)) {
            return self::VALID_HASH_PREFIX . $hex;
        }

        return $hex;
    }

    /**
     * Check if two transaction hashes are equal (case insensitive, prefix agnostic)
     * 
     * @param string $hash1 First transaction hash
     * @param string $hash2 Second transaction hash
     * @return bool True if hashes are equal
     */
    public static function hashEquals(string $hash1, string $hash2): bool
    {
        $sanitized1 = self::sanitizeHexString($hash1);
        $sanitized2 = self::sanitizeHexString($hash2);

        return hash_equals($sanitized1, $sanitized2);
    }

    /**
     * Generate a secure random transaction hash for testing
     * 
     * @param bool $addPrefix Whether to add 0x prefix (default: true)
     * @return string Random valid transaction hash
     */
    public static function generateRandomHash(bool $addPrefix = true): string
    {
        $randomBytes = random_bytes(32); // 32 bytes = 64 hex characters
        $hex = bin2hex($randomBytes);

        return $addPrefix ? self::VALID_HASH_PREFIX . $hex : $hex;
    }

    /**
     * Check if hash is not an obviously fake pattern
     * 
     * This method detects common patterns used in fake/test transactions
     * that should not be accepted in production.
     * 
     * @param string $hash Transaction hash to check
     * @return bool True if hash appears legitimate, false if obviously fake
     */
    private static function isNotObviousFake(string $hash): bool
    {
        $sanitized = self::sanitizeHexString($hash);

        // Reject hashes that contain common fake patterns
        $fakePatterns = [
            'failing',          // Like "failing_payment_1755964752"
            'test',             // Like "test_transaction_123"
            'fake',             // Like "fake_hash_456"
            'demo',             // Like "demo_payment_789"
            'mock',             // Like "mock_tx_abc"
            '000000000000',     // Too many zeros
            'aaaaaaaaaaaa',     // Repeated characters
            'bbbbbbbbbbbb',
            'cccccccccccc',
            'dddddddddddd',
            'eeeeeeeeeeee',
            'ffffffffffff',
        ];

        foreach ($fakePatterns as $pattern) {
            if (stripos($sanitized, $pattern) !== false) {
                return false;
            }
        }

        // Reject hashes with too many repeated characters (indicates fake)
        for ($i = 0; $i < 16; $i++) {
            $char = dechex($i);
            $repeated = str_repeat($char, 12); // 12+ repeated characters
            if (stripos($sanitized, $repeated) !== false) {
                return false;
            }
        }

        // Removed timestamp pattern check - legitimate tx hashes can contain digit sequences

        return true;
    }

    /**
     * Get validation error message for invalid hash
     * 
     * @param string $hash The hash that failed validation
     * @return string Human-readable error message
     */
    public static function getValidationError(string $hash): string
    {
        if (empty($hash)) {
            return "Transaction hash cannot be empty";
        }

        $hash = trim($hash);

        if (!str_starts_with($hash, self::VALID_HASH_PREFIX)) {
            return "Transaction hash must start with '0x' prefix";
        }

        $hashWithoutPrefix = substr($hash, 2);
        $length = strlen($hashWithoutPrefix);

        if ($length !== self::MIN_HASH_LENGTH) {
            return "Transaction hash must be exactly 64 characters (got {$length})";
        }

        if (!ctype_xdigit($hashWithoutPrefix)) {
            return "Transaction hash contains invalid characters (must be hexadecimal)";
        }

        if (!self::isNotObviousFake($hash)) {
            return "Transaction hash appears to be fake or test data";
        }

        return "Unknown validation error";
    }
}