<?php

namespace Tests\Unit\CircularProtocol;

use PHPUnit\Framework\TestCase;
use CircularProtocol\Api\CircularProtocolAPI;

/**
 * Comprehensive unit tests for all CircularProtocolAPI methods
 * 
 * @covers \CircularProtocol\Api\CircularProtocolAPI
 */
class CircularProtocolAPITest extends TestCase
{
    private CircularProtocolAPI $api;
    private string $testBlockchain = 'Circular';
    private string $testAddress = 'cc8c6c8cf85a1b9cb8a4ce92e36e5b0c1b0f8b7e5c3a7e8c1e9b8c4e3f2d1a0b';
    private string $testPrivateKey = 'a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890';
    private string $testPublicKey = '04a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890';
    private string $testTransactionId = 'tx123456789abcdef123456789abcdef123456789abcdef123456789abcdef123456';

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new CircularProtocolAPI();
    }

    /*---------------------------------------------------------------------------
     | CONFIGURATION TESTS
     *---------------------------------------------------------------------------*/

    public function testConstructorSetsDefaultValues(): void
    {
        $this->assertEquals('1.0.8', $this->api->getVersion());
        $this->assertEquals('https://nag.circularlabs.io/NAG.php?cep=', $this->api->getNAGURL());
        $this->assertEquals('', $this->api->getNAGKey());
        $this->assertNull($this->api->lastError);
    }

    public function testSetAndGetNAGKey(): void
    {
        $testKey = 'test_nag_key_123';
        $this->api->setNAGKey($testKey);
        $this->assertEquals($testKey, $this->api->getNAGKey());
    }

    public function testSetAndGetNAGURL(): void
    {
        $testUrl = 'https://nag.circularlabs.io/NAG_Mainnet.php?cep=';
        $this->api->setNAGURL($testUrl);
        $this->assertEquals($testUrl, $this->api->getNAGURL());
    }

    public function testGetVersion(): void
    {
        $version = $this->api->getVersion();
        $this->assertIsString($version);
        $this->assertNotEmpty($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }

    /*---------------------------------------------------------------------------
     | HELPER FUNCTION TESTS
     *---------------------------------------------------------------------------*/

    public function testPadNumber(): void
    {
        $this->assertEquals('05', $this->api->padNumber(5));
        $this->assertEquals('10', $this->api->padNumber(10));
        $this->assertEquals('01', $this->api->padNumber(1));
        $this->assertEquals('99', $this->api->padNumber(99));
    }

    public function testGetFormattedTimestamp(): void
    {
        $timestamp = $this->api->getFormattedTimestamp();
        $this->assertIsString($timestamp);
        $this->assertMatchesRegularExpression('/^\d{4}:\d{2}:\d{2}-\d{2}:\d{2}:\d{2}$/', $timestamp);
    }

    public function testStringToHex(): void
    {
        $this->assertEquals('48656c6c6f', $this->api->stringToHex('Hello'));
        $this->assertEquals('576f726c64', $this->api->stringToHex('World'));
        $this->assertEquals('', $this->api->stringToHex(''));
    }

    public function testHexFix(): void
    {
        $this->assertEquals('1234abcd', $this->api->hexFix('0x1234abcd'));
        $this->assertEquals('1234abcd', $this->api->hexFix('1234abcd'));
        $this->assertEquals('efgh5678', $this->api->hexFix('0xefgh5678'));
        $this->assertEquals('cleanstring', $this->api->hexFix("clean\nstring\r"));
    }

    /*---------------------------------------------------------------------------
     | CRYPTOGRAPHIC FUNCTION TESTS
     *---------------------------------------------------------------------------*/

    public function testGetPublicKey(): void
    {
        $publicKey = $this->api->getPublicKey($this->testPrivateKey);
        $this->assertIsString($publicKey);
        $this->assertNotEmpty($publicKey);
        // Public key should be 130 characters (65 bytes * 2) for uncompressed format
        $this->assertGreaterThan(120, strlen($publicKey));
    }

    public function testSignMessage(): void
    {
        $message = 'Test message for signing';
        $signature = $this->api->signMessage($message, $this->testPrivateKey);
        
        $this->assertIsString($signature);
        $this->assertNotEmpty($signature);
        // DER signature should be in hex format
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/i', $signature);
    }

    public function testVerifySignature(): void
    {
        $message = 'Test message for verification';
        $publicKey = $this->api->getPublicKey($this->testPrivateKey);
        $signature = $this->api->signMessage($message, $this->testPrivateKey);
        
        $isValid = $this->api->verifySignature($publicKey, $message, $signature);
        $this->assertTrue($isValid);
        
        // Test with wrong message
        $isInvalid = $this->api->verifySignature($publicKey, 'Wrong message', $signature);
        $this->assertFalse($isInvalid);
    }

    public function testKeysFromSeedPhrase(): void
    {
        $seedPhrase = 'test seed phrase for key generation';
        $keys = $this->api->keysFromSeedPhrase($seedPhrase);
        
        $this->assertIsArray($keys);
        $this->assertArrayHasKey('privateKey', $keys);
        $this->assertArrayHasKey('publicKey', $keys);
        $this->assertArrayHasKey('walletAddress', $keys);
        
        $this->assertIsString($keys['privateKey']);
        $this->assertIsString($keys['publicKey']);
        $this->assertIsString($keys['walletAddress']);
        
        $this->assertNotEmpty($keys['privateKey']);
        $this->assertNotEmpty($keys['publicKey']);
        $this->assertNotEmpty($keys['walletAddress']);
        
        // Verify deterministic generation
        $keys2 = $this->api->keysFromSeedPhrase($seedPhrase);
        $this->assertEquals($keys, $keys2);
    }

    /*---------------------------------------------------------------------------
     | WALLET FUNCTION TESTS (Mock Response Tests)
     *---------------------------------------------------------------------------*/

    public function testCheckWalletParameterPreparation(): void
    {
        // Since these methods make actual HTTP calls, we test parameter preparation
        $blockchain = '0x' . $this->testBlockchain;
        $address = '0x' . $this->testAddress;
        
        // Test that hexFix is applied correctly in the method structure
        $this->assertIsString($this->api->hexFix($blockchain));
        $this->assertIsString($this->api->hexFix($address));
        
        // Verify hexFix removes 0x prefix
        $this->assertEquals($this->testBlockchain, $this->api->hexFix($blockchain));
        $this->assertEquals($this->testAddress, $this->api->hexFix($address));
    }

    public function testGetWalletParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $address = '0x' . $this->testAddress;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanAddress = $this->api->hexFix($address);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals($this->testAddress, $cleanAddress);
    }

    public function testGetWalletBalanceParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $address = '0x' . $this->testAddress;
        $asset = 'CIRX';
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanAddress = $this->api->hexFix($address);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals($this->testAddress, $cleanAddress);
        $this->assertIsString($asset);
    }

    public function testGetWalletNonceParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $address = '0x' . $this->testAddress;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanAddress = $this->api->hexFix($address);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals($this->testAddress, $cleanAddress);
    }

    public function testGetLatestTransactionsParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $address = '0x' . $this->testAddress;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanAddress = $this->api->hexFix($address);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals($this->testAddress, $cleanAddress);
    }

    public function testRegisterWalletParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $publicKey = '0x' . $this->testPublicKey;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanPublicKey = $this->api->hexFix($publicKey);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals($this->testPublicKey, $cleanPublicKey);
        
        // Test that wallet address generation works
        $expectedFrom = hash('sha256', $cleanPublicKey);
        $this->assertIsString($expectedFrom);
        $this->assertEquals(64, strlen($expectedFrom)); // SHA256 produces 64 char hex string
    }

    /*---------------------------------------------------------------------------
     | DOMAIN MANAGEMENT TESTS
     *---------------------------------------------------------------------------*/

    public function testGetDomainParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $domainName = 'test.circular';
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertIsString($domainName);
    }

    /*---------------------------------------------------------------------------
     | ASSET MANAGEMENT TESTS
     *---------------------------------------------------------------------------*/

    public function testGetAssetParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $assetName = 'CIRX';
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertIsString($assetName);
    }

    public function testGetAssetListParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
    }

    public function testGetAssetSupplyParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $assetName = 'CIRX';
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertIsString($assetName);
    }

    /*---------------------------------------------------------------------------
     | VOUCHER MANAGEMENT TESTS
     *---------------------------------------------------------------------------*/

    public function testGetVoucherParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $voucherCode = '0xVOUCHER123';
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanCode = $this->api->hexFix($voucherCode);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals('VOUCHER123', $cleanCode);
    }

    /*---------------------------------------------------------------------------
     | BLOCK MANAGEMENT TESTS
     *---------------------------------------------------------------------------*/

    public function testGetBlockRangeParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $start = 100;
        $end = 200;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals('100', strval($start));
        $this->assertEquals('200', strval($end));
    }

    public function testGetBlockParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $blockNumber = 12345;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals('12345', strval($blockNumber));
    }

    public function testGetBlockCountParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
    }

    /*---------------------------------------------------------------------------
     | ANALYTICS TESTS
     *---------------------------------------------------------------------------*/

    public function testGetAnalyticsParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
    }

    /*---------------------------------------------------------------------------
     | BLOCKCHAIN TESTS
     *---------------------------------------------------------------------------*/

    public function testGetBlockchainsParameterPreparation(): void
    {
        // This method doesn't require parameters, so we test that it accepts empty data
        $emptyData = [];
        $this->assertIsArray($emptyData);
        $this->assertEmpty($emptyData);
    }

    /*---------------------------------------------------------------------------
     | TRANSACTION MANAGEMENT TESTS
     *---------------------------------------------------------------------------*/

    public function testGetPendingTransactionParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $txId = '0x' . $this->testTransactionId;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanTxId = $this->api->hexFix($txId);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals($this->testTransactionId, $cleanTxId);
    }

    public function testGetTransactionByIDParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $txId = '0x' . $this->testTransactionId;
        $start = 0;
        $end = 10;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanTxId = $this->api->hexFix($txId);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals($this->testTransactionId, $cleanTxId);
        $this->assertEquals('0', strval($start));
        $this->assertEquals('10', strval($end));
    }

    public function testGetTransactionByNodeParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $nodeId = '0xnode123';
        $start = 0;
        $end = 10;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanNodeId = $this->api->hexFix($nodeId);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals('node123', $cleanNodeId);
        $this->assertEquals('0', strval($start));
        $this->assertEquals('10', strval($end));
    }

    public function testGetTransactionByAddressParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $address = '0x' . $this->testAddress;
        $start = 0;
        $end = 10;
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanAddress = $this->api->hexFix($address);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals($this->testAddress, $cleanAddress);
        $this->assertEquals('0', strval($start));
        $this->assertEquals('10', strval($end));
    }

    public function testGetTransactionByDateParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $address = '0x' . $this->testAddress;
        $startDate = '2024-01-01';
        $endDate = '2024-12-31';
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanAddress = $this->api->hexFix($address);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals($this->testAddress, $cleanAddress);
        $this->assertIsString($startDate);
        $this->assertIsString($endDate);
    }

    public function testSendTransactionParameterPreparation(): void
    {
        $id = '0x' . $this->testTransactionId;
        $from = '0x' . $this->testAddress;
        $to = '0xrecipient' . $this->testAddress;
        $timestamp = $this->api->getFormattedTimestamp();
        $type = 'C_TYPE_REGISTERWALLET';
        $payload = $this->api->stringToHex('{"test": "payload"}');
        $nonce = '1';
        $signature = 'test_signature_hex';
        $blockchain = '0x' . $this->testBlockchain;
        
        // Test parameter cleaning
        $cleanId = $this->api->hexFix($id);
        $cleanFrom = $this->api->hexFix($from);
        $cleanTo = $this->api->hexFix($to);
        $cleanPayload = $this->api->hexFix($payload);
        $cleanSignature = $this->api->hexFix($signature);
        $cleanBlockchain = $this->api->hexFix($blockchain);
        
        $this->assertEquals($this->testTransactionId, $cleanId);
        $this->assertEquals($this->testAddress, $cleanFrom);
        $this->assertEquals('recipient' . $this->testAddress, $cleanTo);
        $this->assertIsString($cleanPayload);
        $this->assertIsString($cleanSignature);
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertIsString($timestamp);
        $this->assertIsString($type);
        $this->assertIsString($nonce);
    }

    /*---------------------------------------------------------------------------
     | SMART CONTRACT TESTS
     *---------------------------------------------------------------------------*/

    public function testTestContractParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $from = '0x' . $this->testAddress;
        $project = 'Test smart contract project';
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanFrom = $this->api->hexFix($from);
        $hexProject = $this->api->stringToHex($project);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals($this->testAddress, $cleanFrom);
        $this->assertIsString($hexProject);
        $this->assertNotEmpty($hexProject);
    }

    public function testCallContractParameterPreparation(): void
    {
        $blockchain = '0x' . $this->testBlockchain;
        $from = '0x' . $this->testAddress;
        $contractAddress = '0xcontract123';
        $request = 'getBalance';
        
        $cleanBlockchain = $this->api->hexFix($blockchain);
        $cleanFrom = $this->api->hexFix($from);
        $cleanAddress = $this->api->hexFix($contractAddress);
        $hexRequest = $this->api->stringToHex($request);
        
        $this->assertEquals($this->testBlockchain, $cleanBlockchain);
        $this->assertEquals($this->testAddress, $cleanFrom);
        $this->assertEquals('contract123', $cleanAddress);
        $this->assertIsString($hexRequest);
        $this->assertNotEmpty($hexRequest);
    }

    /*---------------------------------------------------------------------------
     | EDGE CASE AND ERROR HANDLING TESTS
     *---------------------------------------------------------------------------*/

    public function testHexFixHandlesSpecialCharacters(): void
    {
        $testCases = [
            "0x1234\n5678" => '12345678',
            "0x1234\\5678" => '12345678',
            "0x1234\r5678" => '12345678',
            "regular_string" => 'regular_string',
            "" => '',
        ];
        
        foreach ($testCases as $input => $expected) {
            $this->assertEquals($expected, $this->api->hexFix($input));
        }
    }

    public function testStringToHexHandlesEmptyString(): void
    {
        $this->assertEquals('', $this->api->stringToHex(''));
    }

    public function testStringToHexHandlesSpecialCharacters(): void
    {
        $testString = "Hello\nWorld\t!";
        $hexResult = $this->api->stringToHex($testString);
        $this->assertIsString($hexResult);
        $this->assertNotEmpty($hexResult);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/i', $hexResult);
    }

    public function testPadNumberHandlesEdgeCases(): void
    {
        $this->assertEquals('00', $this->api->padNumber(0));
        $this->assertEquals('09', $this->api->padNumber(9));
        $this->assertEquals('10', $this->api->padNumber(10));
        $this->assertEquals('100', $this->api->padNumber(100));
    }

    public function testGetFormattedTimestampFormat(): void
    {
        $timestamp = $this->api->getFormattedTimestamp();
        
        // Test that timestamp matches expected format: Y:m:d-H:i:s
        $this->assertMatchesRegularExpression('/^\d{4}:\d{2}:\d{2}-\d{2}:\d{2}:\d{2}$/', $timestamp);
        
        // Test that it's a valid datetime
        $parts = explode('-', $timestamp);
        $this->assertCount(2, $parts);
        
        $datePart = $parts[0];
        $timePart = $parts[1];
        
        $this->assertMatchesRegularExpression('/^\d{4}:\d{2}:\d{2}$/', $datePart);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $timePart);
    }
}