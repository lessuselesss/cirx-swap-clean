<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;

/**
 * Health Check Service for CIRX OTC Backend
 * 
 * Monitors system health including database, logging, workers, and external services
 */
class HealthCheckService
{
    private array $checks = [];
    private bool $enabledChecks;

    public function __construct()
    {
        $this->enabledChecks = filter_var($_ENV['HEALTH_CHECK_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Run all health checks and return comprehensive status
     */
    public function runAllChecks(): array
    {
        if (!$this->enabledChecks) {
            return [
                'status' => 'disabled',
                'message' => 'Health checks are disabled',
                'timestamp' => date('c')
            ];
        }

        $startTime = microtime(true);
        $overallStatus = 'healthy';
        $checks = [];

        // Run individual checks
        $checkMethods = [
            'database' => 'checkDatabase',
            'logging' => 'checkLogging',
            'file_system' => 'checkFileSystem',
            'memory' => 'checkMemoryUsage',
            'configuration' => 'checkConfiguration',
            'workers' => 'checkWorkerStatus',
            'external_services' => 'checkExternalServices'
        ];

        foreach ($checkMethods as $checkName => $method) {
            try {
                $checkResult = $this->$method();
                $checks[$checkName] = $checkResult;

                if ($checkResult['status'] !== 'healthy') {
                    $overallStatus = $checkResult['status'] === 'critical' ? 'critical' : 'degraded';
                }
            } catch (Exception $e) {
                $checks[$checkName] = [
                    'status' => 'critical',
                    'message' => $e->getMessage(),
                    'timestamp' => date('c')
                ];
                $overallStatus = 'critical';
            }
        }

        $endTime = microtime(true);

        return [
            'status' => $overallStatus,
            'timestamp' => date('c'),
            'duration_ms' => round(($endTime - $startTime) * 1000, 2),
            'checks' => $checks,
            'summary' => $this->generateSummary($checks)
        ];
    }

    /**
     * Check database connectivity and basic operations
     */
    private function checkDatabase(): array
    {
        try {
            // Test database connection
            $connection = Capsule::connection();
            $connection->getPdo();

            // Test a simple query
            $result = $connection->select('SELECT 1 as test');
            if (empty($result) || $result[0]->test !== 1) {
                throw new Exception('Database query test failed');
            }

            // Check if critical tables exist
            $schema = $connection->getSchemaBuilder();
            $requiredTables = ['transactions', 'project_wallets'];
            $missingTables = [];

            foreach ($requiredTables as $table) {
                if (!$schema->hasTable($table)) {
                    $missingTables[] = $table;
                }
            }

            if (!empty($missingTables)) {
                return [
                    'status' => 'critical',
                    'message' => 'Missing required tables: ' . implode(', ', $missingTables),
                    'timestamp' => date('c')
                ];
            }

            // Check recent transaction activity
            $recentTransactions = Transaction::where('created_at', '>=', date('Y-m-d H:i:s', time() - 3600))->count();

            return [
                'status' => 'healthy',
                'message' => 'Database is operational',
                'details' => [
                    'driver' => $connection->getDriverName(),
                    'tables_checked' => $requiredTables,
                    'recent_transactions' => $recentTransactions
                ],
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check logging system functionality
     */
    private function checkLogging(): array
    {
        try {
            $loggingStats = LoggerService::getLoggingStatistics();
            $testResults = LoggerService::testLogging();

            if (!$testResults['success']) {
                return [
                    'status' => 'degraded',
                    'message' => 'Logging tests failed',
                    'details' => [
                        'errors' => $testResults['errors'],
                        'statistics' => $loggingStats
                    ],
                    'timestamp' => date('c')
                ];
            }

            return [
                'status' => 'healthy',
                'message' => 'Logging system is operational',
                'details' => $loggingStats,
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Logging check failed: ' . $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check file system permissions and disk space
     */
    private function checkFileSystem(): array
    {
        $checks = [];
        $status = 'healthy';

        // Check critical directories
        $directories = [
            'storage/logs' => 'Log directory',
            'storage' => 'Storage directory',
            'database/migrations' => 'Migrations directory'
        ];

        foreach ($directories as $dir => $description) {
            if (!is_dir($dir)) {
                $checks[$dir] = "❌ {$description} does not exist";
                $status = 'critical';
            } elseif (!is_writable($dir)) {
                $checks[$dir] = "⚠️ {$description} is not writable";
                $status = 'degraded';
            } else {
                $checks[$dir] = "✅ {$description} is accessible";
            }
        }

        // Check disk space
        $freeSpace = disk_free_space('.');
        $totalSpace = disk_total_space('.');
        $usedPercentage = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);

        if ($usedPercentage > 95) {
            $status = 'critical';
            $spaceMessage = "❌ Disk space critical: {$usedPercentage}% used";
        } elseif ($usedPercentage > 85) {
            $status = 'degraded';
            $spaceMessage = "⚠️ Disk space warning: {$usedPercentage}% used";
        } else {
            $spaceMessage = "✅ Disk space healthy: {$usedPercentage}% used";
        }

        return [
            'status' => $status,
            'message' => 'File system check completed',
            'details' => [
                'directories' => $checks,
                'disk_space' => [
                    'message' => $spaceMessage,
                    'free_bytes' => $freeSpace,
                    'total_bytes' => $totalSpace,
                    'used_percentage' => $usedPercentage
                ]
            ],
            'timestamp' => date('c')
        ];
    }

    /**
     * Check memory usage
     */
    private function checkMemoryUsage(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));

        $usagePercentage = $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : 0;

        $status = 'healthy';
        if ($usagePercentage > 90) {
            $status = 'critical';
        } elseif ($usagePercentage > 75) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'message' => "Memory usage: {$usagePercentage}%",
            'details' => [
                'current_usage' => $memoryUsage,
                'peak_usage' => $memoryPeak,
                'memory_limit' => $memoryLimit,
                'usage_percentage' => $usagePercentage,
                'current_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                'peak_usage_mb' => round($memoryPeak / 1024 / 1024, 2),
                'limit_mb' => $memoryLimit > 0 ? round($memoryLimit / 1024 / 1024, 2) : 'unlimited'
            ],
            'timestamp' => date('c')
        ];
    }

    /**
     * Check configuration validity
     */
    private function checkConfiguration(): array
    {
        $issues = [];
        $warnings = [];

        // Check required environment variables
        $requiredEnvVars = [
            'APP_ENV',
            'DB_CONNECTION',
            'DB_DATABASE'
        ];

        foreach ($requiredEnvVars as $var) {
            if (empty($_ENV[$var])) {
                $issues[] = "Missing required environment variable: {$var}";
            }
        }

        // Check recommended settings
        if (empty($_ENV['API_KEYS']) && ($_ENV['API_KEY_REQUIRED'] ?? 'true') === 'true') {
            $warnings[] = "API key authentication enabled but no API keys configured";
        }

        if (empty($_ENV['LOG_FILE_PATH'])) {
            $warnings[] = "No log file path configured";
        }

        $status = 'healthy';
        if (!empty($issues)) {
            $status = 'critical';
        } elseif (!empty($warnings)) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'message' => 'Configuration check completed',
            'details' => [
                'environment' => $_ENV['APP_ENV'] ?? 'unknown',
                'issues' => $issues,
                'warnings' => $warnings,
                'php_version' => phpversion()
            ],
            'timestamp' => date('c')
        ];
    }

    /**
     * Check worker status (simulated - would integrate with actual worker monitoring)
     */
    private function checkWorkerStatus(): array
    {
        // This would integrate with actual worker monitoring in production
        // For now, we'll check if worker classes exist and are loadable
        
        $workerClasses = [
            'App\\Workers\\PaymentVerificationWorker',
            'App\\Workers\\CirxTransferWorker'
        ];

        $issues = [];
        foreach ($workerClasses as $class) {
            if (!class_exists($class)) {
                $issues[] = "Worker class not found: {$class}";
            }
        }

        // Check recent transaction processing activity
        $pendingTransactions = Transaction::where('swap_status', 'pending_payment_verification')->count();
        $stuckTransactions = Transaction::where('swap_status', 'cirx_transfer_pending')
            ->where('updated_at', '<', date('Y-m-d H:i:s', time() - 3600))
            ->count();

        $status = 'healthy';
        if (!empty($issues)) {
            $status = 'critical';
        } elseif ($stuckTransactions > 10) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'message' => 'Worker status check completed',
            'details' => [
                'issues' => $issues,
                'pending_transactions' => $pendingTransactions,
                'stuck_transactions' => $stuckTransactions,
                'worker_classes' => array_map(fn($class) => class_exists($class) ? '✅' : '❌', $workerClasses)
            ],
            'timestamp' => date('c')
        ];
    }

    /**
     * Check external service connectivity (placeholder)
     */
    private function checkExternalServices(): array
    {
        // Placeholder for external service checks
        // In production, this would check blockchain RPC endpoints, APIs, etc.
        
        return [
            'status' => 'healthy',
            'message' => 'External services check not implemented',
            'details' => [
                'note' => 'This would check blockchain RPC endpoints and external APIs'
            ],
            'timestamp' => date('c')
        ];
    }

    /**
     * Generate summary of all checks
     */
    private function generateSummary(array $checks): array
    {
        $statusCounts = ['healthy' => 0, 'degraded' => 0, 'critical' => 0];
        
        foreach ($checks as $check) {
            $status = $check['status'] ?? 'unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }

        return [
            'total_checks' => count($checks),
            'healthy' => $statusCounts['healthy'],
            'degraded' => $statusCounts['degraded'],
            'critical' => $statusCounts['critical'],
            'health_percentage' => count($checks) > 0 ? round(($statusCounts['healthy'] / count($checks)) * 100, 1) : 0
        ];
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '-1') {
            return 0; // Unlimited
        }

        $unit = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Get quick health status (lightweight check)
     */
    public function getQuickStatus(): array
    {
        if (!$this->enabledChecks) {
            return [
                'status' => 'disabled',
                'timestamp' => date('c')
            ];
        }

        try {
            // Quick database ping
            Capsule::connection()->getPdo();
            
            return [
                'status' => 'healthy',
                'timestamp' => date('c'),
                'uptime' => $this->getUptime()
            ];
        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Quick health check failed',
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Get system uptime (placeholder)
     */
    private function getUptime(): string
    {
        // This would return actual system uptime in production
        return 'unknown';
    }
}