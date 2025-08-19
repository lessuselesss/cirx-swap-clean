<?php

namespace App\Blockchain;

use App\Blockchain\Exceptions\BlockchainException;
use App\Services\LoggerService;
use Psr\Log\LoggerInterface;

/**
 * Blockchain Client Factory
 * 
 * Creates and manages blockchain client instances for different networks
 */
class BlockchainClientFactory
{
    private array $clients = [];
    private LoggerInterface $logger;
    private array $config;

    public function __construct(array $config = [], ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger ?? LoggerService::getLogger('blockchain');
    }

    /**
     * Get Ethereum blockchain client
     */
    public function getEthereumClient(string $network = 'mainnet'): EthereumBlockchainClient
    {
        $key = "ethereum_{$network}";
        
        if (!isset($this->clients[$key])) {
            $this->clients[$key] = $this->createEthereumClient($network);
        }

        return $this->clients[$key];
    }

    /**
     * Get CIRX blockchain client
     */
    public function getCirxClient(): CirxBlockchainClient
    {
        if (!isset($this->clients['cirx'])) {
            $this->clients['cirx'] = $this->createCirxClient();
        }

        return $this->clients['cirx'];
    }

    /**
     * Get client by chain name
     */
    public function getClientByChain(string $chain): BlockchainClientInterface
    {
        return match (strtolower($chain)) {
            'ethereum', 'eth' => $this->getEthereumClient('mainnet'),
            'polygon', 'matic' => $this->getEthereumClient('polygon'),
            'binance-smart-chain', 'bsc' => $this->getEthereumClient('bsc'),
            'goerli' => $this->getEthereumClient('goerli'),
            'sepolia' => $this->getEthereumClient('sepolia'),
            'cirx', 'circular' => $this->getCirxClient(),
            default => throw new BlockchainException(
                "Unsupported blockchain: {$chain}",
                400,
                null,
                $chain,
                'get_client'
            )
        };
    }

    /**
     * Create Ethereum client for specific network
     */
    private function createEthereumClient(string $network): EthereumBlockchainClient
    {
        $config = $this->getNetworkConfig($network);

        return new EthereumBlockchainClient(
            rpcUrl: $config['rpc_url'],
            chainId: $config['chain_id'],
            networkName: $network,
            backupRpcUrl: $config['backup_rpc_url'] ?? null,
            tokenContracts: $config['token_contracts'] ?? [],
            logger: $this->logger
        );
    }

    /**
     * Create CIRX blockchain client
     */
    private function createCirxClient(): CirxBlockchainClient
    {
        $config = $this->getCirxConfig();

        return new CirxBlockchainClient(
            rpcUrl: $config['rpc_url'],
            cirxWalletAddress: $config['wallet_address'],
            cirxPrivateKey: $config['private_key'] ?? null,
            cirxDecimals: $config['decimals'] ?? 18,
            backupRpcUrl: $config['backup_rpc_url'] ?? null,
            logger: $this->logger
        );
    }

    /**
     * Get network configuration
     */
    private function getNetworkConfig(string $network): array
    {
        $envPrefix = strtoupper($network);

        // Handle special network names
        if ($network === 'polygon') {
            $envPrefix = 'POLYGON';
        } elseif ($network === 'bsc') {
            $envPrefix = 'BSC';
        } elseif ($network === 'mainnet') {
            $envPrefix = 'ETHEREUM';
        }

        $config = [
            'rpc_url' => $_ENV["{$envPrefix}_RPC_URL"] ?? $this->getDefaultRpcUrl($network),
            'backup_rpc_url' => $_ENV["{$envPrefix}_RPC_URL_BACKUP"] ?? null,
            'chain_id' => (int)($_ENV["{$envPrefix}_CHAIN_ID"] ?? $this->getDefaultChainId($network)),
            // Private key configuration removed - read-only client for payment verification
        ];

        // Add token contracts for Ethereum networks
        if (in_array($network, ['mainnet', 'ethereum', 'goerli', 'sepolia'])) {
            $config['token_contracts'] = [
                strtolower($_ENV['USDC_CONTRACT_ADDRESS'] ?? '0xA0b86a33E6441e8532B8aE1F8A0b86a33E644122') => [
                    'symbol' => 'USDC',
                    'decimals' => (int)($_ENV['USDC_DECIMALS'] ?? 6)
                ],
                strtolower($_ENV['USDT_CONTRACT_ADDRESS'] ?? '0xdAC17F958D2ee523a2206206994597C13D831ec7') => [
                    'symbol' => 'USDT', 
                    'decimals' => (int)($_ENV['USDT_DECIMALS'] ?? 6)
                ],
            ];
        }

        return $config;
    }

    /**
     * Get CIRX blockchain configuration
     */
    private function getCirxConfig(): array
    {
        return [
            'rpc_url' => $_ENV['CIRX_NAG_URL'] ?? 'http://localhost:8545',
            'backup_rpc_url' => $_ENV['CIRX_NAG_URL_BACKUP'] ?? null,
            'wallet_address' => $_ENV['CIRX_WALLET_ADDRESS'] ?? '',
            'private_key' => $_ENV['CIRX_WALLET_PRIVATE_KEY'] ?? null,
            'decimals' => (int)($_ENV['CIRX_DECIMALS'] ?? 18),
        ];
    }

    /**
     * Get default RPC URL for network
     */
    private function getDefaultRpcUrl(string $network): string
    {
        return match ($network) {
            'mainnet', 'ethereum' => 'https://eth-mainnet.alchemyapi.io/v2/demo',
            'goerli' => 'https://eth-goerli.alchemyapi.io/v2/demo',
            'sepolia' => 'https://eth-sepolia.alchemyapi.io/v2/demo',
            'polygon' => 'https://polygon-rpc.com',
            'bsc' => 'https://bsc-dataseed1.binance.org',
            default => throw new BlockchainException(
                "No default RPC URL available for network: {$network}",
                500,
                null,
                $network,
                'get_default_rpc'
            )
        };
    }

    /**
     * Get default chain ID for network
     */
    private function getDefaultChainId(string $network): int
    {
        return match ($network) {
            'mainnet', 'ethereum' => 1,
            'goerli' => 5,
            'sepolia' => 11155111,
            'polygon' => 137,
            'bsc' => 56,
            default => throw new BlockchainException(
                "No default chain ID available for network: {$network}",
                500,
                null,
                $network,
                'get_default_chain_id'
            )
        };
    }

    /**
     * Clear all cached clients (useful for testing)
     */
    public function clearCache(): void
    {
        $this->clients = [];
    }

    /**
     * Get all available networks
     */
    public function getAvailableNetworks(): array
    {
        return [
            'ethereum' => ['name' => 'Ethereum Mainnet', 'chain_id' => 1],
            'goerli' => ['name' => 'Ethereum Goerli Testnet', 'chain_id' => 5],
            'sepolia' => ['name' => 'Ethereum Sepolia Testnet', 'chain_id' => 11155111],
            'polygon' => ['name' => 'Polygon Mainnet', 'chain_id' => 137],
            'bsc' => ['name' => 'Binance Smart Chain', 'chain_id' => 56],
            'cirx' => ['name' => 'Circular Protocol', 'chain_id' => 9999], // Placeholder
        ];
    }

    /**
     * Health check for all configured clients
     */
    public function healthCheck(): array
    {
        $results = [];

        foreach ($this->getAvailableNetworks() as $network => $info) {
            try {
                $client = $this->getClientByChain($network);
                $results[$network] = [
                    'healthy' => $client->isHealthy(),
                    'name' => $info['name'],
                    'chain_id' => $info['chain_id']
                ];
            } catch (\Exception $e) {
                $results[$network] = [
                    'healthy' => false,
                    'name' => $info['name'],
                    'chain_id' => $info['chain_id'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}