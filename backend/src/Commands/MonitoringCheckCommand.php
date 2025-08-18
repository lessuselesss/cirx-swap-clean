<?php

namespace App\Commands;

use App\Services\TransactionMonitoringService;
use Psr\Log\LoggerInterface;
use Carbon\Carbon;

/**
 * Monitoring Check Command
 * 
 * CLI command to run production monitoring checks
 * Usage: php monitoring-check.php [--json] [--alerts-only]
 */
class MonitoringCheckCommand
{
    private TransactionMonitoringService $monitoringService;
    private LoggerInterface $logger;
    
    public function __construct(
        TransactionMonitoringService $monitoringService,
        LoggerInterface $logger
    ) {
        $this->monitoringService = $monitoringService;
        $this->logger = $logger;
    }
    
    /**
     * Execute monitoring check
     */
    public function execute(array $args = []): int
    {
        $jsonOutput = in_array('--json', $args);
        $alertsOnly = in_array('--alerts-only', $args);
        
        try {
            $report = $this->monitoringService->generateMonitoringReport();
            
            if ($jsonOutput) {
                echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
                return $this->getExitCode($report);
            }
            
            if ($alertsOnly && empty($report['alerts'])) {
                echo "No alerts found - system healthy\n";
                return 0;
            }
            
            $this->displayReport($report);
            return $this->getExitCode($report);
            
        } catch (\Exception $e) {
            $this->logger->error('Monitoring check command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($jsonOutput) {
                echo json_encode([
                    'error' => 'Monitoring check failed',
                    'message' => $e->getMessage(),
                    'timestamp' => Carbon::now()->toISOString()
                ]) . "\n";
            } else {
                echo "ERROR: Monitoring check failed - {$e->getMessage()}\n";
            }
            
            return 1;
        }
    }
    
    /**
     * Display human-readable report
     */
    private function displayReport(array $report): void
    {
        echo "=== CIRX TRANSACTION MONITORING REPORT ===\n";
        echo "Timestamp: " . $report['timestamp'] . "\n";
        echo "Alerts: " . $report['alert_count'] . "\n";
        echo "Highest Severity: " . ($report['highest_severity'] ?? 'none') . "\n\n";
        
        // Display summary
        $summary = $report['summary'];
        echo "--- 24 HOUR SUMMARY ---\n";
        echo "Total Transactions: " . $summary['last_24_hours']['total_transactions'] . "\n";
        echo "Completed: " . $summary['last_24_hours']['completed_transactions'] . "\n";
        echo "Failed: " . $summary['last_24_hours']['failed_transactions'] . "\n";
        echo "Success Rate: " . $summary['last_24_hours']['success_rate_percent'] . "%\n\n";
        
        echo "--- CURRENT ISSUES ---\n";
        echo "Stuck Transactions: " . $summary['current_issues']['stuck_transactions'] . "\n";
        echo "Wallet Configured: " . ($summary['current_issues']['wallet_configured'] ? 'YES' : 'NO') . "\n\n";
        
        // Display alerts
        if (!empty($report['alerts'])) {
            echo "--- ALERTS ---\n";
            foreach ($report['alerts'] as $alert) {
                $this->displayAlert($alert);
            }
        } else {
            echo "--- NO ALERTS ---\n";
            echo "System appears healthy\n\n";
        }
    }
    
    /**
     * Display a single alert
     */
    private function displayAlert(array $alert): void
    {
        $severity = strtoupper($alert['severity']);
        echo "[{$severity}] {$alert['type']}\n";
        echo "Message: {$alert['message']}\n";
        
        if (isset($alert['investigation_hints'])) {
            echo "Investigation:\n";
            foreach ($alert['investigation_hints'] as $hint) {
                echo "  - {$hint}\n";
            }
        }
        
        if (isset($alert['transaction_ids'])) {
            echo "Affected Transactions: " . implode(', ', $alert['transaction_ids']) . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Get exit code based on alert severity
     */
    private function getExitCode(array $report): int
    {
        if (empty($report['alerts'])) {
            return 0; // Success - no alerts
        }
        
        $highestSeverity = $report['highest_severity'];
        
        return match($highestSeverity) {
            'critical' => 2,  // Critical issues
            'high' => 1,      // Warning issues
            'medium', 'low' => 0,  // Info only
            default => 0
        };
    }
    
    /**
     * Display usage information
     */
    public static function usage(): void
    {
        echo "Usage: php monitoring-check.php [options]\n\n";
        echo "Options:\n";
        echo "  --json        Output in JSON format\n";
        echo "  --alerts-only Show only alerts (skip summary)\n";
        echo "  --help        Show this help message\n\n";
        echo "Exit Codes:\n";
        echo "  0 = No issues or low severity\n";
        echo "  1 = High severity alerts\n";
        echo "  2 = Critical severity alerts\n\n";
        echo "Examples:\n";
        echo "  php monitoring-check.php\n";
        echo "  php monitoring-check.php --json\n";
        echo "  php monitoring-check.php --alerts-only\n";
    }
}