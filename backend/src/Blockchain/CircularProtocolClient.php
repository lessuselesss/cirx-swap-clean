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
     * Get correct blockchain identifier for NAG API based on environment
     * Returns blockchain address (hex string) as expected by NAG API
     */
    private function getBlockchainId(): string
    {
        return match($this->environment) {
            'mainnet' => '714d2ac07a826b66ac56752eebd7c77b58d2ee842e523d913fd0ef06e6bdfcae', // Circular Main Public
            'staging' => 'acb8a9b79f3c663aa01be852cd42725f9e0e497fd849b436df51c5e074ebeb28', // Circular Secondary Public
            'development', 'testing' => '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2', // Circular SandBox
            default => '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2' // Default to SandBox
        };
    }

    /**
     * Get transaction by hash using NAG
     */
    public function getTransaction(string $txHash): ?array
    {
        try {
            $this->logger->debug('Fetching CIRX transaction', ['tx_hash' => $txHash]);
            
            // Use NAG API - no RPC needed
            $response = $this->cirxApi->getTransactionByID($this->getBlockchainId(), $txHash, 0, 0);
            
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
            // Use NAG to get block count
            $response = $this->cirxApi->getBlockCount($this->getBlockchainId());
            
            if (is_object($response)) {
                return (int)($response->Response ?? $response->result ?? 0);
            }
            
            if (is_array($response)) {
                return (int)($response['Response'] ?? $response['result'] ?? 0);
            }
            
            if (is_numeric($response)) {
                return (int)$response;
            }
            
            $this->logger->warning('Unexpected block count response', [
                'response' => $response
            ]);
            
            // Fallback to a reasonable default
            return 0;
            
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
            $response = $this->cirxApi->getBlock($this->getBlockchainId(), $blockNumber);
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
            
            // NOTE: The SDK's getWalletBalance method has a bug - it uses lowercase "asset" 
            // but NAG API expects "Asset" with capital A. We'll call NAG directly for now.
            
            // Manual NAG call with correct capitalization
            $data = [
                'Blockchain' => $this->getBlockchainId(),
                'Address' => $address,
                'Asset' => 'CIRX', // Correct capitalization - NAG expects capital A
                'Version' => '1.0.8'
            ];
            
            $response = $this->cirxApi->fetch($this->cirxApi->getNAGURL() . 'Circular_GetWalletBalance_', $data);
            
            if (isset($response->Result) && $response->Result === 200 && isset($response->Response->Balance)) {
                $balance = (string)$response->Response->Balance;
            } else {
                $this->logger->warning('NAG API balance check failed', [
                    'response' => $response,
                    'address' => $address,
                    'error' => $response->ERROR ?? $response->Response ?? 'Unknown error'
                ]);
                return '0';
            }
            
            // IMPORTANT: NAG API returns balance in human-readable format (e.g. 203.1 CIRX)
            // NOT in wei format. Do NOT convert with EthereumMathUtils.
            return (string)$balance;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get CIRX balance', [
                'address' => $address,
                'error' => $e->getMessage()
            ]);
            return '0';
        }
    }

    /**
     * Send CIRX transfer using NAG - RESTORED WORKING VERSION
     */
    public function sendCirxTransfer(string $recipientAddress, string $amount): string
    {
        $this->logger->info("Initiating CIRX transfer", [
            'from' => $this->cirxWalletAddress,
            'to' => $recipientAddress,
            'amount' => $amount
        ]);

        if (!$this->cirxPrivateKey) {
            throw new BlockchainException(
                "CIRX private key not configured for transaction signing",
                500,
                null,
                'cirx',
                'send_transfer'
            );
        }

        try {
            // Check sender balance first
            $senderBalance = $this->getCirxBalance($this->cirxWalletAddress);
            if (bccomp($senderBalance, $amount, $this->cirxDecimals) < 0) {
                throw new BlockchainException(
                    "Insufficient CIRX balance. Required: {$amount}, Available: {$senderBalance}",
                    0,
                    null,
                    'cirx',
                    'insufficient_balance'
                );
            }

            // Get transaction parameters first
            $blockchainId = $this->getBlockchainId(); // Use existing method instead of getEnvironmentBlockchain
            $timestampResponse = $this->cirxApi->getFormattedTimestamp();
            $nonceResponse = $this->cirxApi->getWalletNonce($blockchainId, $this->cirxWalletAddress);
            
            // Extract string values from SDK responses (they may return objects)
            $this->logger->info("SDK Response Types", [
                'timestamp_type' => gettype($timestampResponse),
                'timestamp_value' => var_export($timestampResponse, true),
                'nonce_type' => gettype($nonceResponse),
                'nonce_value' => var_export($nonceResponse, true)
            ]);
            
            // Handle timestamp conversion
            if (is_object($timestampResponse)) {
                if (isset($timestampResponse->timestamp)) {
                    $timestamp = (string)$timestampResponse->timestamp;
                } elseif (method_exists($timestampResponse, '__toString')) {
                    $timestamp = $timestampResponse->__toString();
                } else {
                    $timestamp = json_encode($timestampResponse);
                }
            } else {
                $timestamp = (string)$timestampResponse;
            }
            
            // Handle nonce conversion - extract the actual nonce value
            if (is_object($nonceResponse)) {
                if (isset($nonceResponse->Result) && $nonceResponse->Result === 200) {
                    if (isset($nonceResponse->Response->Nonce)) {
                        $nonce = (string)$nonceResponse->Response->Nonce;
                    } else {
                        $nonce = '0'; // Default nonce
                    }
                } else {
                    throw new BlockchainException(
                        "Failed to get wallet nonce: " . ($nonceResponse->Response ?? 'Unknown error'),
                        $nonceResponse->Result ?? 500,
                        null,
                        'cirx',
                        'get_nonce'
                    );
                }
            } else {
                $nonce = (string)$nonceResponse;
            }

            $strategies = [
                'working_simple_nonce_plus1' => [
                    'type' => 'C_TYPE_COIN',
                    'signature_method' => 'simple',
                    'nonce_method' => 'plus1',
                    'payload_obj' => [
                        "Action" => "CP_SEND",
                        "Amount" => $amount,
                        "To" => $recipientAddress,
                        "Asset" => "CIRX",
                        "Memo" => ""
                    ]
                ],
                'working_simple_string_nonce' => [
                    'type' => 'C_TYPE_COIN',
                    'signature_method' => 'simple',
                    'nonce_method' => 'string',
                    'payload_obj' => [
                        "Action" => "CP_SEND",
                        "Amount" => $amount,
                        "To" => $recipientAddress,
                        "Asset" => "CIRX",
                        "Memo" => ""
                    ]
                ],
                'working_simple_original' => [
                    'type' => 'C_TYPE_COIN',
                    'signature_method' => 'simple',
                    'nonce_method' => 'original',
                    'payload_obj' => [
                        "Action" => "CP_SEND",
                        "Amount" => $amount,
                        "To" => $recipientAddress,
                        "Asset" => "CIRX",
                        "Memo" => ""
                    ]
                ]
            ];
            
            $this->logger->info("Using working strategy patterns from commit 4fcf018", [
                'original_amount' => $amount,
                'note' => 'Calculating TxID with same payload that gets sent'
            ]);
            
            $lastException = null;
            $txResponse = null;
            
            foreach ($strategies as $strategyName => $strategy) {
                try {
                    $txType = $strategy['type'];
                    $payloadObj = $strategy['payload_obj'];
                    $signatureMethod = $strategy['signature_method'];
                    $nonceMethod = $strategy['nonce_method'] ?? 'original';
                    
                    // Handle different nonce approaches
                    $currentNonce = $nonce; // Default from API
                    switch ($nonceMethod) {
                        case 'plus1':
                            $currentNonce = (string)((int)$nonce + 1);
                            break;
                        case 'string':
                            $currentNonce = "nonce_" . $nonce;
                            break;
                        case 'original':
                        default:
                            $currentNonce = $nonce;
                            break;
                    }
                    
                    // Follow SDK pattern exactly: JSON → hex → TxID calculation → sendTransaction
                    $jsonStr = json_encode($payloadObj);
                    $currentPayload = $this->cirxApi->stringToHex($jsonStr); // Use SDK's stringToHex method
                    
                    // Calculate TxID using the SAME payload that will be sent (SDK pattern)
                    $fromClean = $this->cirxApi->hexFix($this->cirxWalletAddress);
                    $toClean = $this->cirxApi->hexFix($recipientAddress);
                    $payloadClean = $this->cirxApi->hexFix($currentPayload);
                    
                    // Use exact SDK pattern: hash('sha256', $blockchain . $from . $to . $payload . $nonce . $timestamp)
                    $txIdString = $blockchainId . $fromClean . $toClean . $payloadClean . $currentNonce . $timestamp;
                    $txId = hash('sha256', $txIdString);
                    
                    $this->logger->info("Trying strategy: {$strategyName}", [
                        'txId' => $txId,
                        'txId_source' => $txIdString,
                        'from' => $this->cirxWalletAddress,
                        'to' => $recipientAddress,
                        'timestamp' => $timestamp,
                        'tx_type' => $txType,
                        'payload' => $currentPayload,
                        'payload_decoded' => $jsonStr,
                        'nonce_original' => $nonce,
                        'nonce_current' => $currentNonce,
                        'nonce_method' => $nonceMethod,
                        'blockchain' => $blockchainId,
                        'signature_method' => $signatureMethod
                    ]);
                    
                    // Handle signature - use simple method that was working
                    $signature = '';
                    switch ($signatureMethod) {
                        case 'simple':
                            // Try just signing the TxID (simpler message)
                            $message = $txId;
                            $signature = $this->cirxApi->signMessage($message, $this->cirxPrivateKey);
                            $this->logger->info("Using simple signature for {$strategyName}", [
                                'message' => $message,
                                'signature' => $signature
                            ]);
                            break;
                            
                        default:
                            $signature = '';
                            break;
                    }
                    
                    // Send transaction with all required parameters (9 total)
                    // Parameters: id, from, to, timestamp, type, payload, nonce, signature, blockchain
                    $txResponse = $this->cirxApi->sendTransaction(
                        $txId,
                        $this->cirxWalletAddress,
                        $recipientAddress,
                        $timestamp,
                        $txType,
                        $currentPayload, // Same payload used in TxID calculation
                        $currentNonce,   // Use the modified nonce for both TxID and transaction
                        $signature,
                        $blockchainId
                    );
                    
                    // Check if the response indicates success
                    if (is_object($txResponse)) {
                        if (isset($txResponse->Result) && $txResponse->Result !== 200) {
                            // This strategy failed, try next one
                            $this->logger->info("Strategy {$strategyName} failed", [
                                'error' => $txResponse->Response ?? 'Unknown error',
                                'error_code' => $txResponse->Result ?? 'unknown'
                            ]);
                            $lastException = new \Exception(
                                "Transaction failed: " . ($txResponse->Response ?? 'Unknown error'),
                                $txResponse->Result ?? 500
                            );
                            continue;
                        }
                    }
                    
                    // If we get here, the strategy worked
                    $this->logger->info("Strategy {$strategyName} succeeded!", [
                        'response' => $txResponse
                    ]);
                    break;
                    
                } catch (\Exception $e) {
                    $this->logger->info("Strategy {$strategyName} failed", [
                        'error' => $e->getMessage(),
                        'error_code' => $e->getCode()
                    ]);
                    $lastException = $e;
                    continue;
                }
            }
            
            // If all strategies failed, throw the last exception
            if ($txResponse === null && $lastException) {
                throw $lastException;
            }
            
            // Handle SDK response - it may return an object instead of string
            if (is_object($txResponse)) {
                if (isset($txResponse->Result) && $txResponse->Result !== 200) {
                    throw new BlockchainException(
                        "Transaction failed: " . ($txResponse->Response ?? 'Unknown error'),
                        $txResponse->Result ?? 500,
                        null,
                        'cirx',
                        'send_transfer'
                    );
                }
                
                // Extract transaction hash from response object
                $txHash = isset($txResponse->Hash) ? $txResponse->Hash : 
                         (isset($txResponse->TransactionHash) ? $txResponse->TransactionHash :
                         (isset($txResponse->TxHash) ? $txResponse->TxHash : $txId));
            } else {
                $txHash = (string)$txResponse;
            }

            $this->logger->info("CIRX transfer completed", [
                'tx_hash' => $txHash,
                'from' => $this->cirxWalletAddress,
                'to' => $recipientAddress,
                'amount' => $amount
            ]);

            return (string)$txHash;

        } catch (\Exception $e) {
            throw new BlockchainException(
                "Failed to send CIRX transfer: " . $e->getMessage(),
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

    /**
     * Get block by number (interface requirement)
     */
    public function getBlock(int $blockNumber): ?array
    {
        return $this->getBlockByNumber($blockNumber);
    }

    /**
     * Get token balance (interface requirement)
     * For CIRX, this is the same as native balance
     */
    public function getTokenBalance(string $tokenAddress, string $walletAddress): string
    {
        // For CIRX chain, token balance is the same as native balance
        return $this->getBalance($walletAddress);
    }

    /**
     * Get native balance (interface requirement)
     */
    public function getNativeBalance(string $walletAddress): string
    {
        return $this->getBalance($walletAddress);
    }

    /**
     * Get chain ID (interface requirement)
     */
    public function getChainId(): int
    {
        return (int)($_ENV['CIRX_CHAIN_ID'] ?? 9999);
    }

    /**
     * Get transaction confirmations (interface requirement)
     */
    public function getTransactionConfirmations(string $txHash): int
    {
        try {
            $receipt = $this->getTransactionReceipt($txHash);
            if (!$receipt || !isset($receipt['blockNumber'])) {
                return 0;
            }

            $currentBlock = $this->getBlockNumber();
            $txBlock = (int)$receipt['blockNumber'];
            
            return max(0, $currentBlock - $txBlock);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get transaction confirmations', [
                'tx_hash' => $txHash,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Check if private key is configured for signing transactions
     */
    public function hasPrivateKey(): bool
    {
        return !empty($this->cirxPrivateKey);
    }

    /**
     * Get CIRX balance (compatibility method)
     */
    public function getCirxBalance(string $address): string
    {
        return $this->getBalance($address);
    }

    /**
     * Get CIRX wallet address (compatibility method)
     */
    public function getCirxWalletAddress(): string
    {
        return $this->getWalletAddress();
    }
}