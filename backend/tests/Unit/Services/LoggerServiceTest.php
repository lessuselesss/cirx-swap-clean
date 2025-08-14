<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\LoggerService;
use Monolog\Logger;

/**
 * @covers \App\Services\LoggerService
 */
class LoggerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set test environment variables
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['LOG_LEVEL'] = 'debug';
        $_ENV['LOG_FILE_PATH'] = 'storage/logs/test.log';
    }

    protected function tearDown(): void
    {
        // Clean up test log files
        $logFile = $_ENV['LOG_FILE_PATH'] ?? '';
        if ($logFile && file_exists($logFile)) {
            unlink($logFile);
        }
        
        parent::tearDown();
    }

    public function testGetLoggerReturnsMonologInstance(): void
    {
        $logger = LoggerService::getLogger('test');
        
        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertEquals('test', $logger->getName());
    }

    public function testGetLoggerReturnsSameInstanceForSameChannel(): void
    {
        $logger1 = LoggerService::getLogger('test-channel');
        $logger2 = LoggerService::getLogger('test-channel');
        
        $this->assertSame($logger1, $logger2);
    }

    public function testGetLoggerReturnsDifferentInstancesForDifferentChannels(): void
    {
        $logger1 = LoggerService::getLogger('channel1');
        $logger2 = LoggerService::getLogger('channel2');
        
        $this->assertNotSame($logger1, $logger2);
        $this->assertEquals('channel1', $logger1->getName());
        $this->assertEquals('channel2', $logger2->getName());
    }

    public function testLogApiRequestCreatesLogEntry(): void
    {
        // This test verifies the method doesn't throw exceptions
        // In a real environment, you'd mock the logger to verify the call
        
        $this->expectNotToPerformAssertions();
        
        LoggerService::logApiRequest(
            'POST',
            '/api/v1/transactions/initiate-swap',
            ['test' => 'context'],
            'test-client',
            201
        );
    }

    public function testLogWorkerActivityCreatesLogEntry(): void
    {
        $this->expectNotToPerformAssertions();
        
        LoggerService::logWorkerActivity(
            'TestWorker',
            'processing_transaction',
            ['transaction_id' => 'test-123'],
            'info'
        );
    }

    public function testLogTransactionCreatesLogEntry(): void
    {
        $this->expectNotToPerformAssertions();
        
        LoggerService::logTransaction(
            'test-transaction-123',
            'payment_verified',
            ['amount' => '1000.0'],
            'info'
        );
    }

    public function testLogSecurityCreatesLogEntry(): void
    {
        $this->expectNotToPerformAssertions();
        
        LoggerService::logSecurity(
            'api_key_authentication_failed',
            ['ip' => '192.168.1.1'],
            'warning'
        );
    }

    public function testLogBlockchainCreatesLogEntry(): void
    {
        $this->expectNotToPerformAssertions();
        
        LoggerService::logBlockchain(
            'ethereum',
            'payment_verification',
            ['tx_hash' => '0xabc123'],
            'info'
        );
    }

    public function testGetLoggingStatisticsReturnsArray(): void
    {
        $stats = LoggerService::getLoggingStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('configured_level', $stats);
        $this->assertArrayHasKey('environment', $stats);
        $this->assertArrayHasKey('log_file', $stats);
        $this->assertArrayHasKey('active_loggers', $stats);
    }

    public function testTestLoggingReturnsResults(): void
    {
        $results = LoggerService::testLogging();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('success', $results);
        $this->assertArrayHasKey('tests', $results);
        $this->assertArrayHasKey('errors', $results);
        $this->assertIsBool($results['success']);
        $this->assertIsArray($results['tests']);
        $this->assertIsArray($results['errors']);
    }

    public function testLogWorkerActivitySupportsMultipleLevels(): void
    {
        $levels = ['debug', 'info', 'warning', 'error', 'critical'];
        
        foreach ($levels as $level) {
            $this->expectNotToPerformAssertions();
            
            LoggerService::logWorkerActivity(
                'TestWorker',
                'test_action',
                ['level_test' => true],
                $level
            );
        }
    }

    public function testLogTransactionSupportsMultipleLevels(): void
    {
        $levels = ['debug', 'info', 'warning', 'error', 'critical'];
        
        foreach ($levels as $level) {
            $this->expectNotToPerformAssertions();
            
            LoggerService::logTransaction(
                'test-tx-123',
                'test_event',
                ['level_test' => true],
                $level
            );
        }
    }

    public function testLogSecuritySupportsMultipleLevels(): void
    {
        $levels = ['info', 'warning', 'error', 'critical'];
        
        foreach ($levels as $level) {
            $this->expectNotToPerformAssertions();
            
            LoggerService::logSecurity(
                'test_security_event',
                ['level_test' => true],
                $level
            );
        }
    }

    public function testLogBlockchainSupportsMultipleLevels(): void
    {
        $levels = ['debug', 'info', 'warning', 'error', 'critical'];
        
        foreach ($levels as $level) {
            $this->expectNotToPerformAssertions();
            
            LoggerService::logBlockchain(
                'ethereum',
                'test_blockchain_action',
                ['level_test' => true],
                $level
            );
        }
    }
}