<?php

namespace App\Blockchain;

use App\Blockchain\Exceptions\BlockchainException;
use Psr\Log\LoggerInterface;
use CircularProtocol\Api\CircularProtocolAPI;

/**
 * CIRX Blockchain Client
 * 
 * Handles interactions with the Circular Protocol blockchain for CIRX token operations.
 * This client supports BOTH read operations (monitoring) AND write operations (CIRX transfers)
 * since CIRX transfers are server-side operations managed by this backend.
 * Generic EVM transaction methods removed - use sendCirxTransfer for CIRX operations.
 */
class CirxBlockchainClient extends AbstractBlockchainClient
{
    private string $cirxWalletAddress;
    private ?string $cirxPrivateKey;
    private int $cirxDecimals;
    private CircularProtocolAPI $cirxApi;

    public function __construct(
        string $rpcUrl,
        string $cirxWalletAddress,
        ?string $cirxPrivateKey = null,
        int $cirxDecimals = 18,
        ?string $backupRpcUrl = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($rpcUrl, $backupRpcUrl, $logger);
        
        $this->cirxWalletAddress = $cirxWalletAddress;
        $this->cirxPrivateKey = $cirxPrivateKey;
        $this->cirxDecimals = $cirxDecimals;
        
        // Initialize Circular Protocol API
        $this->cirxApi = new CircularProtocolAPI();
        
        // Set NAG URL based on environment (no key required)
        if ($rpcUrl && str_contains($rpcUrl, 'mainnet')) {
            $this->cirxApi->setNAGURL('https://nag.circularlabs.io/NAG_Mainnet.php?cep=');
        } else {
            $this->cirxApi->setNAGURL('https://nag.circularlabs.io/NAG.php?cep=');
        }
    }

    /**
     * Get transaction by hash
     */
    public function getTransaction(string $txHash): ?array
    {
        try {
            // Use the SDK's transaction retrieval method
            // Parameters: blockchain, txID, start, end (start/end can be 0 for single tx)
            $response = $this->cirxApi->getTransactionByID('circular', $txHash, 0, 0);
            
            // Convert stdClass to array if needed for type consistency
            return is_object($response) ? json_decode(json_encode($response), true) : $response;
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not found') || $e->getCode() === 404) {
                return null;
            }
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
     * Get transaction receipt by hash
     */
    public function getTransactionReceipt(string $txHash): ?array
    {
        try {
            $response = $this->rpcCall('eth_getTransactionReceipt', [$txHash]);
            return $response['result'];
        } catch (BlockchainException $e) {
            if (str_contains($e->getMessage(), 'not found')) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Get current block number
     */
    public function getBlockNumber(): int
    {
        $response = $this->rpcCall('eth_blockNumber');
        return (int)$this->hexToDec($response['result']);
    }

    /**
     * Get block by number
     */
    public function getBlock(int $blockNumber): ?array
    {
        $blockHex = $this->decToHex((string)$blockNumber);
        $response = $this->rpcCall('eth_getBlockByNumber', [$blockHex, true]);
        return $response['result'];
    }

    /**
     * Get CIRX token balance for an address
     */
    public function getTokenBalance(string $tokenAddress, string $walletAddress): string
    {
        // For CIRX, tokenAddress might be ignored as CIRX is the native token
        return $this->getCirxBalance($walletAddress);
    }

    /**
     * Get native balance (assuming CIRX is the native token)
     */
    public function getNativeBalance(string $walletAddress): string
    {
        return $this->getCirxBalance($walletAddress);
    }

    /**
     * Get CIRX balance for a wallet address
     */
    public function getCirxBalance(string $walletAddress): string
    {
        try {
            // NOTE: The SDK's getWalletBalance method has a bug - it uses lowercase "asset" 
            // but NAG API expects "Asset" with capital A. We'll call NAG directly for now.
            
            // Manual NAG call with correct capitalization
            // Strip 0x prefix from addresses as required by NAG API
            $data = [
                'Blockchain' => $this->getEnvironmentBlockchain(),
                'Address' => str_replace('0x', '', $walletAddress),
                'Asset' => 'CIRX', // Correct capitalization
                'Version' => '1.0.8'
            ];
            
            $response = $this->cirxApi->fetch($this->cirxApi->getNAGURL() . 'Circular_GetWalletBalance_', $data);
            
            if (isset($response->Result) && $response->Result === 200 && isset($response->Response->Balance)) {
                return (string)$response->Response->Balance;
            } else {
                throw new \Exception('NAG API returned error: ' . ($response->ERROR ?? json_encode($response)));
            }
        } catch (\Exception $e) {
            throw new BlockchainException(
                "Failed to get CIRX balance: " . $e->getMessage(),
                $e->getCode(),
                $e,
                'cirx',
                'get_balance'
            );
        }
    }

    // ERC-20 style balance method removed - CIRX is native token, use getCirxBalance() instead

    // Generic sendTransaction method removed - use sendCirxTransfer for CIRX operations

    /**
     * Send CIRX tokens to a recipient
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
                throw BlockchainException::insufficientBalance(
                    'cirx',
                    $this->cirxWalletAddress,
                    $amount,
                    $senderBalance
                );
            }

            // Get transaction parameters first
            $blockchainId = $this->getEnvironmentBlockchain();
            $timestampResponse = $this->cirxApi->getFormattedTimestamp();
            
            // Use the blockchain ID instead of 'circular' string
            $blockchainId = $this->getEnvironmentBlockchain();
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
            
            // Try hexadecimal payload format for amount
            // Convert amount to wei (18 decimals) and then to hex
            $amountWei = bcmul($amount, bcpow('10', $this->cirxDecimals));
            // Convert BC math result to hex - handle large numbers properly
            $payload = '0x' . $this->bcToHex($amountWei);
            
            // Calculate TxID as sha256 hash - try different approaches based on SDK patterns
            $fromClean = str_replace('0x', '', $this->cirxWalletAddress);
            $toClean = str_replace('0x', '', $recipientAddress);
            $payloadClean = str_replace('0x', '', $payload);
            
            // Strategy 1: Try using random transaction ID (maybe the API doesn't validate it strictly)
            $randomTxId = '0x' . hash('sha256', uniqid() . microtime() . $fromClean . $toClean);
            
            // Strategy 2: Try the SDK registerWallet pattern: blockchain + from + to + payload + nonce + timestamp
            $sdkPattern = $blockchainId . $fromClean . $toClean . $payloadClean . $nonce . $timestamp;
            $sdkTxId = '0x' . hash('sha256', $sdkPattern);
            
            // Strategy 3: Original pattern but include blockchain ID
            $withBlockchain = $blockchainId . $fromClean . $toClean . $payloadClean . $timestamp;
            $blockchainTxId = '0x' . hash('sha256', $withBlockchain);
            
            // Strategy 4: Try without the 0x prefix in the final result
            $noPrefixTxId = hash('sha256', $fromClean . $toClean . $payloadClean . $timestamp);
            
            // Strategy 5: Try plain string payload instead of hex (like registerWallet does)
            $plainAmount = (string)$amount; // Just "0.1" instead of hex
            $plainPayloadPattern = $blockchainId . $fromClean . $toClean . $plainAmount . $nonce . $timestamp;
            $plainPayloadTxId = '0x' . hash('sha256', $plainPayloadPattern);
            
            // Strategy 6: Try amount in different format (wei as string)
            $weiStringPattern = $blockchainId . $fromClean . $toClean . $amountWei . $nonce . $timestamp;
            $weiStringTxId = '0x' . hash('sha256', $weiStringPattern);
            
            // Strategy 7: Simple incremental ID (maybe they just want unique IDs)
            $simpleId = '0x' . str_pad(dechex(time() + rand(1, 1000)), 64, '0', STR_PAD_LEFT);
            
            // Strategy 8: Try double SHA256 (like Bitcoin)
            $doubleSha = hash('sha256', $fromClean . $toClean . $payloadClean . $timestamp);
            $doubleShaId = '0x' . hash('sha256', $doubleSha);
            
            // Strategy 9: Include nonce in different position
            $nonceFirstPattern = $nonce . $blockchainId . $fromClean . $toClean . $payloadClean . $timestamp;
            $nonceFirstId = '0x' . hash('sha256', $nonceFirstPattern);
            
            // Strategy 10: Try keccak256 instead of SHA256 (Ethereum style)
            if (function_exists('hash') && in_array('sha3-256', hash_algos())) {
                $keccakId = '0x' . hash('sha3-256', $fromClean . $toClean . $payloadClean . $timestamp);
            } else {
                // Fallback to regular SHA256 if keccak not available
                $keccakId = '0x' . hash('sha256', 'keccak:' . $fromClean . $toClean . $payloadClean . $timestamp);
            }
            
            // Strategy 11: Message digest format (like in signing)
            $messageDigest = $this->cirxWalletAddress . $recipientAddress . $timestamp . $payload . $nonce;
            $messageDigestId = '0x' . hash('sha256', $messageDigest);
            
            // Strategy 12: Try with the actual hex fix function from SDK
            $hexFixedFrom = $this->cirxApi->hexFix($this->cirxWalletAddress);
            $hexFixedTo = $this->cirxApi->hexFix($recipientAddress);
            $hexFixedPayload = $this->cirxApi->hexFix($payload);
            $hexFixedPattern = $blockchainId . $hexFixedFrom . $hexFixedTo . $hexFixedPayload . $nonce . $timestamp;
            $hexFixedId = '0x' . hash('sha256', $hexFixedPattern);
            
            // SOLVED: Simple signature method works! Now testing nonce handling
            // The simple message (signing only TxID) passes signature verification
            // Issue: nonce needs proper handling for transaction submission
            
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
            
            $this->logger->info("Fixed TxID/payload calculation using exact SDK pattern", [
                'original_amount' => $amount,
                'amount_wei' => $amountWei,
                'note' => 'Now calculating TxID with same payload that gets sent'
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
                    
                    // Handle different signature methods
                    $signature = '';
                    switch ($signatureMethod) {
                        case 'empty':
                            // Like registerWallet - no signature required
                            $signature = '';
                            $this->logger->info("Using empty signature for {$strategyName}");
                            break;
                            
                        case 'simple':
                            // Try just signing the TxID (simpler message)
                            $message = $txId;
                            $signature = $this->cirxApi->signMessage($message, $this->cirxPrivateKey);
                            $this->logger->info("Using simple signature for {$strategyName}", [
                                'message' => $message,
                                'signature' => $signature
                            ]);
                            break;
                            
                        case 'normal':
                        default:
                            // Original complex message format
                            $message = $txId . $this->cirxWalletAddress . $recipientAddress . $timestamp . $currentPayload . $nonce;
                            $signature = $this->cirxApi->signMessage($message, $this->cirxPrivateKey);
                            $this->logger->info("Using normal signature for {$strategyName}", [
                                'message' => $message,
                                'message_length' => strlen($message),
                                'signature' => $signature
                            ]);
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

    // ERC-20 and native transfer helper methods removed - use sendCirxTransfer directly

    // Gas estimation and pricing methods removed - CIRX transfers handle gas internally

    /**
     * Get the chain ID (CIRX network specific)
     */
    public function getChainId(): int
    {
        try {
            $response = $this->rpcCall('eth_chainId');
            return (int)$this->hexToDec($response['result']);
        } catch (BlockchainException $e) {
            $this->logger->warning("Failed to get chain ID, using default", [
                'error' => $e->getMessage()
            ]);
            return 9999; // Default CIRX chain ID (placeholder)
        }
    }

    /**
     * Get the network name
     */
    public function getNetworkName(): string
    {
        return 'cirx';
    }

    /**
     * Verify a CIRX transfer by checking transaction receipt
     */
    public function verifyCirxTransfer(
        string $txHash,
        string $expectedRecipient,
        string $expectedAmount
    ): bool {
        $receipt = $this->getTransactionReceipt($txHash);
        
        if (!$receipt || $receipt['status'] !== '0x1') {
            return false;
        }

        $transaction = $this->getTransaction($txHash);
        if (!$transaction) {
            return false;
        }

        // Check recipient
        if (strtolower($transaction['to']) !== strtolower($expectedRecipient)) {
            return false;
        }

        // CIRX is native token - verify amount from transaction value
        $transferredAmount = $this->parseTokenAmount(
            $this->hexToDec($transaction['value']), 
            $this->cirxDecimals
        );
        
        $expectedFloat = (float)$expectedAmount;
        $actualFloat = (float)$transferredAmount;
        $tolerance = $expectedFloat * 0.001; // 0.1% tolerance

        return abs($expectedFloat - $actualFloat) <= $tolerance;
    }

    // ERC-20 transfer verification removed - CIRX is native token

    /**
     * Get transaction confirmation count
     */
    public function getTransactionConfirmations(string $txHash): int
    {
        $tx = $this->getTransaction($txHash);
        if (!$tx || !isset($tx['blockNumber'])) {
            return 0;
        }

        $currentBlock = $this->getBlockNumber();
        $txBlock = (int)$this->hexToDec($tx['blockNumber']);
        
        return max(0, $currentBlock - $txBlock + 1);
    }

    /**
     * Get CIRX wallet address
     */
    public function getCirxWalletAddress(): string
    {
        return $this->cirxWalletAddress;
    }

    // Contract address method removed - CIRX is native token, no contract address needed

    /**
     * Check if private key is configured
     */
    public function hasPrivateKey(): bool
    {
        return !empty($this->cirxPrivateKey);
    }

    /**
     * Check if NAG URL is configured
     */
    public function hasNAGURL(): bool
    {
        return !empty($this->cirxApi->getNAGURL());
    }

    /**
     * Get NAG URL being used
     */
    public function getNAGURL(): string
    {
        return $this->cirxApi->getNAGURL();
    }

    /**
     * Get SDK version
     */
    public function getSDKVersion(): string
    {
        return $this->cirxApi->getVersion();
    }
    
    /**
     * Convert BC Math arbitrary precision number to hexadecimal
     * Handles large numbers that dechex() cannot process
     * @param string $number BC Math number as string
     * @return string Hexadecimal representation without 0x prefix
     */
    private function bcToHex(string $number): string
    {
        // Handle zero case
        if (bccomp($number, '0', 0) === 0) {
            return '0';
        }

        $hex = '';
        while (bccomp($number, '0', 0) > 0) {
            $remainder = bcmod($number, '16');
            $hex = dechex((int)$remainder) . $hex;
            $number = bcdiv($number, '16', 0);
        }

        return $hex;
    }

    /**
     * Get blockchain address based on environment
     * @return string Blockchain address for current environment
     */
    private function getEnvironmentBlockchain(): string
    {
        $environment = $_ENV['APP_ENV'] ?? 'development';
        
        switch ($environment) {
            case 'production':
                return '714d2ac07a826b66ac56752eebd7c77b58d2ee842e523d913fd0ef06e6bdfcae'; // Circular Main Public
            case 'staging':
                return 'acb8a9b79f3c663aa01be852cd42725f9e0e497fd849b436df51c5e074ebeb28'; // Circular Secondary Public
            case 'development':
            case 'testing':
            default:
                return '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2'; // Circular SandBox
        }
    }
}