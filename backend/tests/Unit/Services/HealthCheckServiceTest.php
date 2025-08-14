<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\HealthCheckService;

/**
 * @covers \App\Services\HealthCheckService
 */
class HealthCheckServiceTest extends TestCase
{
    private HealthCheckService $healthCheckService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->healthCheckService = new HealthCheckService();
        
        // Set test environment variables
        $_ENV['HEALTH_CHECK_ENABLED'] = 'true';
        $_ENV['APP_ENV'] = 'testing';
    }

    protected function tearDown(): void
    {
        unset($_ENV['HEALTH_CHECK_ENABLED'], $_ENV['APP_ENV']);
        parent::tearDown();
    }

    public function testRunAllChecksReturnsHealthStatus(): void
    {
        $result = $this->healthCheckService->runAllChecks();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('duration_ms', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('summary', $result);
        
        $this->assertContains($result['status'], ['healthy', 'degraded', 'critical']);
        $this->assertIsFloat($result['duration_ms']);
        $this->assertIsArray($result['checks']);
        $this->assertIsArray($result['summary']);
    }

    public function testRunAllChecksWithHealthCheckDisabled(): void
    {
        $_ENV['HEALTH_CHECK_ENABLED'] = 'false';
        $service = new HealthCheckService();
        
        $result = $service->runAllChecks();
        
        $this->assertEquals('disabled', $result['status']);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('timestamp', $result);
    }

    public function testRunAllChecksIncludesExpectedChecks(): void
    {
        $result = $this->healthCheckService->runAllChecks();
        
        $expectedChecks = [
            'database',
            'logging',
            'file_system',
            'memory',
            'configuration',
            'workers',
            'external_services'
        ];
        
        foreach ($expectedChecks as $checkName) {
            $this->assertArrayHasKey($checkName, $result['checks']);
        }
    }

    public function testEachCheckHasRequiredFields(): void
    {
        $result = $this->healthCheckService->runAllChecks();
        
        foreach ($result['checks'] as $checkName => $check) {
            $this->assertArrayHasKey('status', $check, "Check {$checkName} missing status");
            $this->assertArrayHasKey('timestamp', $check, "Check {$checkName} missing timestamp");
            
            $this->assertContains($check['status'], ['healthy', 'degraded', 'critical'], 
                "Check {$checkName} has invalid status: {$check['status']}");
        }
    }

    public function testSummaryHasExpectedFields(): void
    {
        $result = $this->healthCheckService->runAllChecks();
        $summary = $result['summary'];
        
        $this->assertArrayHasKey('total_checks', $summary);
        $this->assertArrayHasKey('healthy', $summary);
        $this->assertArrayHasKey('degraded', $summary);
        $this->assertArrayHasKey('critical', $summary);
        $this->assertArrayHasKey('health_percentage', $summary);
        
        $this->assertIsInt($summary['total_checks']);
        $this->assertIsInt($summary['healthy']);
        $this->assertIsInt($summary['degraded']);
        $this->assertIsInt($summary['critical']);
        $this->assertIsFloat($summary['health_percentage']);
        
        // Verify counts add up
        $totalCounts = $summary['healthy'] + $summary['degraded'] + $summary['critical'];
        $this->assertEquals($summary['total_checks'], $totalCounts);
    }

    public function testGetQuickStatusReturnsBasicHealth(): void
    {
        $result = $this->healthCheckService->getQuickStatus();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        $this->assertContains($result['status'], ['healthy', 'critical', 'disabled']);
    }

    public function testGetQuickStatusWithHealthCheckDisabled(): void
    {
        $_ENV['HEALTH_CHECK_ENABLED'] = 'false';
        $service = new HealthCheckService();
        
        $result = $service->getQuickStatus();
        
        $this->assertEquals('disabled', $result['status']);
        $this->assertArrayHasKey('timestamp', $result);
    }

    public function testMemoryUsageCalculation(): void
    {
        $result = $this->healthCheckService->runAllChecks();
        $memoryCheck = $result['checks']['memory'];
        
        $this->assertArrayHasKey('details', $memoryCheck);
        $details = $memoryCheck['details'];
        
        $this->assertArrayHasKey('current_usage', $details);
        $this->assertArrayHasKey('peak_usage', $details);
        $this->assertArrayHasKey('memory_limit', $details);
        $this->assertArrayHasKey('usage_percentage', $details);
        $this->assertArrayHasKey('current_usage_mb', $details);
        $this->assertArrayHasKey('peak_usage_mb', $details);
        $this->assertArrayHasKey('limit_mb', $details);
        
        $this->assertIsInt($details['current_usage']);
        $this->assertIsInt($details['peak_usage']);
        $this->assertIsFloat($details['usage_percentage']);
        $this->assertIsFloat($details['current_usage_mb']);
        $this->assertIsFloat($details['peak_usage_mb']);
        
        // Memory usage should be positive
        $this->assertGreaterThan(0, $details['current_usage']);
        $this->assertGreaterThan(0, $details['peak_usage']);
        $this->assertGreaterThanOrEqual(0, $details['usage_percentage']);
        $this->assertLessThanOrEqual(100, $details['usage_percentage']);
    }

    public function testFileSystemCheck(): void
    {
        $result = $this->healthCheckService->runAllChecks();
        $fileSystemCheck = $result['checks']['file_system'];
        
        $this->assertArrayHasKey('details', $fileSystemCheck);
        $details = $fileSystemCheck['details'];
        
        $this->assertArrayHasKey('directories', $details);
        $this->assertArrayHasKey('disk_space', $details);
        
        $this->assertIsArray($details['directories']);
        $this->assertIsArray($details['disk_space']);
        
        // Disk space details
        $diskSpace = $details['disk_space'];
        $this->assertArrayHasKey('message', $diskSpace);
        $this->assertArrayHasKey('free_bytes', $diskSpace);
        $this->assertArrayHasKey('total_bytes', $diskSpace);
        $this->assertArrayHasKey('used_percentage', $diskSpace);
        
        $this->assertIsString($diskSpace['message']);
        $this->assertGreaterThan(0, $diskSpace['free_bytes']);
        $this->assertGreaterThan(0, $diskSpace['total_bytes']);
        $this->assertGreaterThanOrEqual(0, $diskSpace['used_percentage']);
        $this->assertLessThanOrEqual(100, $diskSpace['used_percentage']);
    }

    public function testConfigurationCheck(): void
    {
        $result = $this->healthCheckService->runAllChecks();
        $configCheck = $result['checks']['configuration'];
        
        $this->assertArrayHasKey('details', $configCheck);
        $details = $configCheck['details'];
        
        $this->assertArrayHasKey('environment', $details);
        $this->assertArrayHasKey('issues', $details);
        $this->assertArrayHasKey('warnings', $details);
        $this->assertArrayHasKey('php_version', $details);
        
        $this->assertIsString($details['environment']);
        $this->assertIsArray($details['issues']);
        $this->assertIsArray($details['warnings']);
        $this->assertIsString($details['php_version']);
        
        // PHP version should be valid
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $details['php_version']);
    }

    public function testWorkerStatusCheck(): void
    {
        $result = $this->healthCheckService->runAllChecks();
        $workerCheck = $result['checks']['workers'];
        
        $this->assertArrayHasKey('details', $workerCheck);
        $details = $workerCheck['details'];
        
        $this->assertArrayHasKey('issues', $details);
        $this->assertArrayHasKey('pending_transactions', $details);
        $this->assertArrayHasKey('stuck_transactions', $details);
        $this->assertArrayHasKey('worker_classes', $details);
        
        $this->assertIsArray($details['issues']);
        $this->assertIsInt($details['pending_transactions']);
        $this->assertIsInt($details['stuck_transactions']);
        $this->assertIsArray($details['worker_classes']);
        
        // Worker classes should indicate status
        foreach ($details['worker_classes'] as $class => $status) {
            $this->assertContains($status, ['✅', '❌']);
        }
    }
}