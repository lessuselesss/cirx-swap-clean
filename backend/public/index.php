<?php

use Slim\Factory\AppFactory;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use App\Controllers\TransactionController;
use App\Controllers\TransactionStatusController;
use App\Controllers\TransactionTestController;
use App\Controllers\TelegramTestController;
use App\Controllers\DebugController;
use App\Controllers\ConfigController;
use App\Controllers\WorkerController;
use App\Controllers\AdminController;
use App\Controllers\IrohTransactionController;
use App\Controllers\MonitoringController;
use App\Middleware\ApiKeyAuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\LoggingMiddleware;
use App\Services\HealthCheckService;
use App\Services\LoggerService;
use App\Services\TransactionReadinessService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
try {
    $dotenv->load();
    error_log("✅ Environment loaded successfully");
} catch (Exception $e) {
    error_log("❌ Environment loading failed: " . $e->getMessage());
    // Environment file might not exist in some deployments
}

// Set up database connection
$capsule = new Capsule();
$capsule->addConnection([
    'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'database' => $_ENV['DB_DATABASE'] ?? 'cirx_otc',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// Create Slim app
$app = AppFactory::create();

// Set base path for production deployment (only if not running on localhost)  
$isProduction = !str_contains($_SERVER['HTTP_HOST'] ?? '', 'localhost');
if ($isProduction) {
    $app->setBasePath('/buy/api');
}

// Add JSON parsing middleware
$app->addBodyParsingMiddleware();

// Add middleware stack (order matters - last added runs first!)
$app->add(new LoggingMiddleware());        // Log requests/responses
$app->add(new CorsMiddleware());           // Handle CORS
$app->add(new RateLimitMiddleware());      // Rate limiting
$app->add(new ApiKeyAuthMiddleware());     // Authentication

// Add error middleware with logging
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(function (
    ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) {
    // Log error using LoggerService
    $requestId = $request->getAttribute('request_id', 'unknown');
    
    LoggerService::getLogger('error')->error('Unhandled API error', [
        'error_type' => 'unhandled_api_error',  // Add for Telegram notifications
        'request_id' => $requestId,
        'exception_class' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'method' => $request->getMethod(),
        'path' => $request->getUri()->getPath(),
        'stack_trace' => $displayErrorDetails ? $exception->getTraceAsString() : null
    ]);
    
    $response = new \Slim\Psr7\Response();
    $errorData = [
        'status' => 'error',
        'message' => $displayErrorDetails ? $exception->getMessage() : 'Internal server error',
        'request_id' => $requestId,
        'timestamp' => date('c')
    ];
    
    if ($displayErrorDetails) {
        $errorData['details'] = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'type' => get_class($exception)
        ];
    }
    
    $response->getBody()->write(json_encode($errorData));
    return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
});

// Root route
$app->get('/', function (Request $request, Response $response) {
    $data = [
        'service' => 'CIRX OTC Backend API',
        'version' => '1.0.0',
        'status' => 'running',
        'endpoints' => [
            'health' => '/v1/health',
            'transactions' => '/v1/transactions/*',
            'debug' => '/v1/debug/*'
        ],
        'timestamp' => date('c')
    ];
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Test route (direct, no group) - NO external dependencies
$app->get('/test', function (Request $request, Response $response) {
    $data = ['status' => 'working', 'message' => 'Direct route test successful'];
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

// Simple hello route - absolutely minimal
$app->get('/hello', function (Request $request, Response $response) {
    $response->getBody()->write('Hello World');
    return $response;
});

// Debug route registration
$app->get('/debug-routes', function (Request $request, Response $response) {
    $data = [
        'message' => 'Route registration is working',
        'timestamp' => date('c'),
        'server_info' => [
            'php_version' => phpversion(),
            'slim_version' => '4.x',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not_set',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'not_set',
            'path_info' => $_SERVER['PATH_INFO'] ?? 'not_set'
        ]
    ];
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Routes - API v1 endpoints
// Route group: /v1 in production (with /buy/api base = /buy/api/v1), /api/v1 in development  
$routeGroup = $isProduction ? '/v1' : '/api/v1';
$app->group($routeGroup, function ($group) {
    // Comprehensive health check with transaction readiness
    $group->get('/health', function (Request $request, Response $response) {
        try {
            $logger = \App\Services\LoggerService::getLogger('monitoring');
            $readinessService = new \App\Services\TransactionReadinessService();
            
            // Get comprehensive transaction readiness data
            $transactionData = $readinessService->assessTransactionReadiness();
            
            // Merge with basic health metadata
            $data = array_merge($transactionData, [
                'version' => '1.0.2', // Incremented to trigger deployment with updated ADMIN_TOKEN
                'deployment_timestamp' => '2025-09-05T00:15:00Z',
                'environment' => $_ENV['APP_ENV'] ?? 'development',
                'security' => [
                    'api_key_required' => (bool) ($_ENV['API_KEY_REQUIRED'] ?? true),
                    'rate_limiting' => (bool) ($_ENV['RATE_LIMIT_ENABLED'] ?? true),
                    'cors_enabled' => true
                ]
            ]);
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $data = [
                'transaction_ready' => false,
                'status' => 'error',
                'message' => 'Health check failed: ' . $e->getMessage(),
                'timestamp' => date('c'),
                'version' => '1.0.0',
                'environment' => $_ENV['APP_ENV'] ?? 'development'
            ];
            $response->getBody()->write(json_encode($data));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Ultra-fast ping endpoint for frontend connectivity (no checks, just connection test)
    $group->get('/ping', function (Request $request, Response $response) {
        $data = [
            'transaction_ready' => true, // Always true for basic connectivity
            'status' => 'ready',
            'timestamp' => date('c'),
            'ping' => true
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Quick health check for frontend connectivity (lightweight, fast response)
    $group->get('/health/quick', function (Request $request, Response $response) {
        try {
            // Just basic checks - no blockchain calls or heavy operations
            $isWalletConfigured = !empty($_ENV['CIRX_WALLET_ADDRESS']) && !empty($_ENV['CIRX_WALLET_PRIVATE_KEY']);
            
            $data = [
                'transaction_ready' => $isWalletConfigured, // Simple check based on wallet config
                'status' => $isWalletConfigured ? 'ready' : 'not_ready',
                'timestamp' => date('c'),
                'version' => '1.0.0',
                'environment' => $_ENV['APP_ENV'] ?? 'development',
                'quick_check' => true
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $data = [
                'transaction_ready' => false,
                'status' => 'error',
                'message' => 'Quick health check failed: ' . $e->getMessage(),
                'timestamp' => date('c'),
                'quick_check' => true
            ];
            $response->getBody()->write(json_encode($data));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });


    // Transaction readiness check - validates ALL systems for transaction processing
    $group->get('/health/transaction-ready', function (Request $request, Response $response) {
        $logger = \App\Services\LoggerService::getLogger('monitoring');
        $monitoringService = new \App\Services\TransactionMonitoringService($logger);
        $controller = new MonitoringController($monitoringService, $logger);
        return $controller->transactionReady($request, $response);
    });

    // Security status endpoint (protected)
    $group->get('/security/status', function (Request $request, Response $response) {
        $corsMiddleware = new \App\Middleware\CorsMiddleware();
        $rateLimitMiddleware = new \App\Middleware\RateLimitMiddleware();
        
        $clientId = 'ip:' . ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $currentTime = time();
        
        $data = [
            'api_key' => [
                'authenticated' => $request->getAttribute('api_key') ? true : false,
                'key' => $request->getAttribute('api_key')
            ],
            'rate_limit' => $rateLimitMiddleware->getRateLimitStatus($clientId, $currentTime),
            'cors' => $corsMiddleware->getConfiguration(),
            'client_info' => [
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $request->getHeaderLine('User-Agent'),
                'origin' => $request->getHeaderLine('Origin')
            ]
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Transaction routes
    $group->post('/transactions/initiate-swap', function (Request $request, Response $response) {
        $controller = new TransactionController();
        return $controller->initiateSwap($request, $response);
    });

    $group->get('/transactions/{id}/status', function (Request $request, Response $response, array $args) {
        $controller = new TransactionStatusController();
        return $controller->getStatus($request, $response, $args);
    });

    // Transaction table for status dashboard (with explorer links)
    $group->get('/transactions/table', function (Request $request, Response $response) {
        $controller = new TransactionStatusController();
        return $controller->getAllTransactionsTable($request, $response);
    });

    // CIRX balance endpoint
    $group->get('/cirx/balance/{address}', function (Request $request, Response $response, array $args) {
        $controller = new TransactionController();
        return $controller->getCirxBalance($request, $response, $args);
    });

    // IROH networking endpoints
    $group->get('/iroh/status', function (Request $request, Response $response) {
        $controller = new IrohTransactionController();
        return $controller->getNetworkStatus($request, $response);
    });

    $group->post('/iroh/discover', function (Request $request, Response $response) {
        $controller = new IrohTransactionController();
        return $controller->discoverPeers($request, $response);
    });

    $group->get('/transactions/{id}/status/realtime', function (Request $request, Response $response, array $args) {
        $controller = new IrohTransactionController();
        return $controller->getTransactionStatusWithUpdates($request, $response, $args);
    });

    // Configuration endpoint (frontend/backend synchronization)
    $group->get('/config/circular-network', function (Request $request, Response $response) {
        $controller = new ConfigController();
        return $controller->getCircularNetworkConfig($request, $response);
    });

    // Worker endpoints (for FTP deployments without cron/systemd)
    $group->post('/workers/process', function (Request $request, Response $response) {
        $controller = new WorkerController();
        return $controller->processTransactions($request, $response);
    });

    $group->get('/workers/stats', function (Request $request, Response $response) {
        $controller = new WorkerController();
        return $controller->getStats($request, $response);
    });

    $group->get('/workers/health', function (Request $request, Response $response) {
        $controller = new WorkerController();
        return $controller->healthCheck($request, $response);
    });

    // Manual retry endpoint (admin only)
    $group->post('/workers/retry', function (Request $request, Response $response) {
        $controller = new WorkerController();
        return $controller->manualRetry($request, $response);
    });

    // Auto-processing endpoint (for external cron services)
    $group->get('/workers/auto', function (Request $request, Response $response) {
        $controller = new WorkerController();
        return $controller->autoProcess($request, $response);
    });

    // Temporary path discovery endpoint (remove after database setup)
    // REQUIRES API KEY - not publicly accessible
    $group->get('/debug/paths', function (Request $request, Response $response) {
        // Check if API key is present and valid (middleware should handle this)
        $apiKey = $request->getAttribute('api_key');
        if (!$apiKey) {
            $data = [
                'error' => 'API key required for path discovery',
                'message' => 'This endpoint requires authentication for security reasons'
            ];
            $response->getBody()->write(json_encode($data));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
        
        try {
            // Prevent this script from running accidentally after setup
            $allow_discovery = true; // Set to false after getting paths
            
            if (!$allow_discovery) {
                $data = ['error' => 'Path discovery disabled for security'];
                $response->getBody()->write(json_encode($data));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
            
            $paths = [];
            
            // Current working directory
            $paths['current_directory'] = getcwd();
            
            // Script location (we're in public/index.php)
            $paths['script_location'] = __FILE__;
            $paths['script_directory'] = dirname(__FILE__);
            
            // Backend root directory (parent of public/)
            $backend_root = dirname(dirname(__FILE__));
            $paths['backend_root'] = $backend_root;
            $paths['public_directory'] = dirname(__FILE__);
            
            // Storage directory paths
            $storage_dir = $backend_root . '/storage';
            $paths['storage_directory'] = $storage_dir;
            $paths['storage_exists'] = is_dir($storage_dir);
            $paths['storage_writable'] = is_dir($storage_dir) ? is_writable($storage_dir) : false;
            
            // Proposed database file paths
            $paths['database_testnet_path'] = $storage_dir . '/database.testnet.sqlite';
            $paths['database_mainnet_path'] = $storage_dir . '/database.mainnet.sqlite';
            
            // Check if databases already exist
            $paths['testnet_db_exists'] = file_exists($paths['database_testnet_path']);
            $paths['mainnet_db_exists'] = file_exists($paths['database_mainnet_path']);
            
            // Current problematic database path (if exists)
            $current_db = $storage_dir . '/database.sqlite';
            $paths['current_db_path'] = $current_db;
            $paths['current_db_exists'] = file_exists($current_db);
            
            // Directory permissions
            if (is_dir($storage_dir)) {
                $paths['storage_permissions'] = substr(sprintf('%o', fileperms($storage_dir)), -4);
            } else {
                $paths['storage_permissions'] = 'directory_not_found';
            }
            
            // Web server user info
            $paths['php_user'] = get_current_user();
            if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
                $paths['process_user'] = posix_getpwuid(posix_geteuid())['name'] ?? 'unknown';
            } else {
                $paths['process_user'] = 'posix_functions_not_available';
            }
            
            // Environment info
            $paths['php_version'] = PHP_VERSION;
            $paths['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'unknown';
            $paths['document_root'] = $_SERVER['DOCUMENT_ROOT'] ?? 'unknown';
            $paths['server_name'] = $_SERVER['SERVER_NAME'] ?? 'unknown';
            
            // Environment detection
            $paths['app_env'] = $_ENV['APP_ENV'] ?? 'not_set';
            $paths['testnet_mode'] = $_ENV['TESTNET_MODE'] ?? 'not_set';
            
            // Database configuration discovery
            $paths['db_connection'] = $_ENV['DB_CONNECTION'] ?? 'not_set';
            $paths['db_database'] = $_ENV['DB_DATABASE'] ?? 'not_set';
            
            // Disk space info
            $paths['disk_free_bytes'] = disk_free_space($backend_root);
            $paths['disk_total_bytes'] = disk_total_space($backend_root);
            $paths['disk_free_gb'] = round($paths['disk_free_bytes'] / (1024*1024*1024), 2);
            
            // SQLite extension check
            $paths['sqlite_extension_loaded'] = extension_loaded('sqlite3');
            
            // Migration script path
            $migrate_script = $backend_root . '/migrate.php';
            $paths['migrate_script_path'] = $migrate_script;
            $paths['migrate_script_exists'] = file_exists($migrate_script);
            
            // Suggested absolute paths for .env configuration
            $paths['suggested_env_config'] = [
                'testnet_production' => [
                    'DB_CONNECTION' => 'sqlite',
                    'DB_DATABASE' => $paths['database_testnet_path'],
                    'APP_ENV' => 'production',
                    'TESTNET_MODE' => 'true'
                ],
                'mainnet_production' => [
                    'DB_CONNECTION' => 'sqlite', 
                    'DB_DATABASE' => $paths['database_mainnet_path'],
                    'APP_ENV' => 'production',
                    'TESTNET_MODE' => 'false'
                ]
            ];
            
            // Commands to run on server
            $paths['setup_commands'] = [
                'create_storage_dir' => "mkdir -p {$storage_dir}",
                'set_storage_permissions' => "chmod 755 {$storage_dir}",
                'create_testnet_db' => "cd {$backend_root} && php migrate.php",
                'rename_to_testnet' => "mv {$current_db} {$paths['database_testnet_path']}",
                'create_mainnet_db_later' => "cd {$backend_root} && php migrate.php && mv {$current_db} {$paths['database_mainnet_path']}",
                'set_db_permissions' => "chmod 664 {$storage_dir}/*.sqlite"
            ];
            
            // Health check analysis
            $paths['health_check_analysis'] = [
                'current_db_issue' => !file_exists($current_db) ? 'database.sqlite file missing' : 'database.sqlite exists',
                'storage_dir_issue' => !is_dir($storage_dir) ? 'storage directory missing' : 'storage directory exists',
                'permissions_issue' => is_dir($storage_dir) && !is_writable($storage_dir) ? 'storage directory not writable' : 'permissions ok',
                'sqlite_available' => extension_loaded('sqlite3') ? 'SQLite extension loaded' : 'SQLite extension missing'
            ];
            
            $response->getBody()->write(json_encode($paths, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $data = [
                'error' => 'Path discovery failed',
                'message' => $e->getMessage(),
                'basic_info' => [
                    'current_directory' => getcwd(),
                    'script_location' => __FILE__ ?? 'unknown',
                    'backend_root_attempt' => dirname(dirname(__FILE__))
                ]
            ];
            $response->getBody()->write(json_encode($data));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Secure database setup endpoint (requires admin token)  
    $group->post('/admin/setup-database', function (Request $request, Response $response) {
        // Check admin token authentication
        $body = $request->getParsedBody();
        $providedToken = $body['admin_token'] ?? '';
        $expectedToken = $_ENV['ADMIN_TOKEN'] ?? 'admin_dev_token_2024_cirx_secure_debug_new';
        
        if ($providedToken !== $expectedToken) {
            $data = [
                'success' => false,
                'error' => 'Admin token required',
                'message' => 'This endpoint requires valid admin authentication'
            ];
            $response->getBody()->write(json_encode($data));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        try {
            $setup = [];
            
            // Get backend root directory
            $backendRoot = dirname(dirname(__FILE__));
            $storageDir = $backendRoot . '/storage';
            
            $setup['backend_root'] = $backendRoot;
            $setup['storage_dir'] = $storageDir;
            $setup['steps'] = [];
            
            // Step 1: Create storage directory if needed
            $step1 = ['step' => 1, 'name' => 'Create storage directory'];
            if (!is_dir($storageDir)) {
                if (mkdir($storageDir, 0755, true)) {
                    $step1['status'] = 'success';
                    $step1['message'] = 'Created storage directory';
                } else {
                    $step1['status'] = 'error';
                    $step1['message'] = 'Failed to create storage directory';
                }
            } else {
                $step1['status'] = 'skipped';
                $step1['message'] = 'Storage directory already exists';
            }
            $setup['steps'][] = $step1;
            
            // Check if storage is writable
            if (!is_writable($storageDir)) {
                $setup['warnings'][] = 'Storage directory is not writable - permissions may need adjustment';
            }
            
            // Step 2: Check database situation
            $currentDb = $storageDir . '/database.sqlite';
            $testnetDb = $storageDir . '/database.testnet.sqlite';
            $mainnetDb = $storageDir . '/database.mainnet.sqlite';
            
            $step2 = ['step' => 2, 'name' => 'Analyze database situation'];
            $step2['status'] = 'info';
            $step2['databases'] = [
                'current' => file_exists($currentDb) ? 'exists' : 'missing',
                'testnet' => file_exists($testnetDb) ? 'exists' : 'missing', 
                'mainnet' => file_exists($mainnetDb) ? 'exists' : 'missing'
            ];
            $setup['steps'][] = $step2;
            
            // Step 3: Create testnet database if needed
            $step3 = ['step' => 3, 'name' => 'Create testnet database'];
            
            if (!file_exists($testnetDb)) {
                // Run migrations
                $output = [];
                $returnCode = 0;
                $migrateCommand = "cd {$backendRoot} && php migrate.php 2>&1";
                exec($migrateCommand, $output, $returnCode);
                
                if ($returnCode === 0) {
                    $step3['migration_success'] = true;
                    $step3['migration_output'] = implode("\n", $output);
                    
                    // Check if database.sqlite was created and rename it
                    if (file_exists($currentDb)) {
                        if (rename($currentDb, $testnetDb)) {
                            $step3['status'] = 'success';
                            $step3['message'] = 'Created and renamed to database.testnet.sqlite';
                        } else {
                            $step3['status'] = 'partial';
                            $step3['message'] = 'Database created but rename failed';
                        }
                    } else {
                        $step3['status'] = 'error';
                        $step3['message'] = 'Migration ran but no database.sqlite file created';
                    }
                } else {
                    $step3['status'] = 'error';
                    $step3['message'] = 'Migration failed';
                    $step3['migration_output'] = implode("\n", $output);
                }
            } else {
                $step3['status'] = 'skipped';
                $step3['message'] = 'Testnet database already exists';
            }
            $setup['steps'][] = $step3;
            
            // Step 4: Set permissions
            $step4 = ['step' => 4, 'name' => 'Set database permissions'];
            if (file_exists($testnetDb)) {
                if (chmod($testnetDb, 0664)) {
                    $step4['status'] = 'success';
                    $step4['message'] = 'Set permissions (664) on testnet database';
                } else {
                    $step4['status'] = 'warning';
                    $step4['message'] = 'Could not set permissions automatically';
                }
                
                // Get file info
                $fileOwner = function_exists('posix_getpwuid') && function_exists('fileowner') 
                    ? (posix_getpwuid(fileowner($testnetDb))['name'] ?? 'unknown')
                    : 'unknown';
                $step4['file_owner'] = $fileOwner;
                $step4['file_size'] = filesize($testnetDb);
            } else {
                $step4['status'] = 'skipped';
                $step4['message'] = 'No testnet database file found';
            }
            $setup['steps'][] = $step4;
            
            // Step 5: Provide configuration
            $step5 = ['step' => 5, 'name' => 'Environment configuration'];
            $step5['status'] = 'info';
            $step5['recommended_config'] = [
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => './storage/database.testnet.sqlite',
                'DB_DATABASE_ABSOLUTE' => $testnetDb,
                'APP_ENV' => 'production',
                'TESTNET_MODE' => 'true'
            ];
            $setup['steps'][] = $step5;
            
            // Step 6: Verification
            $step6 = ['step' => 6, 'name' => 'Verification'];
            if (file_exists($testnetDb) && is_readable($testnetDb)) {
                try {
                    $pdo = new PDO("sqlite:{$testnetDb}");
                    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
                    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
                    $step6['status'] = 'success';
                    $step6['message'] = 'Database connection successful';
                    $step6['tables_found'] = $tables;
                } catch (Exception $e) {
                    $step6['status'] = 'error';
                    $step6['message'] = 'Database connection failed: ' . $e->getMessage();
                }
            } else {
                $step6['status'] = 'error';
                $step6['message'] = 'Database file not accessible';
            }
            $setup['steps'][] = $step6;
            
            // Overall success determination
            $errorSteps = array_filter($setup['steps'], function($step) {
                return $step['status'] === 'error';
            });
            
            $setup['success'] = count($errorSteps) === 0;
            $setup['summary'] = [
                'testnet_database_ready' => file_exists($testnetDb) && is_readable($testnetDb),
                'next_steps' => [
                    'Update production .env file with the recommended configuration',
                    'Test health check endpoint to verify database connectivity',
                    'Remove this setup endpoint for security after completion'
                ]
            ];
            
            $response->getBody()->write(json_encode($setup, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $data = [
                'success' => false,
                'error' => 'Setup failed',
                'message' => $e->getMessage(),
                'timestamp' => date('c')
            ];
            $response->getBody()->write(json_encode($data));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Debug endpoints (always available)
    $group->post('/debug/nag-balance', function (Request $request, Response $response) {
        $controller = new DebugController();
        return $controller->testNagBalance($request, $response);
    });

    $group->get('/debug/nag-config', function (Request $request, Response $response) {
        $controller = new DebugController();
        return $controller->getNagConfig($request, $response);
    });

    $group->post('/debug/send-transaction', function (Request $request, Response $response) {
        $controller = new DebugController();
        return $controller->sendTransaction($request, $response);
    });

    $group->get('/debug/health', function (Request $request, Response $response) {
        $controller = new DebugController();
        return $controller->health($request, $response);
    });

    $group->get('/debug/env', function (Request $request, Response $response) {
        $controller = new DebugController();
        return $controller->debugEnv($request, $response);
    });


    // Proxy endpoints for Circular Labs APIs (to avoid CORS issues)
    $group->get('/proxy/circulating-supply', function (Request $request, Response $response) {
        try {
            $url = 'https://nag.circularlabs.io/GetCirculatingSupply.php?Asset=CIRX';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'CIRX-OTC-Backend/1.0');
            
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL error: " . $error);
            }
            
            if ($httpCode !== 200) {
                throw new Exception("HTTP error: " . $httpCode);
            }
            
            $response->getBody()->write($data);
            return $response
                ->withHeader('Content-Type', 'text/plain')
                ->withHeader('Cache-Control', 'max-age=30') // Cache for 30 seconds
                ->withStatus(200);
                
        } catch (Exception $e) {
            $errorData = [
                'success' => false,
                'error' => 'Failed to fetch circulating supply: ' . $e->getMessage(),
                'timestamp' => date('c')
            ];
            $response->getBody()->write(json_encode($errorData));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(502);
        }
    });
    
    $group->map(['GET', 'POST'], '/proxy/circular-protocol-validators', function (Request $request, Response $response) {
        try {
            // Get the target method from query parameters
            $cep = $request->getQueryParams()['cep'] ?? '';
            
            // Whitelist of allowed Circular Labs methods
            $allowedMethods = [
                'GetCirculatingSupply.php',
                'CProxy.php', 
                'Circular_CheckWallet_',
                'Circular_GetWalletBalance_'
            ];
            
            // Validate method
            if (!in_array($cep, $allowedMethods)) {
                throw new Exception('Invalid method');
            }
            
            // Build NAG URL with cep parameter - use environment-appropriate endpoint
            $testnetMode = ($_ENV['TESTNET_MODE'] ?? 'true') === 'true';
            $nagEndpoint = $testnetMode ? 'NAG.php' : 'NAG_Mainnet.php';
            $url = 'https://nag.circularlabs.io/' . $nagEndpoint . '?cep=' . urlencode($cep);
            
            // Add any additional query parameters except 'cep'
            $params = $request->getQueryParams();
            unset($params['cep']);
            if (!empty($params)) {
                $url .= '&' . http_build_query($params);
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'CIRX-OTC-Backend/1.0');
            
            // Handle POST data if this is a POST request
            if ($request->getMethod() === 'POST') {
                $postData = (string) $request->getBody();
                if (!empty($postData)) {
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($postData)
                    ]);
                }
            }
            
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL error: " . $error);
            }
            
            if ($httpCode !== 200) {
                throw new Exception("HTTP error: " . $httpCode);
            }
            
            // Determine appropriate content type
            $responseContentType = 'application/json';
            if (strpos($contentType, 'text/plain') !== false || strpos($cep, 'GetCirculatingSupply') !== false) {
                $responseContentType = 'text/plain';
            }
            
            $response->getBody()->write($data);
            return $response
                ->withHeader('Content-Type', $responseContentType)
                ->withHeader('Cache-Control', 'max-age=30')
                ->withStatus(200);
                
        } catch (Exception $e) {
            // Enhanced error logging for production debugging
            error_log("Circular proxy error: " . $e->getMessage() . " | CEP: " . ($cep ?? 'none') . " | Method: " . $request->getMethod());
            
            $errorData = [
                'success' => false,
                'error' => 'Proxy request failed: ' . $e->getMessage(),
                'debug' => [
                    'cep' => $cep ?? 'not_provided',
                    'method' => $request->getMethod(),
                    'whitelist_check' => in_array($cep ?? '', $allowedMethods),
                    'post_body_size' => strlen((string)$request->getBody()),
                    'php_version' => PHP_VERSION,
                    'testnet_mode' => $_ENV['TESTNET_MODE'] ?? 'NOT_SET'
                ],
                'timestamp' => date('c')
            ];
            $response->getBody()->write(json_encode($errorData));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(502);
        }
    });

    // Demo/Testing endpoints (only in development)
    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        $group->post('/test/transactions/demo', function (Request $request, Response $response) {
            $controller = new TransactionTestController();
            return $controller->createDemoTransaction($request, $response);
        });

        $group->post('/test/transactions/{id}/advance', function (Request $request, Response $response, array $args) {
            $controller = new TransactionTestController();
            return $controller->updateDemoTransaction($request, $response, $args);
        });
    }

    // Telegram notification testing endpoints (only in non-production)
    if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
        $group->get('/telegram/test/connection', function (Request $request, Response $response) {
            $controller = new TelegramTestController();
            return $controller->testConnection($request, $response);
        });

        $group->post('/telegram/test/error', function (Request $request, Response $response) {
            $controller = new TelegramTestController();
            return $controller->triggerTestError($request, $response);
        });

        $group->post('/telegram/test/multiple', function (Request $request, Response $response) {
            $controller = new TelegramTestController();
            return $controller->triggerMultipleErrors($request, $response);
        });

        $group->get('/telegram/status', function (Request $request, Response $response) {
            $controller = new TelegramTestController();
            return $controller->getStatus($request, $response);
        });
    }
});

// Admin routes (outside API group to avoid CORS issues)
$app->get('/admin', function (Request $request, Response $response) {
    return $response->withHeader('Location', '/admin/dashboard')->withStatus(302);
});

$app->get('/admin/login', function (Request $request, Response $response) {
    $controller = new AdminController();
    return $controller->login($request, $response);
});

$app->post('/admin/authenticate', function (Request $request, Response $response) {
    $controller = new AdminController();
    return $controller->authenticate($request, $response);
});

$app->get('/admin/dashboard', function (Request $request, Response $response) {
    $controller = new AdminController();
    return $controller->dashboard($request, $response);
});

// Admin API routes
$app->get('/admin/api/overview', function (Request $request, Response $response) {
    $controller = new AdminController();
    return $controller->getSystemOverview($request, $response);
});

$app->get('/admin/api/transactions', function (Request $request, Response $response) {
    $controller = new AdminController();
    return $controller->getTransactionManagement($request, $response);
});

// Handle preflight OPTIONS requests
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

// Enhanced catch-all route with full diagnostics
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) {
    $data = [
        'status' => 'error',
        'message' => 'Route not found',
        'method' => $request->getMethod(),
        'uri' => (string) $request->getUri(),
        'debug_info' => [
            'path' => $request->getUri()->getPath(),
            'query' => $request->getUri()->getQuery(),
            'headers' => $request->getHeaders(),
            'server_vars' => [
                'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'not_set',
                'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'not_set',
                'PATH_INFO' => $_SERVER['PATH_INFO'] ?? 'not_set',
                'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? 'not_set',
                'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'not_set'
            ],
            'route_patterns' => [
                'expected_test' => '/test',
                'expected_hello' => '/hello', 
                'expected_debug' => '/debug-routes',
                'expected_v1_health' => '/v1/health',
                'expected_root' => '/'
            ]
        ]
    ];
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
});

$app->run();