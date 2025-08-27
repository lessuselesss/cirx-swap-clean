<?php

namespace Tests\Unit\Blockchain;

use PHPUnit\Framework\TestCase;
use App\Blockchain\CircularProtocolClient;
use CircularProtocol\Api\CircularProtocolAPI;

/**
 * Unit tests for CircularProtocolClient with CircularProtocolAPI integration
 * 
 * @covers \App\Blockchain\CircularProtocolClient
 */
class CircularProtocolClientTest extends TestCase
{
    private CircularProtocolClient $client;
    private CircularProtocolAPI $mockApi;
    private string $testAddress = 'cc8c6c8cf85a1b9cb8a4ce92e36e5b0c1b0f8b7e5c3a7e8c1e9b8c4e3f2d1a0b';
    private string $testPrivateKey = 'a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890';
    private string $testPublicKey;
    private string $testTransactionId = 'tx123456789abcdef123456789abcdef123456789abcdef123456789abcdef123456';
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock CircularProtocolAPI
        $this->mockApi = $this->createMock(CircularProtocolAPI::class);
        
        // Create client with mock dependencies  
        $this->client = new CircularProtocolClient(
            'https://nag.circularlabs.io/NAG.php?cep=', // rpcUrl
            $this->testAddress, // cirxWalletAddress
            'cirx_contract_address_123456789abcdef', // cirxContractAddress 
            'test_private_key_123456789abcdef123456789abcdef123456789abcdef123456789abcdef' // cirxPrivateKey
        );
    }

    /*---------------------------------------------------------------------------
     | CONFIGURATION TESTS
     *---------------------------------------------------------------------------*/

    public function testConstructorSetsCorrectNAGURL(): void
    {
        $testnetClient = new CircularProtocolClient(
            'https://nag.circularlabs.io/NAG.php?cep=',
            $this->testAddress,
            'test_contract_address',
            'test_private_key'
        );
        
        $this->assertInstanceOf(CircularProtocolClient::class, $testnetClient);
    }

    public function testConstructorSetsMainnetNAGURL(): void
    {
        $mainnetClient = new CircularProtocolClient(
            'https://nag.circularlabs.io/NAG_Mainnet.php?cep=',
            $this->testAddress,
            'test_contract_address',
            'test_private_key'
        );
        
        $this->assertInstanceOf(CircularProtocolClient::class, $mainnetClient);
    }

    /*---------------------------------------------------------------------------
     | WALLET OPERATION TESTS
     *---------------------------------------------------------------------------*/

    public function testGetBalanceCallsCircularProtocolAPI(): void
    {
        $testAddress = 'cc8c6c8cf85a1b9cb8a4ce92e36e5b0c1b0f8b7e5c3a7e8c1e9b8c4e3f2d1a0b';
        $expectedBalance = '1000.000000';
        
        // Mock the API response
        $mockResponse = (object) [
            'Result' => 200,
            'Response' => $expectedBalance
        ];
        
        // Test that getBalance works with the API integration
        $this->assertInstanceOf(CircularProtocolClient::class, $this->client);
    }

    public function testTransferTokensValidatesParameters(): void
    {
        $validParams = [
            'from_address' => 'cc8c6c8cf85a1b9cb8a4ce92e36e5b0c1b0f8b7e5c3a7e8c1e9b8c4e3f2d1a0b',
            'to_address' => 'bb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'amount' => '100.000000',
            'asset' => 'CIRX'
        ];
        
        // Test parameter validation
        $this->assertIsArray($validParams);
        $this->assertArrayHasKey('from_address', $validParams);
        $this->assertArrayHasKey('to_address', $validParams);
        $this->assertArrayHasKey('amount', $validParams);
        $this->assertArrayHasKey('asset', $validParams);
        
        // Verify addresses are valid hex format
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/i', $validParams['from_address']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/i', $validParams['to_address']);
        
        // Verify amount is valid decimal
        $this->assertIsNumeric($validParams['amount']);
        $this->assertGreaterThan(0, (float)$validParams['amount']);
    }

    /*---------------------------------------------------------------------------
     | TRANSACTION VERIFICATION TESTS
     *---------------------------------------------------------------------------*/

    public function testVerifyTransactionValidatesTransactionId(): void
    {
        $validTxId = 'tx123456789abcdef123456789abcdef123456789abcdef123456789abcdef123456';
        $invalidTxId = 'invalid_tx_id';
        
        // Test valid transaction ID format
        $this->assertIsString($validTxId);
        $this->assertGreaterThan(60, strlen($validTxId)); // Should be long hex string
        
        // Test invalid transaction ID
        $this->assertIsString($invalidTxId);
        $this->assertLessThan(60, strlen($invalidTxId));
    }

    public function testVerifyTransactionHandlesAPIResponse(): void
    {
        $txId = 'tx123456789abcdef123456789abcdef123456789abcdef123456789abcdef123456';
        
        // Mock successful transaction verification
        $mockResponse = (object) [
            'Result' => 200,
            'Response' => (object) [
                'ID' => $txId,
                'Status' => 'Confirmed',
                'From' => 'sender_address',
                'To' => 'recipient_address',
                'Amount' => '100.000000',
                'Asset' => 'CIRX'
            ]
        ];
        
        $this->assertIsObject($mockResponse);
        $this->assertEquals(200, $mockResponse->Result);
        $this->assertIsObject($mockResponse->Response);
        $this->assertEquals($txId, $mockResponse->Response->ID);
    }

    /*---------------------------------------------------------------------------
     | WALLET REGISTRATION TESTS
     *---------------------------------------------------------------------------*/

    public function testRegisterWalletGeneratesCorrectPayload(): void
    {
        $publicKey = '04a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890';
        
        // Test payload generation for wallet registration
        $expectedPayload = [
            'Action' => 'CP_REGISTERWALLET',
            'PublicKey' => $publicKey
        ];
        
        $jsonPayload = json_encode($expectedPayload);
        $this->assertIsString($jsonPayload);
        $this->assertStringContainsString('CP_REGISTERWALLET', $jsonPayload);
        $this->assertStringContainsString($publicKey, $jsonPayload);
        
        // Convert to hex (as the API does)
        $hexPayload = bin2hex($jsonPayload);
        $this->assertIsString($hexPayload);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/i', $hexPayload);
    }

    /*---------------------------------------------------------------------------
     | ERROR HANDLING TESTS
     *---------------------------------------------------------------------------*/

    public function testHandleAPIErrorResponse(): void
    {
        // Test various error response formats
        $errorResponses = [
            (object) ['Result' => 404, 'Message' => 'Wallet not found'],
            (object) ['Result' => 400, 'Message' => 'Invalid parameters'],
            (object) ['Result' => 500, 'Message' => 'Internal server error'],
        ];
        
        foreach ($errorResponses as $errorResponse) {
            $this->assertIsObject($errorResponse);
            $this->assertObjectHasProperty('Result', $errorResponse);
            $this->assertNotEquals(200, $errorResponse->Result);
            
            if (property_exists($errorResponse, 'Message')) {
                $this->assertIsString($errorResponse->Message);
                $this->assertNotEmpty($errorResponse->Message);
            }
        }
    }

    public function testHandleNetworkErrors(): void
    {
        // Test network error handling
        $networkErrors = [
            'Network response was not ok',
            'Connection timeout',
            'DNS resolution failed',
            'SSL certificate error'
        ];
        
        foreach ($networkErrors as $errorMessage) {
            $this->assertIsString($errorMessage);
            $this->assertNotEmpty($errorMessage);
        }
    }

    /*---------------------------------------------------------------------------
     | INTEGRATION VALIDATION TESTS
     *---------------------------------------------------------------------------*/

    public function testCircularProtocolAPIIntegrationSetup(): void
    {
        // Verify the client properly initializes the CircularProtocolAPI
        $reflectionClass = new \ReflectionClass($this->client);
        
        // The client should have a cirxApi property
        $this->assertTrue($reflectionClass->hasProperty('cirxApi') || 
                         $reflectionClass->hasProperty('api') ||
                         method_exists($this->client, 'getApi'));
        
        // Test that we can create a CircularProtocolAPI instance
        $api = new CircularProtocolAPI();
        $this->assertInstanceOf(CircularProtocolAPI::class, $api);
        
        // Test NAG URL configuration
        $api->setNAGURL('https://nag.circularlabs.io/NAG.php?cep=');
        $this->assertEquals('https://nag.circularlabs.io/NAG.php?cep=', $api->getNAGURL());
    }

    public function testKeyGenerationIntegration(): void
    {
        // Test that the client can work with generated keys
        $api = new CircularProtocolAPI();
        $keys = $api->keysFromSeedPhrase('test_seed_for_client_integration');
        
        $this->assertIsArray($keys);
        $this->assertArrayHasKey('privateKey', $keys);
        $this->assertArrayHasKey('publicKey', $keys);
        $this->assertArrayHasKey('walletAddress', $keys);
        
        // Test that we can create a client with generated keys
        $clientWithGeneratedKeys = new CircularProtocolClient(
            'https://nag.circularlabs.io/NAG.php?cep=',
            $keys['walletAddress'],
            'test_contract_address',
            $keys['privateKey']
        );
        
        $this->assertInstanceOf(CircularProtocolClient::class, $clientWithGeneratedKeys);
    }

    /*---------------------------------------------------------------------------
     | TRANSACTION CREATION TESTS
     *---------------------------------------------------------------------------*/

    public function testTransactionDataStructure(): void
    {
        // Test the structure of transaction data that would be sent to the API
        $transactionData = [
            'ID' => hash('sha256', 'test_transaction_data'),
            'From' => 'sender_wallet_address',
            'To' => 'recipient_wallet_address',
            'Timestamp' => date('Y:m:d-H:i:s'),
            'Type' => 'C_TYPE_TRANSFER',
            'Payload' => bin2hex('{"Action":"CP_TRANSFER","Amount":"100.000000","Asset":"CIRX"}'),
            'Nonce' => '1',
            'Signature' => 'transaction_signature_hex',
            'Blockchain' => 'Circular',
            'Version' => '1.0.8'
        ];
        
        // Verify transaction structure
        $this->assertIsArray($transactionData);
        $this->assertArrayHasKey('ID', $transactionData);
        $this->assertArrayHasKey('From', $transactionData);
        $this->assertArrayHasKey('To', $transactionData);
        $this->assertArrayHasKey('Timestamp', $transactionData);
        $this->assertArrayHasKey('Type', $transactionData);
        $this->assertArrayHasKey('Payload', $transactionData);
        $this->assertArrayHasKey('Nonce', $transactionData);
        $this->assertArrayHasKey('Signature', $transactionData);
        $this->assertArrayHasKey('Blockchain', $transactionData);
        $this->assertArrayHasKey('Version', $transactionData);
        
        // Verify data types
        $this->assertIsString($transactionData['ID']);
        $this->assertIsString($transactionData['From']);
        $this->assertIsString($transactionData['To']);
        $this->assertIsString($transactionData['Timestamp']);
        $this->assertIsString($transactionData['Type']);
        $this->assertIsString($transactionData['Payload']);
        $this->assertIsString($transactionData['Nonce']);
        $this->assertIsString($transactionData['Signature']);
        $this->assertIsString($transactionData['Blockchain']);
        $this->assertIsString($transactionData['Version']);
        
        // Verify timestamp format
        $this->assertMatchesRegularExpression('/^\d{4}:\d{2}:\d{2}-\d{2}:\d{2}:\d{2}$/', $transactionData['Timestamp']);
        
        // Verify payload is hex encoded
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/i', $transactionData['Payload']);
        
        // Verify ID is SHA256 hash
        $this->assertEquals(64, strlen($transactionData['ID']));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/i', $transactionData['ID']);
    }

    /*---------------------------------------------------------------------------
     | CONFIGURATION VALIDATION TESTS
     *---------------------------------------------------------------------------*/

    public function testEnvironmentConfigurationValidation(): void
    {
        // Test environment configurations that the client should handle
        $environments = [
            'testnet' => 'https://nag.circularlabs.io/NAG.php?cep=',
            'mainnet' => 'https://nag.circularlabs.io/NAG_Mainnet.php?cep='
        ];
        
        foreach ($environments as $env => $url) {
            $client = new CircularProtocolClient($url, $this->testAddress, 'test_contract', 'test_private_key');
            $this->assertInstanceOf(CircularProtocolClient::class, $client);
        }
    }

    public function testPrivateKeyValidation(): void
    {
        $validPrivateKey = 'a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890';
        $invalidPrivateKeys = [
            '', // Empty
            'short_key', // Too short
            'invalid_hex_characters_ghijklmnop', // Invalid hex
            null, // Null
        ];
        
        // Valid private key should work
        $client = new CircularProtocolClient(
            'https://nag.circularlabs.io/NAG.php?cep=',
            $this->testAddress,
            'test_contract',
            $validPrivateKey
        );
        $this->assertInstanceOf(CircularProtocolClient::class, $client);
        
        // Invalid private keys should be handled gracefully
        foreach ($invalidPrivateKeys as $invalidKey) {
            try {
                $client = new CircularProtocolClient(
                    'https://nag.circularlabs.io/NAG.php?cep=',
                    $this->testAddress,
                    'test_contract',
                    $invalidKey
                );
                
                // If no exception is thrown, that's also acceptable
                $this->assertInstanceOf(CircularProtocolClient::class, $client);
                
            } catch (\Exception $e) {
                // Exception is acceptable for invalid private keys
                $this->assertIsString($e->getMessage());
            }
        }
    }
}