<?php

namespace App\Utils;

/**
 * Test Wallet Representation
 * 
 * Represents a test wallet derived from seed phrase for E2E testing.
 * Contains wallet address, private key, and basic information.
 */
class TestWallet
{
    private string $address;
    private string $privateKey;
    private int $derivationIndex;
    private string $publicKey;
    private string $mnemonic;
    
    public function __construct(
        string $address,
        string $privateKey,
        int $derivationIndex,
        string $publicKey = '',
        string $mnemonic = ''
    ) {
        $this->address = $address;
        $this->privateKey = $privateKey;
        $this->derivationIndex = $derivationIndex;
        $this->publicKey = $publicKey;
        $this->mnemonic = $mnemonic;
    }
    
    /**
     * Get wallet address
     */
    public function getAddress(): string
    {
        return $this->address;
    }
    
    /**
     * Get private key (for signing transactions)
     */
    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }
    
    /**
     * Get derivation index from seed phrase
     */
    public function getDerivationIndex(): int
    {
        return $this->derivationIndex;
    }
    
    /**
     * Get public key
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
    
    /**
     * Get mnemonic phrase (if available)
     */
    public function getMnemonic(): string
    {
        return $this->mnemonic;
    }
    
    /**
     * Get derivation path for this wallet
     */
    public function getDerivationPath(): string
    {
        return "m/44'/60'/0'/0/{$this->derivationIndex}";
    }
    
    /**
     * Get wallet information as array
     */
    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'derivation_index' => $this->derivationIndex,
            'derivation_path' => $this->getDerivationPath(),
            'public_key' => $this->publicKey ?: 'not_available'
        ];
    }
    
    /**
     * Get wallet purpose description
     */
    public function getPurpose(): string
    {
        return match($this->derivationIndex) {
            0 => 'Project Wallet (receives payments)',
            1 => 'Payment Wallet (sends payments)',
            2 => 'Recipient Wallet (receives CIRX)',
            3 => 'Backup Wallet 1',
            4 => 'Backup Wallet 2',
            default => "Test Wallet #{$this->derivationIndex}"
        };
    }
    
    /**
     * Validate wallet address format
     */
    public function isValidAddress(): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $this->address) === 1;
    }
    
    /**
     * Validate private key format
     */
    public function isValidPrivateKey(): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{64}$/', $this->privateKey) === 1;
    }
    
    /**
     * Get short address for display
     */
    public function getShortAddress(): string
    {
        return substr($this->address, 0, 6) . '...' . substr($this->address, -4);
    }
    
    /**
     * Create a mock wallet for testing
     */
    public static function createMockWallet(int $index = 0): self
    {
        // Generate deterministic mock data based on index
        $addressBytes = str_pad(dechex($index), 40, '0', STR_PAD_LEFT);
        $privateKeyBytes = str_pad(dechex($index * 1000), 64, '0', STR_PAD_LEFT);
        $publicKeyBytes = str_pad(dechex($index * 2000), 64, '0', STR_PAD_LEFT);
        
        return new self(
            address: '0x' . $addressBytes,
            privateKey: '0x' . $privateKeyBytes,
            derivationIndex: $index,
            publicKey: '0x' . $publicKeyBytes,
            mnemonic: 'mock mnemonic for testing purposes only'
        );
    }
    
    /**
     * Create wallet from environment variables (for testing)
     */
    public static function fromEnvironment(int $index = 0): self
    {
        $privateKey = $_ENV["TEST_WALLET_{$index}_PRIVATE_KEY"] ?? '';
        $address = $_ENV["TEST_WALLET_{$index}_ADDRESS"] ?? '';
        
        if (empty($privateKey) || empty($address)) {
            return self::createMockWallet($index);
        }
        
        return new self(
            address: $address,
            privateKey: $privateKey,
            derivationIndex: $index
        );
    }
    
    /**
     * String representation for debugging
     */
    public function __toString(): string
    {
        return sprintf(
            "TestWallet[%d]: %s (%s)",
            $this->derivationIndex,
            $this->getShortAddress(),
            $this->getPurpose()
        );
    }
    
    /**
     * Compare with another wallet
     */
    public function equals(TestWallet $other): bool
    {
        return $this->address === $other->getAddress() &&
               $this->derivationIndex === $other->getDerivationIndex();
    }
    
    /**
     * Export wallet for backup (address only, no private keys)
     */
    public function exportSafe(): array
    {
        return [
            'address' => $this->address,
            'derivation_index' => $this->derivationIndex,
            'derivation_path' => $this->getDerivationPath(),
            'purpose' => $this->getPurpose()
        ];
    }
    
    /**
     * Get funding instructions for this wallet
     */
    public function getFundingInstructions(): array
    {
        return [
            'address' => $this->address,
            'purpose' => $this->getPurpose(),
            'recommended_amount' => '0.1 ETH',
            'faucets' => [
                'https://sepoliafaucet.com/',
                'https://faucet.sepolia.dev/',
                'https://sepolia-faucet.pk910.de/'
            ],
            'instructions' => [
                '1. Visit one of the faucet URLs above',
                '2. Enter wallet address: ' . $this->address,
                '3. Request 0.1 ETH (may take a few minutes)',
                '4. Verify funding with: php bin/run-tests.php check-e2e'
            ]
        ];
    }
}