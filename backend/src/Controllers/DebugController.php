<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Debug Controller
 * 
 * Provides debug endpoints for testing external APIs and services
 */
class DebugController
{
    private Client $httpClient;
    
    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);
    }
    
    /**
     * Test NAG balance call (proxy to avoid CORS)
     * POST /api/v1/debug/nag-balance
     * 
     * Body: {
     *   "nagUrl": "https://nag.circularlabs.io/NAG_Mainnet.php?cep=",
     *   "endpoint": "Account",
     *   "address": "0x..."
     * }
     */
    public function testNagBalance(Request $request, Response $response): Response
    {
        try {
            $body = json_decode($request->getBody()->getContents(), true);
            
            if (!$body) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON body'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $nagUrl = $body['nagUrl'] ?? '';
            $endpoint = $body['endpoint'] ?? '';
            $address = $body['address'] ?? '';
            
            if (!$nagUrl || !$endpoint || !$address) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Missing required fields: nagUrl, endpoint, address'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Construct the full NAG URL
            $fullNagUrl = $nagUrl . 'Circular_' . $endpoint;
            
            // Prepare NAG request body based on endpoint type
            if ($endpoint === 'GetWalletBalance_') {
                $nagRequestBody = [
                    'Blockchain' => $this->getEnvironmentBlockchain(),
                    'Address' => $address,
                    'Asset' => 'CIRX', // NAG API expects "Asset" with capital A, not "asset"
                    'Version' => '1.0.8'
                ];
            } else {
                // Default format for other endpoints
                $nagRequestBody = [
                    'TBody' => [
                        'Address' => $address
                    ]
                ];
            }
            
            // Log the request for debugging
            error_log("NAG Request URL: {$fullNagUrl}");
            error_log("NAG Request Body: " . json_encode($nagRequestBody));
            
            // Make the NAG API call
            $nagResponse = $this->httpClient->post($fullNagUrl, [
                'json' => $nagRequestBody,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ]);
            
            $nagResponseBody = $nagResponse->getBody()->getContents();
            $statusCode = $nagResponse->getStatusCode();
            
            // Log the response
            error_log("NAG Response Status: {$statusCode}");
            error_log("NAG Response Body: {$nagResponseBody}");
            
            // Try to decode JSON response
            $nagData = json_decode($nagResponseBody, true);
            
            $responseData = [
                'success' => true,
                'nag_request' => [
                    'url' => $fullNagUrl,
                    'body' => $nagRequestBody
                ],
                'nag_response' => [
                    'status' => $statusCode,
                    'body' => $nagData ?: $nagResponseBody,
                    'raw' => $nagResponseBody
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
            
        } catch (RequestException $e) {
            $errorData = [
                'success' => false,
                'error' => 'NAG request failed',
                'message' => $e->getMessage(),
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null,
                'response_body' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null
            ];
            
            error_log("NAG Request Exception: " . json_encode($errorData));
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'error' => 'Unexpected error',
                'message' => $e->getMessage()
            ];
            
            error_log("NAG Test Exception: " . json_encode($errorData));
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Get NAG configuration for frontend
     * GET /api/v1/debug/nag-config
     */
    public function getNagConfig(Request $request, Response $response): Response
    {
        $config = [
            'nag_urls' => [
                'testnet' => 'https://nag.circularlabs.io/NAG.php?cep=',
                'sandbox' => 'https://nag.circularlabs.io/NAG.php?cep=',
                'mainnet' => 'https://nag.circularlabs.io/NAG_Mainnet.php?cep='
            ],
            'endpoints' => [
                'testnet' => [
                    'balance' => 'Account',
                    'transaction' => 'Transaction',
                    'account' => 'Account_Info'
                ],
                'sandbox' => [
                    'balance' => 'Sandbox_Account_Balance',
                    'transaction' => 'Sandbox_Transaction',
                    'account' => 'Sandbox_Account_Info'
                ],
                'mainnet' => [
                    'balance' => 'Account',
                    'transaction' => 'Transaction',
                    'account' => 'Account_Info'
                ]
            ],
            'test_addresses' => [
                'default' => '0x5e9784e938a527625dde0c4f88bede4d86f8ab025377c1c5f3624135bbcdc5bb',
                'alternative' => '0x1234567890123456789012345678901234567890123456789012345678901234'
            ]
        ];
        
        $response->getBody()->write(json_encode($config, JSON_PRETTY_PRINT));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Send CIRX transaction (debug/testing only)
     * POST /api/v1/debug/send-transaction
     * 
     * Body: {
     *   "recipientAddress": "0x...",
     *   "amount": "1.5"
     * }
     */
    public function sendTransaction(Request $request, Response $response): Response
    {
        try {
            $body = json_decode($request->getBody()->getContents(), true);
            
            if (!$body) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON body'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $recipientAddress = $body['recipientAddress'] ?? '';
            $amount = $body['amount'] ?? '';
            
            if (!$recipientAddress || !$amount) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Missing required fields: recipientAddress, amount'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Validate amount is numeric
            if (!is_numeric($amount) || floatval($amount) <= 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Amount must be a positive number'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Get CIRX client from factory
            $clientFactory = new \App\Blockchain\BlockchainClientFactory();
            $cirxClient = $clientFactory->getCirxClient();
            
            // Check if private key is configured
            if (!$cirxClient->hasPrivateKey()) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'CIRX private key not configured. Check CIRX_WALLET_PRIVATE_KEY in .env file.',
                    'help' => 'This endpoint requires a configured private key for transaction signing.'
                ]));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
            
            // Get sender balance for display
            $senderAddress = $cirxClient->getCirxWalletAddress();
            $senderBalance = $cirxClient->getCirxBalance($senderAddress);
            
            error_log("Debug Transaction Request:");
            error_log("From: {$senderAddress} (Balance: {$senderBalance} CIRX)");
            error_log("To: {$recipientAddress}");
            error_log("Amount: {$amount} CIRX");
            
            // Send the transaction
            $txHash = $cirxClient->sendCirxTransfer($recipientAddress, $amount);
            
            $responseData = [
                'success' => true,
                'transaction' => [
                    'hash' => $txHash,
                    'from' => $senderAddress,
                    'to' => $recipientAddress,
                    'amount' => $amount,
                    'asset' => 'CIRX'
                ],
                'sender_balance_before' => $senderBalance,
                'timestamp' => date('c')
            ];
            
            error_log("Transaction sent successfully: {$txHash}");
            
            $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
            
        } catch (\App\Blockchain\Exceptions\BlockchainException $e) {
            $errorData = [
                'success' => false,
                'error' => 'Blockchain error: ' . $e->getMessage(),
                'error_type' => 'blockchain_exception',
                'error_code' => $e->getCode()
            ];
            
            error_log("Blockchain Exception: " . json_encode($errorData));
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'error' => 'Transaction failed: ' . $e->getMessage(),
                'error_type' => 'general_exception'
            ];
            
            error_log("Transaction Exception: " . json_encode($errorData));
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Health check for debug endpoints
     * GET /api/v1/debug/health
     */
    public function health(Request $request, Response $response): Response
    {
        $healthData = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'endpoints' => [
                'nag_balance' => '/api/v1/debug/nag-balance',
                'nag_config' => '/api/v1/debug/nag-config',
                'send_transaction' => '/api/v1/debug/send-transaction'
            ]
        ];
        
        $response->getBody()->write(json_encode($healthData, JSON_PRETTY_PRINT));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Debug environment variables
     * GET /api/v1/debug/env
     */
    public function debugEnv(Request $request, Response $response): Response
    {
        $envData = [
            'cirx_wallet_address' => $_ENV['CIRX_WALLET_ADDRESS'] ?? 'NOT SET',
            'cirx_private_key_set' => !empty($_ENV['CIRX_WALLET_PRIVATE_KEY']),
            'cirx_private_key_length' => isset($_ENV['CIRX_WALLET_PRIVATE_KEY']) ? strlen($_ENV['CIRX_WALLET_PRIVATE_KEY']) : 0,
            'cirx_nag_url' => $_ENV['CIRX_NAG_URL'] ?? 'NOT SET',
            'app_env' => $_ENV['APP_ENV'] ?? 'NOT SET'
        ];
        
        $response->getBody()->write(json_encode($envData, JSON_PRETTY_PRINT));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
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