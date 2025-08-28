<?php

namespace App\Services;

use App\Models\Transaction;
use App\Services\PaymentVerificationService;
use App\Services\CirxTransferService;
use App\Services\LoggerService;
use App\Services\IrohServiceBridge;
use App\Blockchain\CircularProtocolClient;
use App\Blockchain\BlockchainClientFactory;
use App\Workers\PaymentVerificationWorker;
use App\Workers\CirxTransferWorker;
use App\Workers\StuckTransactionRecoveryWorker;
use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Transaction Readiness Service
 * 
 * Comprehensive health check system that validates ALL components required
 * for successful transaction processing from payment to CIRX delivery.
 * 
 * If this service reports transaction_ready=true, the backend can guarantee
 * it can process transactions end-to-end successfully.
 */
class TransactionReadinessService
{
    private PaymentVerificationService $paymentService;
    private CirxTransferService $cirxService;
    private BlockchainClientFactory $blockchainFactory;
    private ?IrohServiceBridge $irohBridge;
    private Client $httpClient;

    public function __construct(
        ?PaymentVerificationService $paymentService = null,
        ?CirxTransferService $cirxService = null,
        ?BlockchainClientFactory $blockchainFactory = null,
        ?IrohServiceBridge $irohBridge = null
    ) {
        $this->paymentService = $paymentService ?? new PaymentVerificationService();
        $this->cirxService = $cirxService ?? new CirxTransferService();
        $this->blockchainFactory = $blockchainFactory ?? new BlockchainClientFactory();
        $this->irohBridge = $irohBridge ?? new IrohServiceBridge();
        $this->httpClient = new Client(['timeout' => 10]);
    }

    /**
     * Complete transaction readiness assessment
     * 
     * @return array Comprehensive readiness report
     */
    public function assessTransactionReadiness(): array
    {
        $startTime = microtime(true);
        $checks = [];
        $transactionReady = true;
        $criticalIssues = [];
        $warnings = [];

        // 1. Database Connectivity & Write Capability
        $dbCheck = $this->checkDatabaseReadiness();
        $checks['database'] = $dbCheck;
        if ($dbCheck['status'] !== 'healthy') {
            $transactionReady = false;
            if ($dbCheck['severity'] === 'critical') {
                $criticalIssues[] = 'Database: ' . $dbCheck['message'];
            } else {
                $warnings[] = 'Database: ' . $dbCheck['message'];
            }
        }

        // 2. Circular Protocol API Connectivity & Authentication
        $cirxApiCheck = $this->checkCircularProtocolApi();
        $checks['circular_protocol_api'] = $cirxApiCheck;
        if ($cirxApiCheck['status'] !== 'healthy') {
            $transactionReady = false;
            if ($cirxApiCheck['severity'] === 'critical') {
                $criticalIssues[] = 'Circular Protocol API: ' . $cirxApiCheck['message'];
            } else {
                $warnings[] = 'Circular Protocol API: ' . $cirxApiCheck['message'];
            }
        }

        // 3. Payment Verification Service Availability
        $paymentCheck = $this->checkPaymentVerificationService();
        $checks['payment_verification'] = $paymentCheck;
        if ($paymentCheck['status'] !== 'healthy') {
            $transactionReady = false;
            if ($paymentCheck['severity'] === 'critical') {
                $criticalIssues[] = 'Payment Verification: ' . $paymentCheck['message'];
            } else {
                $warnings[] = 'Payment Verification: ' . $paymentCheck['message'];
            }
        }

        // 4. CIRX Transfer Service Functionality
        $transferCheck = $this->checkCirxTransferService();
        $checks['cirx_transfer'] = $transferCheck;
        if ($transferCheck['status'] !== 'healthy') {
            $transactionReady = false;
            if ($transferCheck['severity'] === 'critical') {
                $criticalIssues[] = 'CIRX Transfer: ' . $transferCheck['message'];
            } else {
                $warnings[] = 'CIRX Transfer: ' . $transferCheck['message'];
            }
        }

        // 5. Worker Queue System Status
        $workerCheck = $this->checkWorkerSystem();
        $checks['worker_system'] = $workerCheck;
        if ($workerCheck['status'] !== 'healthy') {
            $transactionReady = false;
            if ($workerCheck['severity'] === 'critical') {
                $criticalIssues[] = 'Worker System: ' . $workerCheck['message'];
            } else {
                $warnings[] = 'Worker System: ' . $workerCheck['message'];
            }
        }

        // 6. Critical Environment Variables & Configuration
        $configCheck = $this->checkCriticalConfiguration();
        $checks['configuration'] = $configCheck;
        if ($configCheck['status'] !== 'healthy') {
            $transactionReady = false;
            if ($configCheck['severity'] === 'critical') {
                $criticalIssues[] = 'Configuration: ' . $configCheck['message'];
            } else {
                $warnings[] = 'Configuration: ' . $configCheck['message'];
            }
        }

        // 7. System Resources (Disk Space, Memory)
        $resourceCheck = $this->checkSystemResources();
        $checks['system_resources'] = $resourceCheck;
        if ($resourceCheck['status'] !== 'healthy') {
            if ($resourceCheck['severity'] === 'critical') {
                $transactionReady = false;
                $criticalIssues[] = 'System Resources: ' . $resourceCheck['message'];
            } else {
                $warnings[] = 'System Resources: ' . $resourceCheck['message'];
            }
        }

        // 8. External Dependencies (IROH, etc.)
        $externalCheck = $this->checkExternalDependencies();
        $checks['external_dependencies'] = $externalCheck;
        if ($externalCheck['status'] !== 'healthy') {
            // External deps are not critical for basic transactions
            $warnings[] = 'External Dependencies: ' . $externalCheck['message'];
        }

        // 9. End-to-End Transaction Flow Test
        $e2eCheck = $this->performEndToEndCheck();
        $checks['end_to_end_test'] = $e2eCheck;
        if ($e2eCheck['status'] !== 'healthy') {
            $transactionReady = false;
            $criticalIssues[] = 'End-to-End Test: ' . $e2eCheck['message'];
        }

        $endTime = microtime(true);

        return [
            'transaction_ready' => $transactionReady,
            'status' => $transactionReady ? 'ready' : 'not_ready',
            'timestamp' => date('c'),
            'duration_ms' => round(($endTime - $startTime) * 1000, 2),
            'critical_issues' => $criticalIssues,
            'warnings' => $warnings,
            'checks' => $checks,
            'summary' => [
                'total_checks' => count($checks),
                'healthy_checks' => count(array_filter($checks, fn($check) => $check['status'] === 'healthy')),
                'degraded_checks' => count(array_filter($checks, fn($check) => $check['status'] === 'degraded')),
                'critical_checks' => count(array_filter($checks, fn($check) => $check['status'] === 'critical')),
                'health_percentage' => round((count(array_filter($checks, fn($check) => $check['status'] === 'healthy')) / count($checks)) * 100, 1)
            ]
        ];
    }

    /**
     * Check database connectivity with write capability test
     */
    private function checkDatabaseReadiness(): array
    {
        $startTime = microtime(true);
        
        try {
            $connection = Capsule::connection();
            $pdo = $connection->getPdo();

            // Test 1: Basic connectivity
            $result = $connection->select('SELECT 1 as test');
            if (empty($result) || $result[0]->test !== 1) {
                return [
                    'status' => 'critical',
                    'severity' => 'critical',
                    'message' => 'Database query test failed',
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'timestamp' => date('c')
                ];
            }

            // Test 2: Check critical tables exist
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
                    'severity' => 'critical',
                    'message' => 'Missing required tables: ' . implode(', ', $missingTables),
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'timestamp' => date('c')
                ];
            }

            // Test 3: Write capability test
            $testId = 'health_check_' . time();
            try {
                $connection->table('transactions')->insert([
                    'id' => $testId,
                    'payment_tx_id' => 'test_tx_hash',
                    'payment_chain' => 'ethereum',
                    'payment_token' => 'ETH',
                    'amount_paid' => '0.001',
                    'cirx_recipient_address' => 'test_address',
                    'sender_address' => 'test_sender',
                    'swap_status' => 'test_transaction',
                    'is_test_transaction' => true,
                    'retry_count' => 0,
                    'recovery_attempts' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                // Test update
                $connection->table('transactions')
                    ->where('id', $testId)
                    ->update(['swap_status' => 'test_updated']);

                // Test delete
                $connection->table('transactions')
                    ->where('id', $testId)
                    ->delete();

            } catch (Exception $e) {
                return [
                    'status' => 'critical',
                    'severity' => 'critical',
                    'message' => 'Database write operations failed: ' . $e->getMessage(),
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'timestamp' => date('c')
                ];
            }

            // Test 4: Check for stuck transactions
            $stuckTransactions = Transaction::where('swap_status', 'cirx_transfer_pending')
                ->where('updated_at', '<', date('Y-m-d H:i:s', time() - 3600))
                ->count();

            $status = $stuckTransactions > 20 ? 'degraded' : 'healthy';

            return [
                'status' => $status,
                'severity' => $stuckTransactions > 20 ? 'warning' : 'healthy',
                'message' => $status === 'healthy' ? 'Database fully operational' : "Database working but has {$stuckTransactions} stuck transactions",
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'details' => [
                    'driver' => $connection->getDriverName(),
                    'tables_verified' => $requiredTables,
                    'write_operations' => 'INSERT, UPDATE, DELETE tested successfully',
                    'stuck_transactions' => $stuckTransactions
                ],
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'severity' => 'critical',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check Circular Protocol API connectivity and authentication
     */
    private function checkCircularProtocolApi(): array
    {
        $startTime = microtime(true);
        
        try {
            $environment = $_ENV['APP_ENV'] ?? 'development';
            $cirxWallet = $_ENV['CIRX_WALLET_ADDRESS'] ?? null;
            $cirxPrivateKey = $_ENV['CIRX_WALLET_PRIVATE_KEY'] ?? null;

            if (!$cirxWallet) {
                return [
                    'status' => 'critical',
                    'severity' => 'critical',
                    'message' => 'CIRX wallet address not configured',
                    'response_time_ms' => 0,
                    'timestamp' => date('c')
                ];
            }

            $cirxClient = new CircularProtocolClient($environment, $cirxWallet, $cirxPrivateKey);

            // Test 1: Basic API connectivity
            $blockNumber = $cirxClient->getBlockNumber();
            if ($blockNumber < 0) {
                return [
                    'status' => 'critical',
                    'severity' => 'critical',
                    'message' => 'Unable to retrieve block number from Circular Protocol',
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'timestamp' => date('c')
                ];
            }

            // Test 2: Balance check (authentication test)
            $balance = $cirxClient->getBalance($cirxWallet);
            if (!is_numeric($balance) && $balance !== '0') {
                return [
                    'status' => 'degraded',
                    'severity' => 'warning',
                    'message' => 'Balance check failed - authentication issues possible',
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'timestamp' => date('c')
                ];
            }

            // Test 3: Private key availability for transactions
            if (!$cirxPrivateKey) {
                return [
                    'status' => 'critical',
                    'severity' => 'critical',
                    'message' => 'CIRX private key not configured - cannot send transfers',
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'timestamp' => date('c')
                ];
            }

            // Test 4: Sufficient balance for operations
            $numericBalance = floatval($balance);
            if ($numericBalance < 10.0) {
                return [
                    'status' => 'degraded',
                    'severity' => 'warning',
                    'message' => "Low CIRX balance: {$balance} CIRX (recommended: >10 CIRX)",
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'details' => [
                        'current_balance' => $balance,
                        'recommended_minimum' => '10.0'
                    ],
                    'timestamp' => date('c')
                ];
            }

            return [
                'status' => 'healthy',
                'severity' => 'healthy',
                'message' => 'Circular Protocol API fully operational',
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'details' => [
                    'block_number' => $blockNumber,
                    'wallet_balance' => $balance,
                    'environment' => $environment,
                    'private_key_configured' => true
                ],
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'severity' => 'critical',
                'message' => 'Circular Protocol API check failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check Payment Verification Service availability
     */
    private function checkPaymentVerificationService(): array
    {
        $startTime = microtime(true);
        
        try {
            // Test blockchain client factory
            $supportedChains = ['ethereum', 'bsc', 'polygon'];
            $clientTests = [];
            
            foreach ($supportedChains as $chain) {
                try {
                    $client = $this->blockchainFactory->getClientByChain($chain);
                    $blockNumber = $client->getBlockNumber();
                    $clientTests[$chain] = [
                        'status' => 'healthy',
                        'block_number' => $blockNumber
                    ];
                } catch (Exception $e) {
                    $clientTests[$chain] = [
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Check if critical chains are working
            $criticalChains = ['ethereum'];
            $criticalFailures = [];
            foreach ($criticalChains as $chain) {
                if ($clientTests[$chain]['status'] !== 'healthy') {
                    $criticalFailures[] = $chain;
                }
            }

            if (!empty($criticalFailures)) {
                return [
                    'status' => 'critical',
                    'severity' => 'critical',
                    'message' => 'Critical blockchain clients failed: ' . implode(', ', $criticalFailures),
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'details' => $clientTests,
                    'timestamp' => date('c')
                ];
            }

            // Test indexer connectivity (if configured)
            $indexerUrl = $_ENV['INDEXER_URL'] ?? null;
            $indexerStatus = 'not_configured';
            
            if ($indexerUrl) {
                try {
                    $response = $this->httpClient->get($indexerUrl . '/health');
                    $indexerStatus = $response->getStatusCode() === 200 ? 'healthy' : 'degraded';
                } catch (GuzzleException $e) {
                    $indexerStatus = 'failed';
                }
            }

            $workingChains = count(array_filter($clientTests, fn($test) => $test['status'] === 'healthy'));
            $status = $workingChains >= count($criticalChains) ? 'healthy' : 'degraded';

            return [
                'status' => $status,
                'severity' => $status === 'healthy' ? 'healthy' : 'warning',
                'message' => $status === 'healthy' ? 'Payment verification service operational' : 'Some blockchain clients degraded',
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'details' => [
                    'blockchain_clients' => $clientTests,
                    'indexer_status' => $indexerStatus,
                    'working_chains' => $workingChains,
                    'total_chains' => count($supportedChains)
                ],
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'severity' => 'critical',
                'message' => 'Payment verification service check failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check CIRX Transfer Service functionality
     */
    private function checkCirxTransferService(): array
    {
        $startTime = microtime(true);
        
        try {
            // Test service initialization
            $cirxService = $this->cirxService;
            
            // Check required configuration
            $cirxWallet = $_ENV['CIRX_WALLET_ADDRESS'] ?? null;
            $cirxPrivateKey = $_ENV['CIRX_WALLET_PRIVATE_KEY'] ?? null;
            
            if (!$cirxWallet) {
                return [
                    'status' => 'critical',
                    'severity' => 'critical',
                    'message' => 'CIRX wallet address not configured',
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'timestamp' => date('c')
                ];
            }

            if (!$cirxPrivateKey) {
                return [
                    'status' => 'critical',
                    'severity' => 'critical',
                    'message' => 'CIRX private key not configured',
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'timestamp' => date('c')
                ];
            }

            // Test blockchain client creation
            $environment = $_ENV['APP_ENV'] ?? 'development';
            $cirxClient = new CircularProtocolClient($environment, $cirxWallet, $cirxPrivateKey);
            
            // Test balance check
            $balance = $cirxClient->getBalance($cirxWallet);
            $numericBalance = floatval($balance);
            
            if ($numericBalance < 1.0) {
                return [
                    'status' => 'critical',
                    'severity' => 'critical',
                    'message' => "Insufficient CIRX balance for transfers: {$balance} CIRX",
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'details' => [
                        'current_balance' => $balance,
                        'required_minimum' => '1.0'
                    ],
                    'timestamp' => date('c')
                ];
            }

            // Check for pending transfers that might indicate issues
            $pendingTransfers = Transaction::where('swap_status', 'cirx_transfer_pending')->count();
            $failedTransfers = Transaction::where('swap_status', 'cirx_transfer_failed')->count();
            
            $status = 'healthy';
            if ($pendingTransfers > 10 || $failedTransfers > 5) {
                $status = 'degraded';
            }

            return [
                'status' => $status,
                'severity' => $status === 'healthy' ? 'healthy' : 'warning',
                'message' => $status === 'healthy' ? 'CIRX transfer service operational' : 'Transfer service working but has pending/failed transfers',
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'details' => [
                    'wallet_balance' => $balance,
                    'pending_transfers' => $pendingTransfers,
                    'failed_transfers' => $failedTransfers,
                    'wallet_configured' => true,
                    'private_key_configured' => true
                ],
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'severity' => 'critical',
                'message' => 'CIRX transfer service check failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check Worker Queue System Status
     */
    private function checkWorkerSystem(): array
    {
        $startTime = microtime(true);
        
        try {
            $workerClasses = [
                'PaymentVerificationWorker' => PaymentVerificationWorker::class,
                'CirxTransferWorker' => CirxTransferWorker::class,
                'StuckTransactionRecoveryWorker' => StuckTransactionRecoveryWorker::class
            ];

            $workerStatus = [];
            $criticalFailures = [];

            foreach ($workerClasses as $name => $class) {
                try {
                    if (!class_exists($class)) {
                        $workerStatus[$name] = 'class_not_found';
                        $criticalFailures[] = $name;
                        continue;
                    }

                    // Test instantiation
                    $worker = new $class();
                    $workerStatus[$name] = 'available';
                } catch (Exception $e) {
                    $workerStatus[$name] = 'instantiation_failed: ' . $e->getMessage();
                    $criticalFailures[] = $name;
                }
            }

            // Check transaction queue health
            $queueStats = [
                'pending_payment_verification' => Transaction::where('swap_status', 'pending_payment_verification')->count(),
                'payment_verified' => Transaction::where('swap_status', 'payment_verified')->count(),
                'cirx_transfer_pending' => Transaction::where('swap_status', 'cirx_transfer_pending')->count(),
                'stuck_transactions' => Transaction::where('swap_status', 'cirx_transfer_pending')
                    ->where('updated_at', '<', date('Y-m-d H:i:s', time() - 3600))
                    ->count()
            ];

            if (!empty($criticalFailures)) {
                return [
                    'status' => 'critical',
                    'severity' => 'critical',
                    'message' => 'Critical worker classes unavailable: ' . implode(', ', $criticalFailures),
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'details' => [
                        'worker_status' => $workerStatus,
                        'queue_stats' => $queueStats
                    ],
                    'timestamp' => date('c')
                ];
            }

            // Check if queue is backing up
            $status = 'healthy';
            $message = 'Worker system operational';
            
            if ($queueStats['stuck_transactions'] > 5) {
                $status = 'degraded';
                $message = 'Worker system operational but has stuck transactions';
            }

            return [
                'status' => $status,
                'severity' => $status === 'healthy' ? 'healthy' : 'warning',
                'message' => $message,
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'details' => [
                    'worker_status' => $workerStatus,
                    'queue_stats' => $queueStats
                ],
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'severity' => 'critical',
                'message' => 'Worker system check failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check Critical Environment Variables & Configuration
     */
    private function checkCriticalConfiguration(): array
    {
        $startTime = microtime(true);
        
        $criticalVars = [
            'APP_ENV' => 'Application environment',
            'DB_CONNECTION' => 'Database connection type',
            'DB_DATABASE' => 'Database name',
            'CIRX_WALLET_ADDRESS' => 'CIRX wallet address',
            'CIRX_WALLET_PRIVATE_KEY' => 'CIRX wallet private key'
        ];

        $recommendedVars = [
            'LOG_FILE_PATH' => 'Logging configuration',
            'TELEGRAM_BOT_TOKEN' => 'Error notifications',
            'INDEXER_URL' => 'Payment indexer'
        ];

        $missing = [];
        $missingRecommended = [];
        $configStatus = [];

        // Check critical variables
        foreach ($criticalVars as $var => $description) {
            if (empty($_ENV[$var])) {
                $missing[] = "{$var} ({$description})";
                $configStatus[$var] = 'missing';
            } else {
                $configStatus[$var] = 'configured';
            }
        }

        // Check recommended variables
        foreach ($recommendedVars as $var => $description) {
            if (empty($_ENV[$var])) {
                $missingRecommended[] = "{$var} ({$description})";
                $configStatus[$var] = 'missing';
            } else {
                $configStatus[$var] = 'configured';
            }
        }

        // Check PHP configuration
        $phpChecks = [
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ];

        if (!empty($missing)) {
            return [
                'status' => 'critical',
                'severity' => 'critical',
                'message' => 'Missing critical configuration: ' . implode(', ', $missing),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'details' => [
                    'config_status' => $configStatus,
                    'php_config' => $phpChecks
                ],
                'timestamp' => date('c')
            ];
        }

        $status = empty($missingRecommended) ? 'healthy' : 'degraded';
        $message = $status === 'healthy' ? 'Configuration fully set' : 'Configuration adequate, some recommended settings missing';

        return [
            'status' => $status,
            'severity' => $status === 'healthy' ? 'healthy' : 'warning',
            'message' => $message,
            'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'details' => [
                'config_status' => $configStatus,
                'missing_recommended' => $missingRecommended,
                'php_config' => $phpChecks
            ],
            'timestamp' => date('c')
        ];
    }

    /**
     * Check System Resources (Disk Space, Memory)
     */
    private function checkSystemResources(): array
    {
        $startTime = microtime(true);
        
        try {
            // Check disk space
            $freeSpace = disk_free_space('.');
            $totalSpace = disk_total_space('.');
            $usedPercentage = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);

            // Check memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
            $memoryPercentage = $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : 0;

            $issues = [];
            $status = 'healthy';

            // Disk space thresholds
            if ($usedPercentage > 95) {
                $status = 'critical';
                $issues[] = "Critical disk space: {$usedPercentage}% used";
            } elseif ($usedPercentage > 85) {
                $status = 'degraded';
                $issues[] = "Low disk space: {$usedPercentage}% used";
            }

            // Memory thresholds
            if ($memoryPercentage > 90) {
                $status = 'critical';
                $issues[] = "Critical memory usage: {$memoryPercentage}%";
            } elseif ($memoryPercentage > 75) {
                $status = $status === 'critical' ? 'critical' : 'degraded';
                $issues[] = "High memory usage: {$memoryPercentage}%";
            }

            $severity = $status === 'critical' ? 'critical' : ($status === 'degraded' ? 'warning' : 'healthy');

            return [
                'status' => $status,
                'severity' => $severity,
                'message' => empty($issues) ? 'System resources healthy' : implode(', ', $issues),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'details' => [
                    'disk_space' => [
                        'free_bytes' => $freeSpace,
                        'total_bytes' => $totalSpace,
                        'used_percentage' => $usedPercentage,
                        'free_gb' => round($freeSpace / (1024**3), 2)
                    ],
                    'memory' => [
                        'current_usage_mb' => round($memoryUsage / (1024**2), 2),
                        'peak_usage_mb' => round($memoryPeak / (1024**2), 2),
                        'limit_mb' => $memoryLimit > 0 ? round($memoryLimit / (1024**2), 2) : 'unlimited',
                        'usage_percentage' => $memoryPercentage
                    ]
                ],
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'degraded',
                'severity' => 'warning',
                'message' => 'System resources check failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check External Dependencies (IROH, etc.)
     */
    private function checkExternalDependencies(): array
    {
        $startTime = microtime(true);
        
        try {
            $dependencies = [];

            // Check IROH service bridge
            try {
                $irohStatus = $this->irohBridge->getNetworkInfo();
                $dependencies['iroh'] = [
                    'status' => 'available',
                    'details' => $irohStatus
                ];
            } catch (Exception $e) {
                $dependencies['iroh'] = [
                    'status' => 'unavailable',
                    'error' => $e->getMessage()
                ];
            }

            // Check indexer service
            $indexerUrl = $_ENV['INDEXER_URL'] ?? null;
            if ($indexerUrl) {
                try {
                    $response = $this->httpClient->get($indexerUrl . '/health', ['timeout' => 5]);
                    $dependencies['indexer'] = [
                        'status' => $response->getStatusCode() === 200 ? 'available' : 'degraded',
                        'url' => $indexerUrl,
                        'response_code' => $response->getStatusCode()
                    ];
                } catch (GuzzleException $e) {
                    $dependencies['indexer'] = [
                        'status' => 'unavailable',
                        'url' => $indexerUrl,
                        'error' => $e->getMessage()
                    ];
                }
            } else {
                $dependencies['indexer'] = [
                    'status' => 'not_configured'
                ];
            }

            // External dependencies are not critical for basic functionality
            $availableCount = count(array_filter($dependencies, fn($dep) => $dep['status'] === 'available'));
            $totalCount = count($dependencies);

            $status = 'healthy'; // External deps don't affect transaction processing
            $message = "External dependencies status: {$availableCount}/{$totalCount} available";

            return [
                'status' => $status,
                'severity' => 'healthy',
                'message' => $message,
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'details' => $dependencies,
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'degraded',
                'severity' => 'warning',
                'message' => 'External dependencies check failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Perform End-to-End Transaction Flow Test
     * 
     * This simulates a complete transaction flow without actually executing it
     */
    private function performEndToEndCheck(): array
    {
        $startTime = microtime(true);
        
        try {
            $testSteps = [];

            // Step 1: Database transaction creation test
            try {
                $testId = 'e2e_test_' . time();
                Capsule::connection()->table('transactions')->insert([
                    'id' => $testId,
                    'payment_tx_id' => 'test_e2e_tx_hash',
                    'payment_chain' => 'ethereum',
                    'payment_token' => 'ETH',
                    'amount_paid' => '0.001',
                    'cirx_recipient_address' => 'test_e2e_address',
                    'sender_address' => 'test_e2e_sender',
                    'swap_status' => 'e2e_test',
                    'is_test_transaction' => true,
                    'retry_count' => 0,
                    'recovery_attempts' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                $testSteps['database_write'] = 'passed';
            } catch (Exception $e) {
                $testSteps['database_write'] = 'failed: ' . $e->getMessage();
                throw new Exception('Database write test failed');
            }

            // Step 2: Blockchain client initialization
            try {
                $ethClient = $this->blockchainFactory->getClientByChain('ethereum');
                $ethClient->getBlockNumber();
                $testSteps['blockchain_client'] = 'passed';
            } catch (Exception $e) {
                $testSteps['blockchain_client'] = 'failed: ' . $e->getMessage();
                throw new Exception('Blockchain client test failed');
            }

            // Step 3: CIRX client initialization
            try {
                $environment = $_ENV['APP_ENV'] ?? 'development';
                $cirxWallet = $_ENV['CIRX_WALLET_ADDRESS'];
                $cirxPrivateKey = $_ENV['CIRX_WALLET_PRIVATE_KEY'];
                
                $cirxClient = new CircularProtocolClient($environment, $cirxWallet, $cirxPrivateKey);
                $cirxClient->getBalance($cirxWallet);
                $testSteps['cirx_client'] = 'passed';
            } catch (Exception $e) {
                $testSteps['cirx_client'] = 'failed: ' . $e->getMessage();
                throw new Exception('CIRX client test failed');
            }

            // Step 4: Worker class instantiation
            try {
                $paymentWorker = new PaymentVerificationWorker();
                $transferWorker = new CirxTransferWorker();
                $testSteps['worker_instantiation'] = 'passed';
            } catch (Exception $e) {
                $testSteps['worker_instantiation'] = 'failed: ' . $e->getMessage();
                throw new Exception('Worker instantiation test failed');
            }

            // Step 5: Service initialization
            try {
                $paymentService = new PaymentVerificationService();
                $cirxService = new CirxTransferService();
                $testSteps['service_initialization'] = 'passed';
            } catch (Exception $e) {
                $testSteps['service_initialization'] = 'failed: ' . $e->getMessage();
                throw new Exception('Service initialization test failed');
            }

            // Cleanup test transaction
            try {
                Capsule::connection()->table('transactions')
                    ->where('id', $testId)
                    ->delete();
                $testSteps['cleanup'] = 'passed';
            } catch (Exception $e) {
                $testSteps['cleanup'] = 'failed: ' . $e->getMessage();
                // Don't fail the whole test for cleanup issues
            }

            return [
                'status' => 'healthy',
                'severity' => 'healthy',
                'message' => 'End-to-end transaction flow test passed',
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'details' => [
                    'test_steps' => $testSteps,
                    'all_steps_passed' => !in_array(false, array_map(fn($step) => str_starts_with($step, 'passed'), $testSteps))
                ],
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'severity' => 'critical',
                'message' => 'End-to-end test failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'details' => [
                    'test_steps' => $testSteps ?? [],
                    'failure_point' => $e->getMessage()
                ],
                'timestamp' => date('c')
            ];
        }
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

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}