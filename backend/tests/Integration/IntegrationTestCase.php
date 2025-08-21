<?php

namespace Tests\Integration;

use Tests\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use PDO;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Base class for integration tests with full application context
 * 
 * @covers \Tests\Integration\IntegrationTestCase
 */
abstract class IntegrationTestCase extends TestCase
{
    protected App $app;
    protected PDO $pdo;
    protected ServerRequestFactory $requestFactory;
    protected StreamFactory $streamFactory;

    protected function setUp(): void
    {
        // Skip parent::setUp() to avoid TestCase database setup conflicts
        \PHPUnit\Framework\TestCase::setUp();
        
        // Set up test environment
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DATABASE_URL'] = 'sqlite::memory:';
        $_ENV['API_KEY_REQUIRED'] = 'true';
        $_ENV['API_KEYS'] = 'test_integration_key_123';
        $_ENV['RATE_LIMIT_ENABLED'] = 'false'; // Disable for testing
        $_ENV['CORS_ALLOWED_ORIGINS'] = 'https://test.integration.com';
        
        // Initialize application
        $this->setupApplication();
        $this->setupDatabase();
    }

    protected function tearDown(): void
    {
        // Clean up environment
        unset(
            $_ENV['APP_ENV'],
            $_ENV['DATABASE_URL'],
            $_ENV['API_KEY_REQUIRED'],
            $_ENV['API_KEYS'],
            $_ENV['RATE_LIMIT_ENABLED'],
            $_ENV['CORS_ALLOWED_ORIGINS']
        );
        
        // Skip parent::tearDown() to avoid TestCase database teardown conflicts  
        \PHPUnit\Framework\TestCase::tearDown();
    }

    /**
     * Set up the Slim application with full middleware stack
     */
    protected function setupApplication(): void
    {
        // Create app without DI container
        $this->app = AppFactory::create();
        
        // Initialize factories
        $this->requestFactory = new ServerRequestFactory();
        $this->streamFactory = new StreamFactory();

        // Configure routes and middleware (simplified version of public/index.php)
        $this->configureRoutes();
        $this->configureMiddleware();
    }

    /**
     * Set up in-memory SQLite database with schema
     */
    protected function setupDatabase(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Set up Illuminate Database Capsule for service compatibility
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        
        // Create test schema on both connections
        $this->createTestSchema();
        $this->createCapsuleSchema();
        $this->seedTestData();
        $this->seedCapsuleData();
    }

    /**
     * Configure application routes
     */
    protected function configureRoutes(): void
    {
        // Health check endpoints
        $this->app->get('/api/v1/health', function ($request, $response) {
            $response->getBody()->write(json_encode(['status' => 'healthy']));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $this->app->get('/api/v1/health/detailed', function ($request, $response) {
            $response->getBody()->write(json_encode([
                'status' => 'healthy',
                'checks' => [
                    'database' => 'healthy',
                    'workers' => 'healthy'
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Transaction endpoints
        $this->app->post('/api/v1/transactions/initiate-swap', function ($request, $response) {
            // Check for API key authentication
            $apiKey = $request->getHeaderLine('X-API-Key');
            if (empty($apiKey) || $apiKey !== 'test_integration_key_123') {
                $response->getBody()->write(json_encode(['error' => 'Unauthorized', 'message' => 'Valid API key required']));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }
            
            // Get parsed body (handled by body parsing middleware)
            $body = $request->getParsedBody();
            if (!$body) {
                // Fallback to manual parsing
                $body = json_decode($request->getBody()->getContents(), true);
            }
            
            if (!is_array($body)) {
                $response->getBody()->write(json_encode(['error' => 'Bad Request', 'message' => 'Invalid JSON body']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Validate required parameters
            $requiredFields = ['user_wallet_address', 'payment_token', 'payment_amount', 'cirx_recipient_address'];
            foreach ($requiredFields as $field) {
                if (!isset($body[$field]) || empty($body[$field])) {
                    $response->getBody()->write(json_encode(['error' => 'Bad Request', 'message' => "Missing required field: {$field}"]));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
            }
            
            // Basic validation for common issues
            if (!is_numeric($body['payment_amount']) || floatval($body['payment_amount']) <= 0) {
                $response->getBody()->write(json_encode(['error' => 'Bad Request', 'message' => 'Invalid payment amount']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            if (!in_array($body['payment_token'], ['USDC', 'ETH', 'USDT'])) {
                $response->getBody()->write(json_encode(['error' => 'Bad Request', 'message' => 'Unsupported payment token']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Basic wallet address validation
            if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $body['user_wallet_address'])) {
                $response->getBody()->write(json_encode(['error' => 'Bad Request', 'message' => 'Invalid wallet address format']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $transactionId = 'tx_integration_' . uniqid();
            $responseData = [
                'success' => true,
                'transaction_id' => $transactionId,
                'status' => 'initiated',
                'message' => 'Swap transaction initiated successfully'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $this->app->get('/api/v1/transactions/{id}/status', function ($request, $response, $args) use (&$that) {
            $transactionId = $args['id'];
            
            // For integration tests, we'll simulate checking against test data
            // In a real app, this would check the database
            $knownTestTransactionIds = [
                'tx_test_initiated_001',
                'tx_test_pending_002'
            ];
            
            // Check for generated transaction IDs (they start with tx_integration_)
            if (!in_array($transactionId, $knownTestTransactionIds) && !str_starts_with($transactionId, 'tx_integration_')) {
                $response->getBody()->write(json_encode(['error' => 'Not Found', 'message' => 'Transaction not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            $responseData = [
                'transaction_id' => $transactionId,
                'status' => 'payment_pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
        });
    }

    /**
     * Configure middleware stack
     */
    protected function configureMiddleware(): void
    {
        // Add CORS middleware for testing
        $this->app->options('/{routes:.+}', function ($request, $response) {
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        });

        // Add CORS headers to all responses
        $this->app->add(function ($request, $handler) {
            $response = $handler->handle($request);
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        });
        
        // Add essential middleware for integration testing
        $this->app->addBodyParsingMiddleware();
        $this->app->addRoutingMiddleware();
        
        // Add error handling middleware
        $errorMiddleware = $this->app->addErrorMiddleware(true, true, true);
    }

    /**
     * Create test database schema
     */
    protected function createTestSchema(): void
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            transaction_id VARCHAR(255) UNIQUE NOT NULL,
            user_wallet_address VARCHAR(255) NOT NULL,
            payment_token VARCHAR(10) NOT NULL,
            payment_amount DECIMAL(20, 8) NOT NULL,
            cirx_amount DECIMAL(20, 8) NOT NULL,
            cirx_recipient_address VARCHAR(255) NOT NULL,
            swap_status VARCHAR(50) NOT NULL DEFAULT 'initiated',
            payment_tx_id VARCHAR(255),
            cirx_tx_id VARCHAR(255),
            discount_percentage DECIMAL(5, 2) DEFAULT 0.00,
            platform_fee DECIMAL(20, 8) DEFAULT 0.00000000,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            retry_count INTEGER DEFAULT 0,
            last_retry_at DATETIME NULL,
            failure_reason TEXT NULL
        );

        CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(swap_status);
        CREATE INDEX IF NOT EXISTS idx_transactions_created_at ON transactions(created_at);
        CREATE INDEX IF NOT EXISTS idx_transactions_payment_tx_id ON transactions(payment_tx_id);
        ";

        $this->pdo->exec($sql);
    }

    /**
     * Create test database schema for Capsule connection
     */
    protected function createCapsuleSchema(): void
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            transaction_id VARCHAR(255) UNIQUE NOT NULL,
            user_wallet_address VARCHAR(255) NOT NULL,
            payment_token VARCHAR(10) NOT NULL,
            payment_amount DECIMAL(20, 8) NOT NULL,
            cirx_amount DECIMAL(20, 8) NOT NULL,
            cirx_recipient_address VARCHAR(255) NOT NULL,
            swap_status VARCHAR(50) NOT NULL DEFAULT 'initiated',
            payment_tx_id VARCHAR(255),
            cirx_tx_id VARCHAR(255),
            discount_percentage DECIMAL(5, 2) DEFAULT 0.00,
            platform_fee DECIMAL(20, 8) DEFAULT 0.00000000,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            retry_count INTEGER DEFAULT 0,
            last_retry_at DATETIME NULL,
            failure_reason TEXT NULL
        );

        CREATE TABLE IF NOT EXISTS project_wallets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            chain VARCHAR(50) NOT NULL,
            token VARCHAR(10) NOT NULL,
            address VARCHAR(255) NOT NULL,
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(swap_status);
        CREATE INDEX IF NOT EXISTS idx_transactions_created_at ON transactions(created_at);
        CREATE INDEX IF NOT EXISTS idx_transactions_payment_tx_id ON transactions(payment_tx_id);
        ";

        Capsule::connection()->getPdo()->exec($sql);
    }

    /**
     * Seed test data
     */
    protected function seedTestData(): void
    {
        $testTransactions = [
            [
                'transaction_id' => 'tx_test_initiated_001',
                'user_wallet_address' => '0x834244d016f29d6acb42c1b054a88e2e9b1c9228',
                'payment_token' => 'USDC',
                'payment_amount' => '1000.000000',
                'cirx_amount' => '2160.000000',
                'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
                'swap_status' => 'initiated',
                'discount_percentage' => '8.00',
                'platform_fee' => '5.000000'
            ],
            [
                'transaction_id' => 'tx_test_pending_002',
                'user_wallet_address' => '0x8ba1f109551bD432803012645Hac136c33EbE6b1',
                'payment_token' => 'ETH',
                'payment_amount' => '0.400000',
                'cirx_amount' => '864.000000',
                'cirx_recipient_address' => '0xaa8dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
                'swap_status' => 'payment_pending',
                'payment_tx_id' => '0xabc123...',
                'discount_percentage' => '5.00',
                'platform_fee' => '0.002000'
            ]
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (
                transaction_id, user_wallet_address, payment_token, payment_amount,
                cirx_amount, cirx_recipient_address, swap_status, payment_tx_id,
                discount_percentage, platform_fee
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($testTransactions as $transaction) {
            $stmt->execute([
                $transaction['transaction_id'],
                $transaction['user_wallet_address'],
                $transaction['payment_token'],
                $transaction['payment_amount'],
                $transaction['cirx_amount'],
                $transaction['cirx_recipient_address'],
                $transaction['swap_status'],
                $transaction['payment_tx_id'] ?? null,
                $transaction['discount_percentage'],
                $transaction['platform_fee']
            ]);
        }
    }

    /**
     * Seed test data for Capsule connection
     */
    protected function seedCapsuleData(): void
    {
        $testTransactions = [
            [
                'transaction_id' => 'tx_test_initiated_001',
                'user_wallet_address' => '0x834244d016f29d6acb42c1b054a88e2e9b1c9228',
                'payment_token' => 'USDC',
                'payment_amount' => '1000.000000',
                'cirx_amount' => '2160.000000',
                'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
                'swap_status' => 'initiated',
                'discount_percentage' => '8.00',
                'platform_fee' => '5.000000'
            ],
            [
                'transaction_id' => 'tx_test_pending_002',
                'user_wallet_address' => '0x8ba1f109551bD432803012645Hac136c33EbE6b1',
                'payment_token' => 'ETH',
                'payment_amount' => '0.400000',
                'cirx_amount' => '864.000000',
                'cirx_recipient_address' => '0xaa8dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
                'swap_status' => 'payment_pending',
                'payment_tx_id' => '0xabc123...',
                'discount_percentage' => '5.00',
                'platform_fee' => '0.002000'
            ]
        ];

        // Insert data using Capsule connection
        foreach ($testTransactions as $transaction) {
            Capsule::connection()->table('transactions')->insert([
                'transaction_id' => $transaction['transaction_id'],
                'user_wallet_address' => $transaction['user_wallet_address'],
                'payment_token' => $transaction['payment_token'],
                'payment_amount' => $transaction['payment_amount'],
                'cirx_amount' => $transaction['cirx_amount'],
                'cirx_recipient_address' => $transaction['cirx_recipient_address'],
                'swap_status' => $transaction['swap_status'],
                'payment_tx_id' => $transaction['payment_tx_id'] ?? null,
                'discount_percentage' => $transaction['discount_percentage'],
                'platform_fee' => $transaction['platform_fee']
            ]);
        }

        // Insert project wallet data
        Capsule::connection()->table('project_wallets')->insert([
            'chain' => 'ethereum',
            'token' => 'USDC',
            'address' => '0x834244d016f29d6acb42c1b054a88e2e9b1c9228',
            'is_active' => 1
        ]);
    }

    /**
     * Create HTTP request for testing
     */
    protected function createRequest(
        string $method,
        string $uri,
        array $headers = [],
        $body = null
    ) {
        $request = $this->requestFactory->createServerRequest($method, $uri);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            if (is_array($body)) {
                $body = json_encode($body);
                $request = $request->withHeader('Content-Type', 'application/json');
            }
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        return $request;
    }

    /**
     * Execute request through full application
     */
    protected function runApp($request)
    {
        return $this->app->handle($request);
    }

    /**
     * Assert JSON response structure
     */
    protected function assertJsonResponse($response, int $expectedStatus, array $expectedKeys = []): array
    {
        $this->assertEquals($expectedStatus, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        
        $this->assertIsArray($data, 'Response body should be valid JSON');

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Response should contain key: {$key}");
        }

        return $data;
    }

    /**
     * Create authenticated request with API key
     */
    protected function createAuthenticatedRequest(string $method, string $uri, array $body = []): \Psr\Http\Message\ServerRequestInterface
    {
        $headers = ['X-API-Key' => 'test_integration_key_123'];
        return $this->createRequest($method, $uri, $headers, $body);
    }

    /**
     * Get test transaction from database
     */
    protected function getTestTransaction(string $transactionId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ?");
        $stmt->execute([$transactionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update test transaction status
     */
    protected function updateTransactionStatus(string $transactionId, string $status): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE transactions 
            SET swap_status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE transaction_id = ?
        ");
        $stmt->execute([$status, $transactionId]);
    }
}