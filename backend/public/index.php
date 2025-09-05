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

// Test route (direct, no group)
$app->get('/test', function (Request $request, Response $response) {
    $data = ['status' => 'working', 'message' => 'Direct route test successful'];
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

// Routes
$app->group('/v1', function ($group) {
    // Comprehensive health check with transaction readiness
    $group->get('/health', function (Request $request, Response $response) {
        try {
            $logger = \App\Services\LoggerService::getLogger('monitoring');
            $readinessService = new \App\Services\TransactionReadinessService();
            
            // Get comprehensive transaction readiness data
            $transactionData = $readinessService->assessTransactionReadiness();
            
            // Merge with basic health metadata
            $data = array_merge($transactionData, [
                'version' => '1.0.1', // Incremented to verify deployment
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

    // Comprehensive health check
    $group->get('/health/detailed', function (Request $request, Response $response) {
        $healthService = new HealthCheckService();
        $healthStatus = $healthService->runAllChecks();
        
        $response->getBody()->write(json_encode($healthStatus));
        return $response->withHeader('Content-Type', 'application/json');
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

    $group->post('/debug/set-cirx-recipient', function (Request $request, Response $response) {
        $controller = new DebugController();
        return $controller->setCirxRecipientOverride($request, $response);
    });

    $group->get('/debug/cirx-recipient-override', function (Request $request, Response $response) {
        $controller = new DebugController();
        return $controller->getCirxRecipientOverride($request, $response);
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
    
    $group->map(['GET', 'POST'], '/proxy/circular-labs', function (Request $request, Response $response) {
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
            
            // Build NAG URL with cep parameter
            $url = 'https://nag.circularlabs.io/NAG.php?cep=' . urlencode($cep);
            
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

    // Production debug endpoints for troubleshooting HTTP 500 errors
    $group->get('/debug/circular-config', function (Request $request, Response $response) {
        try {
            $controller = new \App\Controllers\ConfigController();
            $method = new ReflectionMethod($controller, 'getCircularConfiguration');
            $method->setAccessible(true);
            $config = $method->invoke($controller);
            
            $data = [
                'testnet_mode_env' => $_ENV['TESTNET_MODE'] ?? 'NOT_SET',
                'app_env' => $_ENV['APP_ENV'] ?? 'NOT_SET',
                'config_result' => $config,
                'whitelist' => [
                    'GetCirculatingSupply.php',
                    'CProxy.php', 
                    'Circular_CheckWallet_',
                    'Circular_GetWalletBalance_'
                ],
                'php_version' => PHP_VERSION,
                'timestamp' => date('c')
            ];
            
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    $group->get('/debug/nag-direct', function (Request $request, Response $response) {
        try {
            $url = 'https://nag.circularlabs.io/NAG.php?cep=Circular_CheckWallet_';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'CIRX-OTC-Backend/1.0');
            
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            
            $result = [
                'success' => $httpCode === 200 && !$error,
                'http_code' => $httpCode,
                'curl_error' => $error ?: 'none',
                'response' => $data,
                'url' => $url,
                'curl_info' => [
                    'total_time' => $info['total_time'],
                    'namelookup_time' => $info['namelookup_time'],
                    'connect_time' => $info['connect_time']
                ]
            ];
        } catch (Exception $e) {
            $result = ['error' => $e->getMessage()];
        }
        
        $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
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

// Catch-all route (404)
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) {
    $data = [
        'status' => 'error',
        'message' => 'Route not found',
        'method' => $request->getMethod(),
        'uri' => (string) $request->getUri()
    ];
    $response->getBody()->write(json_encode($data));
    return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
});

$app->run();