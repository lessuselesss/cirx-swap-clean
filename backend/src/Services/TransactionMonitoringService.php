<?php

namespace App\Services;

use App\Models\Transaction;
use Psr\Log\LoggerInterface;
use Carbon\Carbon;

/**
 * Transaction Monitoring Service
 * 
 * Monitors transaction states and alerts on production issues
 * that comprehensive tests don't catch in live environments.
 */
class TransactionMonitoringService
{
    private LoggerInterface $logger;
    private array $alertThresholds;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->alertThresholds = [
            'stuck_payment_verified_minutes' => intval($_ENV['ALERT_STUCK_PAYMENT_MINUTES'] ?? 30),
            'failed_transfer_percentage' => floatval($_ENV['ALERT_FAILED_TRANSFER_PERCENT'] ?? 25.0),
            'wallet_config_failure_count' => intval($_ENV['ALERT_WALLET_CONFIG_FAILURES'] ?? 3),
        ];
    }
    
    /**
     * Check for transactions stuck in payment_verified state
     * This indicates CIRX transfers aren't processing
     */
    public function checkStuckTransactions(): array
    {
        $stuckThreshold = Carbon::now()->subMinutes($this->alertThresholds['stuck_payment_verified_minutes']);
        
        $stuckTransactions = Transaction::where('swap_status', Transaction::STATUS_PAYMENT_VERIFIED)
            ->where('updated_at', '<', $stuckThreshold)
            ->get();
            
        if ($stuckTransactions->count() > 0) {
            $alert = [
                'severity' => 'critical',
                'type' => 'stuck_transactions',
                'count' => $stuckTransactions->count(),
                'threshold_minutes' => $this->alertThresholds['stuck_payment_verified_minutes'],
                'message' => "CRITICAL: {$stuckTransactions->count()} transactions stuck in payment_verified state for over {$this->alertThresholds['stuck_payment_verified_minutes']} minutes",
                'transaction_ids' => $stuckTransactions->pluck('id')->toArray(),
                'investigation_hints' => [
                    'Check CIRX wallet configuration (CIRX_WALLET_ADDRESS, CIRX_WALLET_PRIVATE_KEY)',
                    'Verify CIRX transfer worker is running',
                    'Check blockchain connectivity to Circular Protocol',
                    'Review recent CIRX transfer failure logs'
                ]
            ];
            
            $this->logger->critical('Stuck transactions detected', $alert);
            return [$alert];
        }
        
        return [];
    }
    
    /**
     * Monitor CIRX transfer failure rates
     */
    public function checkTransferFailureRates(): array
    {
        $recentTransactions = Transaction::where('created_at', '>=', Carbon::now()->subHours(1))
            ->whereIn('swap_status', [
                Transaction::STATUS_FAILED_CIRX_TRANSFER,
                Transaction::STATUS_COMPLETED,
                Transaction::STATUS_CIRX_TRANSFER_PENDING
            ])
            ->get();
            
        if ($recentTransactions->count() === 0) {
            return [];
        }
        
        $failedCount = $recentTransactions->where('swap_status', Transaction::STATUS_FAILED_CIRX_TRANSFER)->count();
        $failureRate = ($failedCount / $recentTransactions->count()) * 100;
        
        if ($failureRate >= $this->alertThresholds['failed_transfer_percentage']) {
            $alert = [
                'severity' => 'high',
                'type' => 'high_failure_rate',
                'failure_rate_percent' => round($failureRate, 2),
                'failed_count' => $failedCount,
                'total_count' => $recentTransactions->count(),
                'threshold_percent' => $this->alertThresholds['failed_transfer_percentage'],
                'message' => "HIGH: CIRX transfer failure rate is {$failureRate}% ({$failedCount}/{$recentTransactions->count()}) in the last hour",
                'investigation_hints' => [
                    'Check CIRX wallet balance',
                    'Verify Circular Protocol network connectivity',
                    'Review transfer error patterns in logs',
                    'Check for gas price or network congestion issues'
                ]
            ];
            
            $this->logger->warning('High CIRX transfer failure rate', $alert);
            return [$alert];
        }
        
        return [];
    }
    
    /**
     * Check for wallet configuration failures
     */
    public function checkWalletConfigurationFailures(): array
    {
        $recentFailures = Transaction::where('failure_reason', 'LIKE', '%CIRX wallet not configured%')
            ->where('created_at', '>=', Carbon::now()->subMinutes(15))
            ->get();
            
        if ($recentFailures->count() >= $this->alertThresholds['wallet_config_failure_count']) {
            $alert = [
                'severity' => 'critical',
                'type' => 'wallet_config_failure',
                'failure_count' => $recentFailures->count(),
                'threshold_count' => $this->alertThresholds['wallet_config_failure_count'],
                'message' => "CRITICAL: {$recentFailures->count()} CIRX wallet configuration failures in 15 minutes",
                'transaction_ids' => $recentFailures->pluck('id')->toArray(),
                'investigation_hints' => [
                    'URGENT: Check environment variables CIRX_WALLET_ADDRESS and CIRX_WALLET_PRIVATE_KEY',
                    'Verify wallet credentials are properly set in production environment',
                    'Check deployment configuration and secret management',
                    'This indicates NO CIRX transfers can complete'
                ]
            ];
            
            $this->logger->critical('CIRX wallet configuration failure', $alert);
            return [$alert];
        }
        
        return [];
    }
    
    /**
     * Generate comprehensive monitoring report
     */
    public function generateMonitoringReport(): array
    {
        $alerts = [];
        
        // Run all monitoring checks
        $alerts = array_merge($alerts, $this->checkStuckTransactions());
        $alerts = array_merge($alerts, $this->checkTransferFailureRates());
        $alerts = array_merge($alerts, $this->checkWalletConfigurationFailures());
        
        // Add summary statistics
        $summary = $this->generateSummaryStatistics();
        
        return [
            'timestamp' => Carbon::now()->toISOString(),
            'alerts' => $alerts,
            'summary' => $summary,
            'alert_count' => count($alerts),
            'highest_severity' => $this->getHighestSeverity($alerts)
        ];
    }
    
    /**
     * Generate summary statistics for monitoring dashboard
     */
    private function generateSummaryStatistics(): array
    {
        $last24Hours = Carbon::now()->subHours(24);
        
        $totalTransactions = Transaction::where('created_at', '>=', $last24Hours)->count();
        $completedTransactions = Transaction::where('created_at', '>=', $last24Hours)
            ->where('swap_status', Transaction::STATUS_COMPLETED)->count();
        $failedTransactions = Transaction::where('created_at', '>=', $last24Hours)
            ->whereIn('swap_status', [
                Transaction::STATUS_FAILED_PAYMENT_VERIFICATION,
                Transaction::STATUS_FAILED_CIRX_TRANSFER
            ])->count();
        $stuckTransactions = Transaction::where('swap_status', Transaction::STATUS_PAYMENT_VERIFIED)
            ->where('updated_at', '<', Carbon::now()->subMinutes(30))->count();
            
        $successRate = $totalTransactions > 0 ? ($completedTransactions / $totalTransactions) * 100 : 0;
        
        return [
            'last_24_hours' => [
                'total_transactions' => $totalTransactions,
                'completed_transactions' => $completedTransactions,
                'failed_transactions' => $failedTransactions,
                'success_rate_percent' => round($successRate, 2)
            ],
            'current_issues' => [
                'stuck_transactions' => $stuckTransactions,
                'wallet_configured' => !empty($_ENV['CIRX_WALLET_ADDRESS']) && !empty($_ENV['CIRX_WALLET_PRIVATE_KEY'])
            ]
        ];
    }
    
    /**
     * Get the highest severity level from alerts
     */
    private function getHighestSeverity(array $alerts): ?string
    {
        if (empty($alerts)) {
            return null;
        }
        
        $severityOrder = ['critical', 'high', 'medium', 'low'];
        
        foreach ($severityOrder as $severity) {
            foreach ($alerts as $alert) {
                if ($alert['severity'] === $severity) {
                    return $severity;
                }
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Check if the system is healthy
     */
    public function isSystemHealthy(): bool
    {
        $report = $this->generateMonitoringReport();
        
        // System is unhealthy if there are critical alerts
        foreach ($report['alerts'] as $alert) {
            if ($alert['severity'] === 'critical') {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get quick health status for API endpoints
     */
    public function getHealthStatus(): array
    {
        $isHealthy = $this->isSystemHealthy();
        $summary = $this->generateSummaryStatistics();
        
        return [
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => Carbon::now()->toISOString(),
            'wallets_configured' => $summary['current_issues']['wallet_configured'],
            'stuck_transactions' => $summary['current_issues']['stuck_transactions'],
            'last_24h_success_rate' => $summary['last_24_hours']['success_rate_percent']
        ];
    }
}