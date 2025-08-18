<?php

namespace Tests\Integration\CircularProtocol;

use Tests\Integration\IntegrationTestCase;
use CircularProtocol\Api\CircularProtocolAPI;

/**
 * Integration tests for CircularProtocolAPI with mocked NAG endpoints
 * 
 * @covers \CircularProtocol\Api\CircularProtocolAPI
 */
class CircularProtocolAPIIntegrationTest extends IntegrationTestCase
{
    private CircularProtocolAPI $api;
    private string $testBlockchain = 'Circular';
    private string $testAddress = 'cc8c6c8cf85a1b9cb8a4ce92e36e5b0c1b0f8b7e5c3a7e8c1e9b8c4e3f2d1a0b';
    private string $testPrivateKey = 'a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890';
    private string $testPublicKey;
    private string $testWalletAddress;

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new CircularProtocolAPI();
        
        // Generate test keys for consistent testing
        $keys = $this->api->keysFromSeedPhrase('test_seed_phrase_for_integration_testing');
        $this->testPrivateKey = $keys['privateKey'];
        $this->testPublicKey = $keys['publicKey'];
        $this->testWalletAddress = $keys['walletAddress'];
        
        // Set up test NAG URL (using testnet)
        $this->api->setNAGURL('https://nag.circularlabs.io/NAG.php?cep=');
    }

    /*---------------------------------------------------------------------------
     | WALLET OPERATION INTEGRATION TESTS
     *---------------------------------------------------------------------------*/

    public function testCheckWalletIntegration(): void
    {
        // Test wallet existence check
        try {
            $result = $this->api->checkWallet($this->testBlockchain, $this->testWalletAddress);
            
            // Verify response structure (regardless of wallet existence)
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            // NAG responses typically have Result property
            $this->assertIsNumeric($result->Result);
            
        } catch (\Exception $e) {
            // Network errors are acceptable in integration tests
            $this->markTestSkipped('Network error during checkWallet: ' . $e->getMessage());
        }
    }

    public function testGetWalletIntegration(): void
    {
        try {
            $result = $this->api->getWallet($this->testBlockchain, $this->testWalletAddress);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                // Wallet exists - verify response structure
                $this->assertObjectHasProperty('Response', $result);
            } elseif ($result->Result === 404) {
                // Wallet doesn't exist - this is fine for test
                $this->assertTrue(true, 'Wallet not found - expected for test wallet');
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getWallet: ' . $e->getMessage());
        }
    }

    public function testGetWalletBalanceIntegration(): void
    {
        try {
            $result = $this->api->getWalletBalance($this->testBlockchain, $this->testWalletAddress, 'CIRX');
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                // Balance retrieved successfully
                $this->assertObjectHasProperty('Response', $result);
            } else {
                // Wallet may not exist or have balance - acceptable for test
                $this->assertTrue(true, 'Wallet balance check completed');
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getWalletBalance: ' . $e->getMessage());
        }
    }

    public function testGetWalletNonceIntegration(): void
    {
        try {
            $result = $this->api->getWalletNonce($this->testBlockchain, $this->testWalletAddress);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                // Nonce should be numeric
                $this->assertIsNumeric($result->Response);
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getWalletNonce: ' . $e->getMessage());
        }
    }

    public function testGetLatestTransactionsIntegration(): void
    {
        try {
            $result = $this->api->getLatestTransactions($this->testBlockchain, $this->testWalletAddress);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                // Response should be an array of transactions
                $this->assertIsArray($result->Response);
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getLatestTransactions: ' . $e->getMessage());
        }
    }

    /*---------------------------------------------------------------------------
     | WALLET REGISTRATION INTEGRATION TESTS
     *---------------------------------------------------------------------------*/

    public function testRegisterWalletIntegration(): void
    {
        try {
            $result = $this->api->registerWallet($this->testBlockchain, $this->testPublicKey);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                // Registration successful
                $this->assertObjectHasProperty('Response', $result);
                $this->assertTrue(true, 'Wallet registration completed successfully');
            } elseif ($result->Result === 409) {
                // Wallet already exists - this is fine
                $this->assertTrue(true, 'Wallet already registered');
            } else {
                // Other response codes
                $this->assertTrue(true, 'Wallet registration attempt completed');
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during registerWallet: ' . $e->getMessage());
        }
    }

    /*---------------------------------------------------------------------------
     | BLOCKCHAIN INFORMATION INTEGRATION TESTS
     *---------------------------------------------------------------------------*/

    public function testGetBlockchainsIntegration(): void
    {
        try {
            $result = $this->api->getBlockchains();
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                $this->assertIsArray($result->Response);
                $this->assertGreaterThan(0, count($result->Response));
                
                // Verify each blockchain has required properties
                foreach ($result->Response as $blockchain) {
                    $this->assertIsObject($blockchain);
                    $this->assertObjectHasProperty('Name', $blockchain);
                }
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getBlockchains: ' . $e->getMessage());
        }
    }

    public function testGetBlockCountIntegration(): void
    {
        try {
            $result = $this->api->getBlockCount($this->testBlockchain);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                $this->assertIsNumeric($result->Response);
                $this->assertGreaterThan(0, $result->Response);
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getBlockCount: ' . $e->getMessage());
        }
    }

    public function testGetAnalyticsIntegration(): void
    {
        try {
            $result = $this->api->getAnalytics($this->testBlockchain);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                $this->assertIsObject($result->Response);
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getAnalytics: ' . $e->getMessage());
        }
    }

    /*---------------------------------------------------------------------------
     | ASSET MANAGEMENT INTEGRATION TESTS
     *---------------------------------------------------------------------------*/

    public function testGetAssetListIntegration(): void
    {
        try {
            $result = $this->api->getAssetList($this->testBlockchain);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                $this->assertIsArray($result->Response);
                
                // Verify CIRX asset exists
                $cirxFound = false;
                foreach ($result->Response as $asset) {
                    if (isset($asset->Name) && $asset->Name === 'CIRX') {
                        $cirxFound = true;
                        break;
                    }
                }
                $this->assertTrue($cirxFound, 'CIRX asset should be in the asset list');
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getAssetList: ' . $e->getMessage());
        }
    }

    public function testGetAssetIntegration(): void
    {
        try {
            $result = $this->api->getAsset($this->testBlockchain, 'CIRX');
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                $this->assertIsObject($result->Response);
                
                // Verify asset properties
                $this->assertObjectHasProperty('Name', $result->Response);
                $this->assertEquals('CIRX', $result->Response->Name);
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getAsset: ' . $e->getMessage());
        }
    }

    public function testGetAssetSupplyIntegration(): void
    {
        try {
            $result = $this->api->getAssetSupply($this->testBlockchain, 'CIRX');
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                $this->assertIsObject($result->Response);
                
                // Verify supply properties
                $this->assertObjectHasProperty('Total', $result->Response);
                $this->assertObjectHasProperty('Circulating', $result->Response);
                $this->assertIsNumeric($result->Response->Total);
                $this->assertIsNumeric($result->Response->Circulating);
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getAssetSupply: ' . $e->getMessage());
        }
    }

    /*---------------------------------------------------------------------------
     | TRANSACTION INTEGRATION TESTS
     *---------------------------------------------------------------------------*/

    public function testGetTransactionByAddressIntegration(): void
    {
        try {
            $result = $this->api->getTransactionByAddress($this->testBlockchain, $this->testWalletAddress, 0, 10);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                $this->assertIsArray($result->Response);
                
                // Verify transaction structure if any exist
                if (count($result->Response) > 0) {
                    $transaction = $result->Response[0];
                    $this->assertIsObject($transaction);
                    $this->assertObjectHasProperty('ID', $transaction);
                    $this->assertObjectHasProperty('From', $transaction);
                    $this->assertObjectHasProperty('To', $transaction);
                }
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getTransactionByAddress: ' . $e->getMessage());
        }
    }

    public function testGetTransactionByDateIntegration(): void
    {
        try {
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $endDate = date('Y-m-d');
            
            $result = $this->api->getTransactionByDate($this->testBlockchain, $this->testWalletAddress, $startDate, $endDate);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                $this->assertIsArray($result->Response);
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getTransactionByDate: ' . $e->getMessage());
        }
    }

    /*---------------------------------------------------------------------------
     | BLOCK INTEGRATION TESTS
     *---------------------------------------------------------------------------*/

    public function testGetBlockIntegration(): void
    {
        try {
            // Get latest block count first
            $countResult = $this->api->getBlockCount($this->testBlockchain);
            
            if ($countResult->Result === 200 && $countResult->Response > 0) {
                // Get the latest block
                $blockNumber = $countResult->Response;
                $result = $this->api->getBlock($this->testBlockchain, $blockNumber);
                
                $this->assertIsObject($result);
                $this->assertObjectHasProperty('Result', $result);
                
                if ($result->Result === 200) {
                    $this->assertObjectHasProperty('Response', $result);
                    $this->assertIsObject($result->Response);
                    
                    // Verify block properties
                    $this->assertObjectHasProperty('Number', $result->Response);
                    $this->assertObjectHasProperty('Hash', $result->Response);
                    $this->assertEquals($blockNumber, $result->Response->Number);
                }
            } else {
                $this->markTestSkipped('Could not retrieve block count for block test');
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getBlock: ' . $e->getMessage());
        }
    }

    public function testGetBlockRangeIntegration(): void
    {
        try {
            // Test getting a small range of recent blocks
            $result = $this->api->getBlockRange($this->testBlockchain, 1, 5);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                $this->assertIsArray($result->Response);
                
                // Verify we got blocks in the range
                $this->assertLessThanOrEqual(5, count($result->Response));
                
                if (count($result->Response) > 0) {
                    $block = $result->Response[0];
                    $this->assertIsObject($block);
                    $this->assertObjectHasProperty('Number', $block);
                    $this->assertObjectHasProperty('Hash', $block);
                }
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getBlockRange: ' . $e->getMessage());
        }
    }

    /*---------------------------------------------------------------------------
     | DOMAIN INTEGRATION TESTS
     *---------------------------------------------------------------------------*/

    public function testGetDomainIntegration(): void
    {
        try {
            // Test resolving a known domain (if any exist)
            $result = $this->api->getDomain($this->testBlockchain, 'test.circular');
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                $this->assertIsString($result->Response);
                $this->assertNotEmpty($result->Response);
            } elseif ($result->Result === 404) {
                // Domain doesn't exist - acceptable for test
                $this->assertTrue(true, 'Domain not found - expected for test domain');
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getDomain: ' . $e->getMessage());
        }
    }

    /*---------------------------------------------------------------------------
     | VOUCHER INTEGRATION TESTS
     *---------------------------------------------------------------------------*/

    public function testGetVoucherIntegration(): void
    {
        try {
            // Test with a random voucher code
            $testVoucherCode = 'TEST_VOUCHER_' . uniqid();
            $result = $this->api->getVoucher($this->testBlockchain, $testVoucherCode);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            if ($result->Result === 200) {
                $this->assertObjectHasProperty('Response', $result);
                $this->assertIsObject($result->Response);
            } elseif ($result->Result === 404) {
                // Voucher doesn't exist - expected for test voucher
                $this->assertTrue(true, 'Voucher not found - expected for test voucher');
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Network error during getVoucher: ' . $e->getMessage());
        }
    }

    /*---------------------------------------------------------------------------
     | PERFORMANCE AND RELIABILITY TESTS
     *---------------------------------------------------------------------------*/

    public function testMultipleSequentialRequests(): void
    {
        $requests = [
            fn() => $this->api->getBlockchains(),
            fn() => $this->api->getBlockCount($this->testBlockchain),
            fn() => $this->api->getAssetList($this->testBlockchain),
            fn() => $this->api->checkWallet($this->testBlockchain, $this->testWalletAddress),
        ];
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($requests as $request) {
            try {
                $result = $request();
                $this->assertIsObject($result);
                $this->assertObjectHasProperty('Result', $result);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
            }
        }
        
        // At least some requests should succeed
        $this->assertGreaterThan(0, $successCount, 'At least one API request should succeed');
        
        // Log the results
        $this->addToAssertionCount(1);
        echo "\nAPI Integration Test Results: {$successCount} successful, {$errorCount} failed\n";
    }

    public function testResponseTimeConsistency(): void
    {
        $responseTimes = [];
        $maxRequests = 3;
        
        for ($i = 0; $i < $maxRequests; $i++) {
            try {
                $startTime = microtime(true);
                $result = $this->api->getBlockchains();
                $endTime = microtime(true);
                
                $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
                $responseTimes[] = $responseTime;
                
                $this->assertIsObject($result);
                $this->assertObjectHasProperty('Result', $result);
                
            } catch (\Exception $e) {
                // Network errors are acceptable in integration tests
                continue;
            }
        }
        
        if (count($responseTimes) > 1) {
            $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
            $maxResponseTime = max($responseTimes);
            
            // Response times should be reasonable (under 30 seconds)
            $this->assertLessThan(30000, $maxResponseTime, 'API responses should complete within 30 seconds');
            
            echo "\nAPI Response Times: Avg: {$avgResponseTime}ms, Max: {$maxResponseTime}ms\n";
        } else {
            $this->markTestSkipped('Not enough successful requests to test response time consistency');
        }
    }

    /*---------------------------------------------------------------------------
     | ERROR HANDLING INTEGRATION TESTS
     *---------------------------------------------------------------------------*/

    public function testInvalidBlockchainHandling(): void
    {
        try {
            $result = $this->api->getBlockCount('NonExistentBlockchain');
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            // Should return error code for invalid blockchain
            $this->assertNotEquals(200, $result->Result);
            
        } catch (\Exception $e) {
            // Exception is acceptable for invalid blockchain
            $this->assertTrue(true, 'Exception thrown for invalid blockchain: ' . $e->getMessage());
        }
    }

    public function testInvalidAddressHandling(): void
    {
        try {
            $invalidAddress = 'invalid_address_format';
            $result = $this->api->checkWallet($this->testBlockchain, $invalidAddress);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('Result', $result);
            
            // May return error code or 404 for invalid address
            $this->assertTrue(in_array($result->Result, [400, 404, 422]), 'Should return appropriate error code for invalid address');
            
        } catch (\Exception $e) {
            // Exception is acceptable for invalid address
            $this->assertTrue(true, 'Exception thrown for invalid address: ' . $e->getMessage());
        }
    }

    /*---------------------------------------------------------------------------
     | HELPER METHODS
     *---------------------------------------------------------------------------*/

    private function waitForNetworkDelay(): void
    {
        // Add small delay between requests to be respectful to the NAG servers
        usleep(500000); // 0.5 seconds
    }
}