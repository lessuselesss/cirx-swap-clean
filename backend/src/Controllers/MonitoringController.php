<?php

namespace App\Controllers;

use App\Services\TransactionMonitoringService;
use App\Services\TransactionReadinessService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Carbon\Carbon;

/**
 * Monitoring Controller
 * 
 * Provides endpoints for production monitoring and alerting
 */
class MonitoringController
{
    private TransactionMonitoringService $monitoringService;
    private TransactionReadinessService $readinessService;
    private LoggerInterface $logger;
    
    public function __construct(
        TransactionMonitoringService $monitoringService,
        LoggerInterface $logger,
        ?TransactionReadinessService $readinessService = null
    ) {
        $this->monitoringService = $monitoringService;
        $this->logger = $logger;
        $this->readinessService = $readinessService ?? new TransactionReadinessService();
    }
    
    /**
     * Health check endpoint for load balancers
     * GET /api/v1/health
     */
    public function health(Request $request, Response $response): Response
    {
        try {
            $healthStatus = $this->monitoringService->getHealthStatus();
            
            $statusCode = $healthStatus['status'] === 'healthy' ? 200 : 503;
            
            $response->getBody()->write(json_encode($healthStatus));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($statusCode);
                
        } catch (\Exception $e) {
            $this->logger->error('Health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Health check failed',
                'timestamp' => Carbon::now()->toISOString()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
    
    /**
     * Detailed monitoring report for ops dashboard
     * GET /api/v1/monitoring/report
     */
    public function monitoringReport(Request $request, Response $response): Response
    {
        try {
            $report = $this->monitoringService->generateMonitoringReport();
            
            $response->getBody()->write(json_encode($report, JSON_PRETTY_PRINT));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
                
        } catch (\Exception $e) {
            $this->logger->error('Monitoring report failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $response->getBody()->write(json_encode([
                'error' => 'Failed to generate monitoring report',
                'timestamp' => Carbon::now()->toISOString()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
    
    /**
     * Check for stuck transactions specifically
     * GET /api/v1/monitoring/stuck-transactions
     */
    public function stuckTransactions(Request $request, Response $response): Response
    {
        try {
            $stuckAlerts = $this->monitoringService->checkStuckTransactions();
            
            $response->getBody()->write(json_encode([
                'timestamp' => Carbon::now()->toISOString(),
                'alerts' => $stuckAlerts,
                'count' => count($stuckAlerts)
            ], JSON_PRETTY_PRINT));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
                
        } catch (\Exception $e) {
            $this->logger->error('Stuck transactions check failed', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'error' => 'Failed to check stuck transactions',
                'timestamp' => Carbon::now()->toISOString()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
    
    /**
     * Check wallet configuration status
     * GET /api/v1/monitoring/wallet-config
     */
    public function walletConfig(Request $request, Response $response): Response
    {
        try {
            $configAlerts = $this->monitoringService->checkWalletConfigurationFailures();
            
            $walletConfigured = !empty($_ENV['CIRX_WALLET_ADDRESS']) && !empty($_ENV['CIRX_WALLET_PRIVATE_KEY']);
            
            $result = [
                'timestamp' => Carbon::now()->toISOString(),
                'wallet_configured' => $walletConfigured,
                'cirx_wallet_address_set' => !empty($_ENV['CIRX_WALLET_ADDRESS']),
                'cirx_private_key_set' => !empty($_ENV['CIRX_WALLET_PRIVATE_KEY']),
                'alerts' => $configAlerts
            ];
            
            $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT));
            
            // Return 503 if wallet isn't configured - this is a critical issue
            $statusCode = $walletConfigured ? 200 : 503;
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($statusCode);
                
        } catch (\Exception $e) {
            $this->logger->error('Wallet config check failed', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'error' => 'Failed to check wallet configuration',
                'timestamp' => Carbon::now()->toISOString()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
    
    /**
     * Prometheus metrics endpoint
     * GET /api/v1/monitoring/metrics
     */
    public function metrics(Request $request, Response $response): Response
    {
        try {
            $summary = $this->monitoringService->generateSummaryStatistics();
            $healthStatus = $this->monitoringService->getHealthStatus();
            
            // Generate Prometheus-compatible metrics
            $metrics = [
                "# HELP cirx_transactions_total Total number of transactions",
                "# TYPE cirx_transactions_total counter",
                "cirx_transactions_total{status=\"total\"} " . $summary['last_24_hours']['total_transactions'],
                "cirx_transactions_total{status=\"completed\"} " . $summary['last_24_hours']['completed_transactions'],
                "cirx_transactions_total{status=\"failed\"} " . $summary['last_24_hours']['failed_transactions'],
                "",
                "# HELP cirx_transaction_success_rate Transaction success rate percentage",
                "# TYPE cirx_transaction_success_rate gauge",
                "cirx_transaction_success_rate " . $summary['last_24_hours']['success_rate_percent'],
                "",
                "# HELP cirx_stuck_transactions Number of transactions stuck in payment_verified state",
                "# TYPE cirx_stuck_transactions gauge",
                "cirx_stuck_transactions " . $summary['current_issues']['stuck_transactions'],
                "",
                "# HELP cirx_wallet_configured Whether CIRX wallet is properly configured (1=yes, 0=no)",
                "# TYPE cirx_wallet_configured gauge",
                "cirx_wallet_configured " . ($summary['current_issues']['wallet_configured'] ? '1' : '0'),
                "",
                "# HELP cirx_system_healthy Whether the system is healthy (1=yes, 0=no)",
                "# TYPE cirx_system_healthy gauge",
                "cirx_system_healthy " . ($healthStatus['status'] === 'healthy' ? '1' : '0'),
                ""
            ];
            
            $response->getBody()->write(implode("\n", $metrics));
            return $response
                ->withHeader('Content-Type', 'text/plain')
                ->withStatus(200);
                
        } catch (\Exception $e) {
            $this->logger->error('Metrics generation failed', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write("# Error generating metrics\n");
            return $response
                ->withHeader('Content-Type', 'text/plain')
                ->withStatus(500);
        }
    }
    
    /**
     * Comprehensive transaction readiness check
     * GET /api/v1/health/transaction-ready
     * 
     * Validates ALL systems required for transaction processing.
     * If this returns transaction_ready=true, the backend can guarantee
     * it can handle transactions from payment verification to CIRX delivery.
     */
    public function transactionReady(Request $request, Response $response): Response
    {
        try {
            $readinessReport = $this->readinessService->assessTransactionReadiness();
            
            // Set appropriate HTTP status based on readiness
            $statusCode = $readinessReport['transaction_ready'] ? 200 : 503;
            
            $response->getBody()->write(json_encode($readinessReport, JSON_PRETTY_PRINT));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($statusCode);
                
        } catch (\Exception $e) {
            $this->logger->error('Transaction readiness check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorResponse = [
                'transaction_ready' => false,
                'status' => 'error',
                'message' => 'Transaction readiness check failed',
                'error' => $e->getMessage(),
                'timestamp' => Carbon::now()->toISOString()
            ];
            
            $response->getBody()->write(json_encode($errorResponse, JSON_PRETTY_PRINT));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
}