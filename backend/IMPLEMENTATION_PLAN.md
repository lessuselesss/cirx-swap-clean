# CIRX OTC Backend Implementation Plan

## Project Overview

This document outlines the implementation plan for a PHP backend supporting the CIRX OTC trading platform. The backend provides RESTful JSON API endpoints for payment verification and CIRX token transfers using Test-Driven Development (TDD) methodology.

## Architecture Summary

Based on the analysis in `architecture.md`, we're building:
- **PHP REST API** with JSON responses
- **MySQL database** for transaction tracking
- **Blockchain integration** for payment verification and CIRX transfers
- **Background processing** for asynchronous operations
- **Comprehensive testing** using PHPUnit with TDD approach

## Implementation Strategy: TDD Approach

### Phase 1: Foundation Setup
1. **Test Environment Setup**
   - PHPUnit configuration
   - Database testing with SQLite/MySQL
   - Mock HTTP clients for blockchain APIs
   - Code coverage reporting

2. **Core Structure Tests**
   - API routing tests
   - Database connection tests
   - Configuration loading tests
   - Dependency injection tests

### Phase 2: Database Layer (TDD)
1. **Write Tests First**
   - Transaction model creation/retrieval tests
   - Database migration tests  
   - Data validation tests
   - Constraint violation tests

2. **Implement Database Layer**
   - Transaction model with ORM/PDO
   - Migration system
   - Database seeding for tests

### Phase 3: API Endpoints (TDD)
1. **Write API Tests First**
   - POST /transactions/initiate-swap tests
   - GET /transactions/{swapId}/status tests
   - Input validation tests
   - Error response tests

2. **Implement API Controllers**
   - Request validation
   - Response formatting
   - Error handling
   - HTTP status codes

### Phase 4: Blockchain Integration (TDD)
1. **Write Integration Tests First**
   - Payment verification service tests
   - CIRX transfer service tests
   - Blockchain API client tests
   - Retry mechanism tests

2. **Implement Blockchain Services**
   - Payment verification logic
   - CIRX transfer logic
   - External API clients
   - Error handling and retries

### Phase 5: Background Processing (TDD)
1. **Write Worker Tests First**
   - Job processing tests
   - Queue management tests
   - Status update tests
   - Failure handling tests

2. **Implement Background Workers**
   - Payment verification worker
   - CIRX transfer worker
   - Status monitoring
   - Notification system

## Technology Stack

### Core Dependencies
```json
{
  "require": {
    "php": "^8.2",
    "slim/slim": "^4.0",
    "slim/psr7": "^1.0",
    "illuminate/database": "^10.0",
    "guzzlehttp/guzzle": "^7.0",
    "ramsey/uuid": "^4.0",
    "monolog/monolog": "^3.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.0",
    "mockery/mockery": "^1.0",
    "fakerphp/faker": "^1.0"
  }
}
```

### Framework Choice: Slim Framework
- Lightweight and fast
- Perfect for REST APIs
- Excellent middleware support
- Easy testing capabilities

### Database: Eloquent ORM
- Laravel's ORM without the framework
- Migration system
- Model relationships
- Query builder

## Directory Structure

```
backend/
├── src/
│   ├── Controllers/
│   │   ├── TransactionController.php
│   │   └── HealthController.php
│   ├── Models/
│   │   ├── Transaction.php
│   │   └── ProjectWallet.php
│   ├── Services/
│   │   ├── PaymentVerificationService.php
│   │   ├── CirxTransferService.php
│   │   ├── BlockchainApiClient.php
│   │   └── NotificationService.php
│   ├── Workers/
│   │   ├── PaymentVerificationWorker.php
│   │   └── CirxTransferWorker.php
│   ├── Validators/
│   │   ├── SwapRequestValidator.php
│   │   └── AddressValidator.php
│   ├── Exceptions/
│   │   ├── PaymentVerificationException.php
│   │   └── CirxTransferException.php
│   └── Database/
│       ├── migrations/
│       └── seeds/
├── tests/
│   ├── Unit/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Validators/
│   ├── Integration/
│   │   ├── Api/
│   │   ├── Database/
│   │   └── Blockchain/
│   └── TestCase.php
├── config/
│   ├── database.php
│   ├── app.php
│   └── blockchain.php
├── public/
│   └── index.php
├── composer.json
├── phpunit.xml
└── .env.example
```

## TDD Implementation Workflow

### Step 1: Write Failing Tests
For each feature, start with tests that define expected behavior:

```php
// tests/Unit/Controllers/TransactionControllerTest.php
public function test_initiate_swap_returns_success_response()
{
    $request = $this->createRequest('POST', '/transactions/initiate-swap', [
        'txId' => '0x123...',
        'paymentChain' => 'ethereum',
        'cirxRecipientAddress' => '0xabc...',
        'amountPaid' => '1.0',
        'paymentToken' => 'ETH'
    ]);

    $response = $this->app->handle($request);
    
    $this->assertEquals(202, $response->getStatusCode());
    
    $data = json_decode((string) $response->getBody(), true);
    $this->assertEquals('success', $data['status']);
    $this->assertArrayHasKey('swapId', $data);
}
```

### Step 2: Run Tests (They Should Fail)
```bash
./vendor/bin/phpunit --testdox
```

### Step 3: Implement Minimum Code to Pass
```php
// src/Controllers/TransactionController.php
public function initiateSwap(Request $request, Response $response): Response
{
    // Minimal implementation to pass test
    $data = [
        'status' => 'success',
        'message' => 'Swap request received and being processed.',
        'swapId' => Uuid::uuid4()->toString()
    ];
    
    $response->getBody()->write(json_encode($data));
    return $response->withStatus(202)->withHeader('Content-Type', 'application/json');
}
```

### Step 4: Refactor and Add More Tests
Continue this cycle for each feature and edge case.

## Database Schema Implementation

### TDD for Database Layer
1. **Write Migration Tests**
   - Test table creation
   - Test column constraints
   - Test index creation

2. **Write Model Tests**
   - Test model creation
   - Test validation rules
   - Test relationships

3. **Implement Schema**
   ```sql
   CREATE TABLE transactions (
       id VARCHAR(36) PRIMARY KEY,
       payment_tx_id VARCHAR(255) NOT NULL UNIQUE,
       payment_chain VARCHAR(50) NOT NULL,
       cirx_recipient_address VARCHAR(255) NOT NULL,
       amount_paid DECIMAL(65, 18) NOT NULL,
       payment_token VARCHAR(10) NOT NULL,
       swap_status ENUM(
           'pending_payment_verification',
           'payment_verified', 
           'cirx_transfer_pending',
           'cirx_transfer_initiated',
           'completed',
           'failed_payment_verification',
           'failed_cirx_transfer'
       ) NOT NULL DEFAULT 'pending_payment_verification',
       cirx_transfer_tx_id VARCHAR(255) NULL,
       failure_reason TEXT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   );
   ```

## API Endpoints Implementation

### TDD for API Layer

#### POST /transactions/initiate-swap
**Test Coverage:**
- Valid request handling
- Input validation (invalid addresses, amounts, chains)
- Duplicate transaction handling
- Database persistence
- Response format validation

#### GET /transactions/{swapId}/status  
**Test Coverage:**
- Valid swapId lookup
- Invalid swapId handling
- Status reporting accuracy
- Response format validation

## Blockchain Integration Implementation

### TDD for Blockchain Services

#### Payment Verification Service Tests
```php
public function test_verifies_ethereum_payment_successfully()
{
    // Mock blockchain API response
    $mockClient = Mockery::mock(BlockchainApiClient::class);
    $mockClient->shouldReceive('getTransaction')
        ->with('0x123...', 'ethereum')
        ->andReturn([
            'status' => 'confirmed',
            'confirmations' => 12,
            'to' => '0xproject_wallet...',
            'value' => '1000000000000000000' // 1 ETH in wei
        ]);

    $service = new PaymentVerificationService($mockClient);
    $result = $service->verifyPayment('0x123...', 'ethereum', '1.0', 'ETH');
    
    $this->assertTrue($result->isValid());
}
```

#### CIRX Transfer Service Tests
```php
public function test_transfers_cirx_tokens_successfully()
{
    $mockClient = Mockery::mock(CirxBlockchainClient::class);
    $mockClient->shouldReceive('sendTransaction')
        ->andReturn(['txId' => '0xabc...', 'status' => 'pending']);

    $service = new CirxTransferService($mockClient);
    $result = $service->transferCirx('0xrecipient...', '100.0');
    
    $this->assertEquals('0xabc...', $result->getTransactionId());
}
```

## Background Processing Implementation

### TDD for Worker Classes
1. **Test Job Processing**
   - Payment verification jobs
   - CIRX transfer jobs
   - Status update jobs
   - Error handling jobs

2. **Test Queue Management**
   - Job queuing
   - Job retry logic
   - Job failure handling
   - Dead letter queues

## Security Implementation

### TDD for Security Features
1. **Input Validation Tests**
   - SQL injection prevention
   - XSS prevention
   - Address format validation
   - Amount validation

2. **Authentication/Authorization Tests**
   - API key validation
   - Rate limiting
   - CORS handling

3. **Private Key Security Tests**
   - Encryption/decryption
   - Key rotation
   - Secure storage

## Testing Strategy

### Unit Tests (70% Coverage Target)
- Individual class/method testing
- Mock external dependencies
- Fast execution (< 1 second)

### Integration Tests (20% Coverage Target)
- Database integration
- API endpoint testing
- External service integration

### End-to-End Tests (10% Coverage Target)
- Complete workflow testing
- Real blockchain interaction (testnet)
- Performance testing

## Deployment Considerations

### Environment Configuration
```php
// config/app.php
return [
    'debug' => env('APP_DEBUG', false),
    'blockchain' => [
        'ethereum' => [
            'rpc_url' => env('ETHEREUM_RPC_URL'),
            'api_key' => env('ETHERSCAN_API_KEY'),
        ],
        'cirx' => [
            'rpc_url' => env('CIRX_RPC_URL'),
            'treasury_private_key' => env('CIRX_TREASURY_PRIVATE_KEY'),
        ],
    ],
    'database' => [
        'host' => env('DB_HOST', 'localhost'),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
    ],
];
```

### Docker Configuration
```dockerfile
FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql zip

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy application
COPY . /var/www/html/
COPY .htaccess /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/
```

## Performance Considerations

### Database Optimization
- Proper indexing on frequently queried columns
- Connection pooling
- Query optimization
- Caching layer (Redis)

### API Optimization  
- Response caching
- Gzip compression
- CDN for static assets
- Load balancing

### Background Processing Optimization
- Queue prioritization
- Batch processing
- Resource monitoring
- Graceful shutdown

## Monitoring and Logging

### Logging Strategy
```php
// src/Services/LoggerService.php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SlackWebhookHandler;

class LoggerService 
{
    public function logTransactionEvent(string $event, array $context): void
    {
        $this->logger->info($event, [
            'transaction_id' => $context['swapId'],
            'payment_tx_id' => $context['txId'],
            'status' => $context['status'],
            'timestamp' => now(),
        ]);
    }
    
    public function logError(Exception $e, array $context = []): void
    {
        $this->logger->error($e->getMessage(), [
            'exception' => $e,
            'context' => $context,
            'stack_trace' => $e->getTraceAsString(),
        ]);
    }
}
```

### Health Checks
- Database connectivity
- Blockchain API availability
- Queue processing status
- Disk space monitoring

## Next Steps

1. **Set up development environment**
2. **Initialize Composer project** 
3. **Configure PHPUnit**
4. **Write first failing test**
5. **Implement minimal passing code**
6. **Continue TDD cycle**

This plan provides a comprehensive roadmap for implementing the CIRX OTC backend using TDD methodology, ensuring robust, testable, and maintainable code.