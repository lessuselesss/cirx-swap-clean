<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TransactionMonitoringService;
use App\Models\Transaction;
use Psr\Log\LoggerInterface;
use Carbon\Carbon;
use Mockery;

/**
 * @covers \App\Services\TransactionMonitoringService
 */
class TransactionMonitoringServiceTest extends TestCase
{
    private TransactionMonitoringService $service;
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->service = new TransactionMonitoringService($this->mockLogger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_detects_stuck_transactions()
    {
        // Create a transaction stuck in payment_verified state for over 30 minutes
        $stuckTransaction = Transaction::create($this->createTransaction([
            'payment_tx_id' => '0xstuck123',
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
            'updated_at' => Carbon::now()->subMinutes(45), // 45 minutes ago
        ]));

        // Expect critical log
        $this->mockLogger->shouldReceive('critical')
            ->once()
            ->with('Stuck transactions detected', Mockery::type('array'));

        $alerts = $this->service->checkStuckTransactions();

        $this->assertCount(1, $alerts);
        $this->assertEquals('critical', $alerts[0]['severity']);
        $this->assertEquals('stuck_transactions', $alerts[0]['type']);
        $this->assertEquals(1, $alerts[0]['count']);
        $this->assertContains($stuckTransaction->id, $alerts[0]['transaction_ids']);
        $this->assertStringContainsString('CRITICAL: 1 transactions stuck', $alerts[0]['message']);
    }

    public function test_no_alerts_for_recent_transactions()
    {
        // Create a recent transaction in payment_verified state
        Transaction::create($this->createTransaction([
            'payment_tx_id' => '0xrecent123',
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
            'updated_at' => Carbon::now()->subMinutes(10), // 10 minutes ago - within threshold
        ]));

        $this->mockLogger->shouldNotReceive('critical');

        $alerts = $this->service->checkStuckTransactions();

        $this->assertEmpty($alerts);
    }

    public function test_detects_high_failure_rates()
    {
        // Create transactions with high failure rate (50% failure)
        Transaction::create($this->createTransaction([
            'payment_tx_id' => '0xfailed1',
            'swap_status' => Transaction::STATUS_FAILED_CIRX_TRANSFER,
            'created_at' => Carbon::now()->subMinutes(30),
        ]));

        Transaction::create($this->createTransaction([
            'payment_tx_id' => '0xsuccess1',
            'swap_status' => Transaction::STATUS_COMPLETED,
            'created_at' => Carbon::now()->subMinutes(30),
        ]));

        // Expect warning log for high failure rate
        $this->mockLogger->shouldReceive('warning')
            ->once()
            ->with('High CIRX transfer failure rate', Mockery::type('array'));

        $alerts = $this->service->checkTransferFailureRates();

        $this->assertCount(1, $alerts);
        $this->assertEquals('high', $alerts[0]['severity']);
        $this->assertEquals('high_failure_rate', $alerts[0]['type']);
        $this->assertEquals(50.0, $alerts[0]['failure_rate_percent']);
    }

    public function test_detects_wallet_configuration_failures()
    {
        // Create multiple transactions with wallet configuration failures
        for ($i = 0; $i < 4; $i++) {
            Transaction::create($this->createTransaction([
                'payment_tx_id' => "0xwallet{$i}",
                'swap_status' => Transaction::STATUS_FAILED_CIRX_TRANSFER,
                'failure_reason' => 'CIRX wallet not configured',
                'created_at' => Carbon::now()->subMinutes(5),
            ]));
        }

        // Expect critical log for wallet config failures
        $this->mockLogger->shouldReceive('critical')
            ->once()
            ->with('CIRX wallet configuration failure', Mockery::type('array'));

        $alerts = $this->service->checkWalletConfigurationFailures();

        $this->assertCount(1, $alerts);
        $this->assertEquals('critical', $alerts[0]['severity']);
        $this->assertEquals('wallet_config_failure', $alerts[0]['type']);
        $this->assertEquals(4, $alerts[0]['failure_count']);
        $this->assertStringContainsString('URGENT: Check environment variables', $alerts[0]['investigation_hints'][0]);
    }

    public function test_generates_comprehensive_monitoring_report()
    {
        // Create test data
        Transaction::create($this->createTransaction([
            'payment_tx_id' => '0xstuck',
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
            'updated_at' => Carbon::now()->subMinutes(45),
        ]));

        Transaction::create($this->createTransaction([
            'payment_tx_id' => '0xfailed',
            'swap_status' => Transaction::STATUS_FAILED_CIRX_TRANSFER,
            'failure_reason' => 'CIRX wallet not configured',
            'created_at' => Carbon::now()->subMinutes(5),
        ]));

        // Mock logger expectations
        $this->mockLogger->shouldReceive('critical')->twice();

        $report = $this->service->generateMonitoringReport();

        $this->assertArrayHasKey('timestamp', $report);
        $this->assertArrayHasKey('alerts', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('alert_count', $report);
        $this->assertArrayHasKey('highest_severity', $report);

        $this->assertGreaterThan(0, $report['alert_count']);
        $this->assertEquals('critical', $report['highest_severity']);
    }

    public function test_system_health_status()
    {
        // No alerts = healthy system
        $this->assertTrue($this->service->isSystemHealthy());

        $healthStatus = $this->service->getHealthStatus();
        $this->assertEquals('healthy', $healthStatus['status']);
        $this->assertArrayHasKey('timestamp', $healthStatus);
        $this->assertArrayHasKey('stuck_transactions', $healthStatus);
    }

    public function test_system_unhealthy_with_critical_alerts()
    {
        // Create stuck transaction to trigger critical alert
        Transaction::create($this->createTransaction([
            'payment_tx_id' => '0xstuck',
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
            'updated_at' => Carbon::now()->subMinutes(45),
        ]));

        $this->mockLogger->shouldReceive('critical')->once();

        $this->assertFalse($this->service->isSystemHealthy());

        $healthStatus = $this->service->getHealthStatus();
        $this->assertEquals('unhealthy', $healthStatus['status']);
    }

    public function test_summary_statistics_calculation()
    {
        // Create test transactions for last 24 hours
        Transaction::create($this->createTransaction([
            'payment_tx_id' => '0xcompleted',
            'swap_status' => Transaction::STATUS_COMPLETED,
            'created_at' => Carbon::now()->subHours(2),
        ]));

        Transaction::create($this->createTransaction([
            'payment_tx_id' => '0xfailed',
            'swap_status' => Transaction::STATUS_FAILED_CIRX_TRANSFER,
            'created_at' => Carbon::now()->subHours(3),
        ]));

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateSummaryStatistics');
        $method->setAccessible(true);

        $summary = $method->invoke($this->service);

        $this->assertArrayHasKey('last_24_hours', $summary);
        $this->assertArrayHasKey('current_issues', $summary);

        $last24h = $summary['last_24_hours'];
        $this->assertEquals(2, $last24h['total_transactions']);
        $this->assertEquals(1, $last24h['completed_transactions']);
        $this->assertEquals(1, $last24h['failed_transactions']);
        $this->assertEquals(50.0, $last24h['success_rate_percent']);
    }

    public function test_handles_environment_variable_thresholds()
    {
        // Test that service respects environment variable configuration
        $_ENV['ALERT_STUCK_PAYMENT_MINUTES'] = '60';
        $_ENV['ALERT_FAILED_TRANSFER_PERCENT'] = '10.0';
        $_ENV['ALERT_WALLET_CONFIG_FAILURES'] = '1';

        $service = new TransactionMonitoringService($this->mockLogger);

        // Create a transaction stuck for 45 minutes (under 60 minute threshold)
        Transaction::create($this->createTransaction([
            'payment_tx_id' => '0xstuck',
            'swap_status' => Transaction::STATUS_PAYMENT_VERIFIED,
            'updated_at' => Carbon::now()->subMinutes(45),
        ]));

        // Should not trigger alert with 60-minute threshold
        $alerts = $service->checkStuckTransactions();
        $this->assertEmpty($alerts);

        // Clean up environment
        unset($_ENV['ALERT_STUCK_PAYMENT_MINUTES']);
        unset($_ENV['ALERT_FAILED_TRANSFER_PERCENT']);
        unset($_ENV['ALERT_WALLET_CONFIG_FAILURES']);
    }
}