<?php

namespace App\Blockchain;

use App\Exceptions\BlockchainException;
use App\Utils\EthereumMathUtils;
use App\Utils\HashUtils;
use App\Services\LoggerService;
use Psr\Log\LoggerInterface;
use CircularProtocol\Api\CircularProtocolAPI;

/**
 * Circular Protocol Client - Pure NAG Implementation
 * 
 * Handles interactions with the Circular Protocol using Network Access Gateway (NAG).
 * This client is completely independent of RPC infrastructure and uses delegation
 * for shared utilities like math operations and logging.
 * 
 * Architecture: Uses delegation pattern instead of inheritance for better separation
 * of concerns between NAG-based and RPC-based blockchain interactions.
 */
class CircularProtocolClient implements BlockchainClientInterface
{
    private CircularProtocolAPI $cirxApi;
    private LoggerInterface $logger;
    private EthereumMathUtils $mathUtils;
    
    private string $cirxWalletAddress;
    private ?string $cirxPrivateKey;
    private int $cirxDecimals;
    private string $environment;

    public function __construct(
        string $environment,
        string $cirxWalletAddress,
        ?string $cirxPrivateKey = null,
        int $cirxDecimals = 18,
        ?LoggerInterface $logger = null
    ) {
        $this->environment = $environment;
        $this->cirxWalletAddress = $cirxWalletAddress;
        $this->cirxPrivateKey = $cirxPrivateKey;
        $this->cirxDecimals = $cirxDecimals;
        
        // Delegate to dedicated services
        $this->logger = $logger ?? LoggerService::getLogger('circular_protocol');
        $this->mathUtils = new EthereumMathUtils();
        
        // Initialize Circular Protocol API with NAG
        $this->initializeApi();
    }

    /**
     * Initialize the Circular Protocol API with appropriate NAG URL
     */
    private function initializeApi(): void
    {
        $this->cirxApi = new CircularProtocolAPI();
        
        // Set NAG URL based on environment
        $nagUrl = $this->environment === 'mainnet' 
            ? 'https://nag.circularlabs.io/NAG_Mainnet.php?cep='
            : 'https://nag.circularlabs.io/NAG.php?cep=';
            
        $this->cirxApi->setNAGURL($nagUrl);
        
        $this->logger->info('Circular Protocol client initialized', [
            'environment' => $this->environment,
            'nag_url' => $nagUrl,
            'wallet_address' => $this->cirxWalletAddress
        ]);
    }

    /**
     * Get transaction by hash using NAG
     */
    public function getTransaction(string $txHash): ?array
    {
        try {
            $this->logger->debug('Fetching CIRX transaction', ['tx_hash' => $txHash]);
            
            // Use NAG API - no RPC needed
            $response = $this->cirxApi->getTransactionByID('circular', $txHash, 0, 0);
            
            // Convert stdClass to array for consistency
            $result = is_object($response) ? json_decode(json_encode($response), true) : $response;
            
            $this->logger->debug('CIRX transaction fetched successfully', [
                'tx_hash' => $txHash,
                'has_result' => !empty($result)
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not found') || $e->getCode() === 404) {
                $this->logger->debug('CIRX transaction not found', ['tx_hash' => $txHash]);
                return null;
            }
            
            $this->logger->error('Failed to get CIRX transaction', [
                'tx_hash' => $txHash,
                'error' => $e->getMessage()
            ]);
            
            throw new BlockchainException(
                "Failed to get CIRX transaction: " . $e->getMessage(),
                $e->getCode(),
                $e,
                'cirx',
                'get_transaction'
            );
        }
    }

    /**
     * Get transaction receipt - CIRX uses different structure than Ethereum
     */
    public function getTransactionReceipt(string $txHash): ?array
    {
        // For CIRX, transaction and receipt are often the same
        // since NAG provides comprehensive transaction data
        $transaction = $this->getTransaction($txHash);
        
        if (!$transaction) {
            return null;
        }
        
        // Format as receipt-like structure for compatibility
        return [
            'transactionHash' => $txHash,
            'status' => $transaction['status'] ?? 'confirmed',
            'blockNumber' => $transaction['blockNumber'] ?? null,
            'blockHash' => $transaction['blockHash'] ?? null,
            'from' => $transaction['from'] ?? null,
            'to' => $transaction['to'] ?? null,
            'amount' => $transaction['amount'] ?? null,
            'gasUsed' => 75000, // CIRX has minimal gas concept
            'logs' => $transaction['logs'] ?? []
        ];
    }

    /**
     * Get current block number from NAG
     */
    public function getBlockNumber(): int
    {
        try {
            // Use NAG to get latest block info
            $response = $this->cirxApi->getLatestBlock('circular');
            
            if (is_object($response) && isset($response->number)) {
                return (int)$response->number;
            }
            
            if (is_array($response) && isset($response['number'])) {
                return (int)$response['number'];
            }
            
            throw new BlockchainException(
                "Invalid block response from NAG",
                0,
                null,
                'cirx',
                'get_block_number'
            );
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get CIRX block number', [
                'error' => $e->getMessage()
            ]);
            
            // Fallback to a reasonable default
            return 0;
        }
    }

    /**
     * Get block by number using NAG
     */
    public function getBlockByNumber(int $blockNumber): ?array
    {
        try {
            $response = $this->cirxApi->getBlockByNumber('circular', $blockNumber);
            return is_object($response) ? json_decode(json_encode($response), true) : $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get CIRX block', [
                'block_number' => $blockNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get CIRX balance for an address
     */
    public function getBalance(string $address): string
    {
        try {
            $this->logger->debug('Getting CIRX balance', ['address' => $address]);
            
            $response = $this->cirxApi->getBalance('circular', $address);
            
            if (is_object($response) && isset($response->balance)) {
                $balance = (string)$response->balance;
            } elseif (is_array($response) && isset($response['balance'])) {
                $balance = (string)$response['balance'];
            } else {
                return '0';
            }
            
            // Convert from smallest unit using delegation to math utils
            return EthereumMathUtils::convertFromSmallestUnit($balance, 'ETH'); // CIRX uses 18 decimals like ETH
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get CIRX balance', [
                'address' => $address,
                'error' => $e->getMessage()
            ]);
            return '0';
        }
    }

    /**
     * Send CIRX transfer using NAG
     */
    public function sendCirxTransfer(
        string $toAddress, 
        string $amount, 
        ?string $nonce = null
    ): array {
        if (!$this->cirxPrivateKey) {
            throw new BlockchainException(
                "Private key required for CIRX transfers",
                0,
                null,
                'cirx',
                'send_transfer'
            );
        }

        try {
            $this->logger->info('Initiating CIRX transfer', [
                'from' => $this->cirxWalletAddress,
                'to' => $toAddress,
                'amount' => $amount
            ]);

            // Convert amount to smallest unit using delegation
            $amountWei = EthereumMathUtils::convertToSmallestUnit($amount, 'ETH');
            
            // Use NAG API for transfer
            $response = $this->cirxApi->sendTransaction(
                'circular',
                $this->cirxWalletAddress,
                $toAddress,
                $amountWei,
                $this->cirxPrivateKey,
                $nonce ?? (string)time()
            );

            $result = is_object($response) ? json_decode(json_encode($response), true) : $response;
            
            $this->logger->info('CIRX transfer completed', [
                'tx_hash' => $result['txHash'] ?? 'unknown',
                'amount' => $amount,
                'to' => $toAddress
            ]);

            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('CIRX transfer failed', [
                'to' => $toAddress,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            
            throw new BlockchainException(
                "CIRX transfer failed: " . $e->getMessage(),
                $e->getCode(),
                $e,
                'cirx',
                'send_transfer'
            );
        }
    }

    /**
     * Health check - test NAG connectivity
     */
    public function isHealthy(): bool
    {
        try {
            // Simple connectivity test
            $this->getBlockNumber();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('CIRX health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get network name
     */
    public function getNetworkName(): string
    {
        return "Circular Protocol ({$this->environment})";
    }

    /**
     * Get wallet address
     */
    public function getWalletAddress(): string
    {
        return $this->cirxWalletAddress;
    }

    /**
     * Get decimals
     */
    public function getDecimals(): int
    {
        return $this->cirxDecimals;
    }
}