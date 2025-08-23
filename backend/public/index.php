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
use App\Middleware\ApiKeyAuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\LoggingMiddleware;
use App\Services\HealthCheckService;
use App\Services\LoggerService;
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
            'health' => '/api/v1/health',
            'transactions' => '/api/v1/transactions/*',
            'debug' => '/api/v1/debug/*'
        ],
        'timestamp' => date('c')
    ];
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Routes
$app->group('/api/v1', function ($group) {
    // Quick health check
    $group->get('/health', function (Request $request, Response $response) {
        $healthService = new HealthCheckService();
        $quickStatus = $healthService->getQuickStatus();
        
        $data = array_merge($quickStatus, [
            'version' => '1.0.0',
            'environment' => $_ENV['APP_ENV'] ?? 'development',
            'security' => [
                'api_key_required' => (bool) ($_ENV['API_KEY_REQUIRED'] ?? true),
                'rate_limiting' => (bool) ($_ENV['RATE_LIMIT_ENABLED'] ?? true),
                'cors_enabled' => true
            ]
        ]);
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Comprehensive health check
    $group->get('/health/detailed', function (Request $request, Response $response) {
        $healthService = new HealthCheckService();
        $healthStatus = $healthService->runAllChecks();
        
        $response->getBody()->write(json_encode($healthStatus));
        return $response->withHeader('Content-Type', 'application/json');
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