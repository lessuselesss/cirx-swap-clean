<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Blockchain API Client for payment verification
 * This is separate from frontend wallet connections (AppKit/WalletKit)
 * Used for backend verification of transaction details
 */
class BlockchainApiClient
{
    private Client $httpClient;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
        
        $this->config = array_merge([
            'ethereum' => [
                'rpc_url' => $_ENV['ETHEREUM_RPC_URL'] ?? 'https://mainnet.infura.io/v3/your-project-id',
                'etherscan_api' => $_ENV['ETHERSCAN_API_KEY'] ?? null,
                'explorer_url' => $this->getEtherscanUrl($_ENV['ETHEREUM_NETWORK'] ?? 'mainnet'),
                'network' => $_ENV['ETHEREUM_NETWORK'] ?? 'mainnet',
                'required_confirmations' => 12,
            ],
            'sepolia' => [
                'rpc_url' => $_ENV['SEPOLIA_RPC_URL'] ?? $_ENV['ETHEREUM_RPC_URL'] ?? 'https://sepolia.infura.io/v3/your-project-id',
                'etherscan_api' => $_ENV['ETHERSCAN_API_KEY'] ?? null,
                'explorer_url' => $this->getEtherscanUrl('sepolia'),
                'network' => 'sepolia',
                'required_confirmations' => 3,
            ],
            'goerli' => [
                'rpc_url' => $_ENV['GOERLI_RPC_URL'] ?? 'https://goerli.infura.io/v3/your-project-id',
                'etherscan_api' => $_ENV['ETHERSCAN_API_KEY'] ?? null,
                'explorer_url' => $this->getEtherscanUrl('goerli'),
                'network' => 'goerli',
                'required_confirmations' => 3,
            ],
            'polygon' => [
                'rpc_url' => $_ENV['POLYGON_RPC_URL'] ?? 'https://polygon-mainnet.infura.io/v3/your-project-id',
                'etherscan_api' => $_ENV['POLYGONSCAN_API_KEY'] ?? null,
                'explorer_url' => 'https://api.polygonscan.com/api',
                'network' => 'polygon',
                'required_confirmations' => 20,
            ],
            'solana' => [
                'rpc_url' => $_ENV['SOLANA_RPC_URL'] ?? 'https://api.mainnet-beta.solana.com',
                'network' => 'solana',
                'required_confirmations' => 30,
            ],
        ], $config);
    }

    /**
     * Get transaction details from blockchain
     * Used for verifying payments (read-only, no wallet needed)
     */
    public function getTransaction(string $txHash, string $chain): ?array
    {
        switch (strtolower($chain)) {
            case 'ethereum':
            case 'polygon':
                return $this->getEvmTransaction($txHash, $chain);
            case 'solana':
                return $this->getSolanaTransaction($txHash);
            default:
                throw new \InvalidArgumentException("Unsupported chain: {$chain}");
        }
    }

    /**
     * Get EVM-based transaction (Ethereum, Polygon, etc.)
     */
    private function getEvmTransaction(string $txHash, string $chain): ?array
    {
        $chainConfig = $this->config[strtolower($chain)] ?? null;
        if (!$chainConfig) {
            throw new \InvalidArgumentException("Chain config not found: {$chain}");
        }

        // Try Etherscan-like API first (more detailed info)
        if (!empty($chainConfig['etherscan_api'])) {
            $explorerData = $this->getTransactionFromExplorer($txHash, $chainConfig);
            if ($explorerData) {
                return $explorerData;
            }
        }

        // Fallback to RPC endpoint
        return $this->getTransactionFromRpc($txHash, $chainConfig);
    }

    /**
     * Get transaction from Etherscan-like explorer API
     */
    private function getTransactionFromExplorer(string $txHash, array $chainConfig): ?array
    {
        try {
            $url = $chainConfig['explorer_url'] . '?' . http_build_query([
                'module' => 'proxy',
                'action' => 'eth_getTransactionByHash',
                'txhash' => $txHash,
                'apikey' => $chainConfig['etherscan_api']
            ]);

            $response = $this->httpClient->get($url);
            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['result']) || !$data['result']) {
                return null;
            }

            $tx = $data['result'];
            
            // Get transaction receipt for status and logs
            $receiptUrl = $chainConfig['explorer_url'] . '?' . http_build_query([
                'module' => 'proxy',
                'action' => 'eth_getTransactionReceipt',
                'txhash' => $txHash,
                'apikey' => $chainConfig['etherscan_api']
            ]);

            $receiptResponse = $this->httpClient->get($receiptUrl);
            $receiptData = json_decode($receiptResponse->getBody()->getContents(), true);
            $receipt = $receiptData['result'] ?? [];

            // Get latest block number for confirmations
            $latestBlockUrl = $chainConfig['explorer_url'] . '?' . http_build_query([
                'module' => 'proxy',
                'action' => 'eth_blockNumber',
                'apikey' => $chainConfig['etherscan_api']
            ]);

            $latestResponse = $this->httpClient->get($latestBlockUrl);
            $latestData = json_decode($latestResponse->getBody()->getContents(), true);
            $latestBlock = hexdec($latestData['result'] ?? '0x0');

            $blockNumber = hexdec($tx['blockNumber'] ?? '0x0');
            $confirmations = $blockNumber > 0 ? ($latestBlock - $blockNumber) + 1 : 0;

            return [
                'hash' => $tx['hash'],
                'status' => ($receipt['status'] ?? '0x1') === '0x1' ? 'confirmed' : 'failed',
                'confirmations' => $confirmations,
                'blockNumber' => $blockNumber,
                'to' => strtolower($tx['to'] ?? ''),
                'from' => strtolower($tx['from'] ?? ''),
                'value' => $tx['value'] ?? '0x0', // ETH value in hex wei
                'gas' => hexdec($tx['gas'] ?? '0x0'),
                'gasPrice' => $tx['gasPrice'] ?? '0x0',
                'logs' => $receipt['logs'] ?? [], // For ERC20 token transfers
            ];

        } catch (GuzzleException $e) {
            error_log("Explorer API error for {$txHash}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get transaction from RPC endpoint (fallback)
     */
    private function getTransactionFromRpc(string $txHash, array $chainConfig): ?array
    {
        try {
            $response = $this->httpClient->post($chainConfig['rpc_url'], [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getTransactionByHash',
                    'params' => [$txHash],
                    'id' => 1
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['result']) || !$data['result']) {
                return null;
            }

            $tx = $data['result'];
            
            // Basic transaction data (limited compared to explorer API)
            return [
                'hash' => $tx['hash'],
                'status' => 'pending', // RPC doesn't provide status, need receipt
                'confirmations' => 0, // Would need latest block number
                'blockNumber' => hexdec($tx['blockNumber'] ?? '0x0'),
                'to' => strtolower($tx['to'] ?? ''),
                'from' => strtolower($tx['from'] ?? ''),
                'value' => $tx['value'] ?? '0x0',
                'gas' => hexdec($tx['gas'] ?? '0x0'),
                'gasPrice' => $tx['gasPrice'] ?? '0x0',
            ];

        } catch (GuzzleException $e) {
            error_log("RPC error for {$txHash}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get Solana transaction
     */
    private function getSolanaTransaction(string $signature): ?array
    {
        try {
            $response = $this->httpClient->post($this->config['solana']['rpc_url'], [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getTransaction',
                    'params' => [
                        $signature,
                        ['encoding' => 'json', 'maxSupportedTransactionVersion' => 0]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['result']) || !$data['result']) {
                return null;
            }

            $tx = $data['result'];
            $meta = $tx['meta'] ?? [];

            // Parse Solana transaction for SOL transfers
            $instructions = [];
            if (isset($tx['transaction']['message']['instructions'])) {
                foreach ($tx['transaction']['message']['instructions'] as $instruction) {
                    // Parse system program transfers (native SOL)
                    $instructions[] = $this->parseSolanaInstruction($instruction, $tx);
                }
            }

            return [
                'signature' => $signature,
                'status' => isset($meta['err']) ? 'failed' : 'confirmed',
                'confirmations' => $this->getSolanaConfirmations($tx['slot'] ?? 0),
                'slot' => $tx['slot'] ?? 0,
                'instructions' => $instructions,
                'fee' => $meta['fee'] ?? 0,
            ];

        } catch (GuzzleException $e) {
            error_log("Solana RPC error for {$signature}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse Solana instruction to extract transfer info
     */
    private function parseSolanaInstruction(array $instruction, array $tx): array
    {
        // This is a simplified parser - would need more sophisticated parsing for all cases
        return [
            'type' => 'unknown',
            'programId' => $instruction['programIdIndex'] ?? null,
            'accounts' => $instruction['accounts'] ?? [],
            'data' => $instruction['data'] ?? '',
        ];
    }

    /**
     * Get Solana confirmations (simplified)
     */
    private function getSolanaConfirmations(int $slot): int
    {
        try {
            $response = $this->httpClient->post($this->config['solana']['rpc_url'], [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getSlot',
                    'params' => []
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $currentSlot = $data['result'] ?? 0;

            return max(0, $currentSlot - $slot);
            
        } catch (GuzzleException $e) {
            return 0;
        }
    }

    /**
     * Get required confirmations for a chain
     */
    public function getRequiredConfirmations(string $chain): int
    {
        return $this->config[strtolower($chain)]['required_confirmations'] ?? 12;
    }

    /**
     * Get Etherscan API URL for the specified network
     * Based on official Etherscan documentation: https://docs.etherscan.io/
     */
    private function getEtherscanUrl(string $network = 'mainnet'): string
    {
        $urls = [
            'mainnet' => 'https://api.etherscan.io/api',
            'sepolia' => 'https://api-sepolia.etherscan.io/api',
            'goerli' => 'https://api-goerli.etherscan.io/api',
            'holesky' => 'https://api-holesky.etherscan.io/api',
            'hoodi' => 'https://api-hoodi.etherscan.io/api',
        ];

        return $urls[strtolower($network)] ?? $urls['mainnet'];
    }
}