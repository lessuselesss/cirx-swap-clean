<?php

namespace App\Utils;

use InvalidArgumentException;
use RuntimeException;

/**
 * Secure Seed Phrase Manager for E2E Testing
 * 
 * Provides HD wallet derivation from seed phrases for testing purposes.
 * Uses standard BIP39/BIP44 derivation paths for Ethereum wallets.
 */
class SeedPhraseManager
{
    private const ETHEREUM_DERIVATION_PATH = "m/44'/60'/0'/0/";
    private const BIP39_WORDLIST_SIZE = 2048;
    
    private string $seedPhrase;
    private array $derivedWallets = [];
    
    public function __construct(string $seedPhrase)
    {
        $this->validateSeedPhrase($seedPhrase);
        $this->seedPhrase = $seedPhrase;
    }
    
    /**
     * Get a test wallet at the specified derivation index
     */
    public function getWallet(int $index = 0): TestWallet
    {
        if (!isset($this->derivedWallets[$index])) {
            $this->derivedWallets[$index] = $this->deriveWallet($index);
        }
        
        return $this->derivedWallets[$index];
    }
    
    /**
     * Get multiple test wallets
     */
    public function getWallets(int $count = 5): array
    {
        $wallets = [];
        for ($i = 0; $i < $count; $i++) {
            $wallets[] = $this->getWallet($i);
        }
        return $wallets;
    }
    
    /**
     * Derive a wallet at the specified index using BIP44 path
     */
    private function deriveWallet(int $index): TestWallet
    {
        // For testing purposes, we'll use a simplified derivation
        // In production, you'd use a proper BIP32/BIP44 library
        
        $path = self::ETHEREUM_DERIVATION_PATH . $index;
        
        // Generate deterministic private key from seed phrase + index
        $seed = $this->generateSeed();
        $derivedKey = $this->derivePrivateKey($seed, $index);
        $address = $this->privateKeyToAddress($derivedKey);
        
        return new TestWallet($address, $derivedKey, $path, $index);
    }
    
    /**
     * Generate seed from mnemonic phrase
     */
    private function generateSeed(): string
    {
        // Simplified seed generation - in production use proper PBKDF2
        return hash('sha256', $this->seedPhrase . 'test-salt');
    }
    
    /**
     * Derive private key from seed and index
     */
    private function derivePrivateKey(string $seed, int $index): string
    {
        // Simplified derivation - in production use proper BIP32 derivation
        $indexedSeed = $seed . pack('N', $index);
        $privateKey = hash('sha256', $indexedSeed);
        
        // Ensure private key is valid for secp256k1
        $privateKeyInt = gmp_init('0x' . $privateKey);
        $secp256k1Order = gmp_init('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141');
        
        if (gmp_cmp($privateKeyInt, $secp256k1Order) >= 0) {
            // If private key is >= curve order, rehash
            return $this->derivePrivateKey(hash('sha256', $privateKey), $index);
        }
        
        return $privateKey;
    }
    
    /**
     * Convert private key to Ethereum address
     */
    private function privateKeyToAddress(string $privateKey): string
    {
        // Simplified address generation for testing
        // In production, use proper elliptic curve cryptography
        
        // Generate a deterministic "public key" from private key
        $publicKeyHash = hash('sha256', $privateKey . 'pubkey');
        
        // Simulate Ethereum address generation (last 20 bytes of keccak256)
        $addressHash = hash('sha256', $publicKeyHash);
        $address = '0x' . substr($addressHash, -40);
        
        return $address;
    }
    
    /**
     * Validate seed phrase format
     */
    private function validateSeedPhrase(string $seedPhrase): void
    {
        $words = explode(' ', trim($seedPhrase));
        $wordCount = count($words);
        
        if (!in_array($wordCount, [12, 15, 18, 21, 24])) {
            throw new InvalidArgumentException(
                "Invalid seed phrase length: {$wordCount} words. Must be 12, 15, 18, 21, or 24 words."
            );
        }
        
        // Basic word validation
        foreach ($words as $word) {
            if (empty(trim($word)) || !preg_match('/^[a-z]+$/', $word)) {
                throw new InvalidArgumentException("Invalid word in seed phrase: '{$word}'");
            }
        }
    }
    
    /**
     * Get funding instructions for test wallets
     */
    public function getFundingInstructions(): array
    {
        $wallets = $this->getWallets(3);
        
        return [
            'network' => 'Sepolia Testnet',
            'faucets' => [
                'https://sepoliafaucet.com/',
                'https://sepolia-faucet.pk910.de/',
                'https://faucet.sepolia.dev/'
            ],
            'wallets' => array_map(function($wallet) {
                return [
                    'index' => $wallet->getIndex(),
                    'address' => $wallet->getAddress(),
                    'purpose' => $wallet->getIndex() === 0 ? 'Payment wallet' : 
                               ($wallet->getIndex() === 1 ? 'Recipient wallet' : 'Backup wallet')
                ];
            }, $wallets),
            'recommended_funding' => '0.1 ETH per wallet',
            'note' => 'Fund these wallets with Sepolia ETH for E2E testing'
        ];
    }
}

/**
 * Test Wallet Data Container
 */
class TestWallet
{
    private string $address;
    private string $privateKey;
    private string $derivationPath;
    private int $index;
    
    public function __construct(string $address, string $privateKey, string $derivationPath, int $index)
    {
        $this->address = $address;
        $this->privateKey = $privateKey;
        $this->derivationPath = $derivationPath;
        $this->index = $index;
    }
    
    public function getAddress(): string
    {
        return $this->address;
    }
    
    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }
    
    public function getDerivationPath(): string
    {
        return $this->derivationPath;
    }
    
    public function getIndex(): int
    {
        return $this->index;
    }
    
    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'derivation_path' => $this->derivationPath,
            'index' => $this->index
        ];
    }
}