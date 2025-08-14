<?php

use Slim\Factory\AppFactory;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use App\Controllers\TransactionController;
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
} catch (Exception $e) {
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

    $group->get('/transactions/{swapId}/status', function (Request $request, Response $response, array $args) {
        $controller = new TransactionController();
        return $controller->getTransactionStatus($request, $response, $args);
    });

    // CIRX balance endpoint
    $group->get('/cirx/balance/{address}', function (Request $request, Response $response, array $args) {
        $controller = new TransactionController();
        return $controller->getCirxBalance($request, $response);
    });
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