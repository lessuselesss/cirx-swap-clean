<?php

namespace App\Controllers;

use App\Workers\PaymentVerificationWorker;
use App\Workers\CirxTransferWorker;
use App\Workers\StuckTransactionRecoveryWorker;
use App\Services\LoggerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Worker Controller
 * 
 * Provides HTTP endpoints to trigger background workers for FTP deployments
 * where traditional cron/systemd services aren't available.
 */
class WorkerController
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggerService::getLogger('worker_controller');
    }

    /**
     * Process pending transactions (payment verification + CIRX transfers)
     */
    public function processTransactions(Request $request, Response $response): Response
    {
        try {
            $this->logger->info('Web worker triggered');
            
            $paymentWorker = new PaymentVerificationWorker();
            $cirxWorker = new CirxTransferWorker();
            
            $results = [
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'payment_verification' => [],
                'cirx_transfers' => []
            ];

            // Process payment verification
            $paymentResults = $paymentWorker->processPendingTransactions();
            $results['payment_verification'] = $paymentResults;
            
            // Process retry transactions 
            $retryResults = $paymentWorker->processRetryTransactions();
            $results['payment_verification']['retries'] = $retryResults;

            // Process CIRX transfers
            $cirxResults = $cirxWorker->processReadyTransactions();
            $results['cirx_transfers'] = $cirxResults;
            
            // Process stuck transactions
            $stuckResults = $cirxWorker->processStuckTransactions();
            $results['cirx_transfers']['stuck'] = $stuckResults;

            // Run stuck transaction recovery periodically (every 10th call to reduce overhead)
            if (rand(1, 10) === 1) {
                $recoveryWorker = new StuckTransactionRecoveryWorker();
                $recoveryResults = $recoveryWorker->processStuckTransactions();
                $results['stuck_transaction_recovery'] = $recoveryResults;
                
                if ($recoveryResults['recovered'] > 0) {
                    $this->logger->info('Stuck transaction recovery completed', [
                        'scanned' => $recoveryResults['scanned'],
                        'recovered' => $recoveryResults['recovered'],
                        'permanently_failed' => $recoveryResults['permanently_failed']
                    ]);
                }
            }

            $this->logger->info('Web worker completed', [
                'payment_processed' => $paymentResults['processed'] ?? 0,
                'cirx_processed' => $cirxResults['processed'] ?? 0
            ]);

            return $this->jsonResponse($response, $results);

        } catch (\Exception $e) {
            $this->logger->error('Web worker failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Worker execution failed',
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ], 500);
        }
    }

    /**
     * Get worker statistics
     */
    public function getStats(Request $request, Response $response): Response
    {
        try {
            $paymentWorker = new PaymentVerificationWorker();
            $cirxWorker = new CirxTransferWorker();
            
            $stats = [
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'payment_verification' => $paymentWorker->getStatistics(),
                'cirx_transfers' => $cirxWorker->getStatistics()
            ];

            return $this->jsonResponse($response, $stats);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Failed to get statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Health check for workers
     */
    public function healthCheck(Request $request, Response $response): Response
    {
        try {
            $stats = [
                'worker_status' => 'available',
                'timestamp' => date('Y-m-d H:i:s'),
                'last_execution' => $this->getLastExecutionTime(),
                'recommendation' => $this->getProcessingRecommendation()
            ];

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Health check failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-trigger endpoint (for external cron services like cron-job.org)
     */
    public function autoProcess(Request $request, Response $response): Response
    {
        // Simple authentication check
        $queryParams = $request->getQueryParams();
        $apiKey = $queryParams['key'] ?? '';
        $expectedKey = $_ENV['WORKER_API_KEY'] ?? 'default-key';
        
        if ($apiKey !== $expectedKey) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Unauthorized'
            ], 401);
        }

        // Process transactions automatically
        return $this->processTransactions($request, $response);
    }

    /**
     * Get last execution time from log files
     */
    private function getLastExecutionTime(): ?string
    {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            return null;
        }

        $logFiles = glob($logDir . '/worker*.log');
        if (empty($logFiles)) {
            return null;
        }

        $latestFile = array_reduce($logFiles, function($latest, $file) {
            return (!$latest || filemtime($file) > filemtime($latest)) ? $file : $latest;
        });

        return $latestFile ? date('Y-m-d H:i:s', filemtime($latestFile)) : null;
    }

    /**
     * Get processing recommendation based on current state
     */
    private function getProcessingRecommendation(): string
    {
        try {
            $paymentWorker = new PaymentVerificationWorker();
            $cirxWorker = new CirxTransferWorker();
            
            $paymentStats = $paymentWorker->getStatistics();
            $cirxStats = $cirxWorker->getStatistics();
            
            $pendingPayments = $paymentStats['pending_verification'] ?? 0;
            $readyTransfers = $cirxStats['ready_for_transfer'] ?? 0;
            
            if ($pendingPayments > 0 || $readyTransfers > 0) {
                return "Processing recommended: {$pendingPayments} payments pending, {$readyTransfers} transfers ready";
            }
            
            return "No immediate processing needed";
            
        } catch (\Exception $e) {
            return "Unable to determine recommendation: " . $e->getMessage();
        }
    }

    /**
     * Helper method to format JSON response
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}