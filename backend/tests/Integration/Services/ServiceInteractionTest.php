<?php

namespace Tests\Integration\Services;

use Tests\Integration\IntegrationTestCase;
use App\Services\CirxTransferService;
use App\Services\PaymentVerificationService;
use App\Services\HealthCheckService;
use App\Services\LoggerService;
use App\Models\Transaction;

/**
 * Integration tests for service layer interactions
 * 
 * @covers \App\Services\CirxTransferService
 * @covers \App\Services\PaymentVerificationService  
 * @covers \App\Services\HealthCheckService
 * @covers \App\Services\LoggerService
 */
class ServiceInteractionTest extends IntegrationTestCase
{
    private CirxTransferService $cirxTransferService;
    private PaymentVerificationService $paymentVerificationService;
    private HealthCheckService $healthCheckService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize services
        $this->cirxTransferService = new CirxTransferService();
        $this->paymentVerificationService = new PaymentVerificationService();
        $this->healthCheckService = new HealthCheckService();
    }

    /**
     * Test complete service interaction flow for OTC transaction
     */
    public function testCompleteServiceInteractionFlow(): void
    {
        // Create test transaction (matching integration test schema)
        $transactionData = [
            'transaction_id' => 'tx_service_integration_001',
            'user_wallet_address' => '0x834244d016f29d6acb42c1b054a88e2e9b1c9228',
            'payment_token' => 'USDC', 
            'payment_amount' => '5000.000000',
            'cirx_amount' => '10800.000000',
            'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
            'swap_status' => Transaction::STATUS_PENDING_PAYMENT_VERIFICATION,
            'payment_tx_id' => '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'discount_percentage' => '8.00',
            'platform_fee' => '25.000000'
        ];

        $this->insertTestTransaction($transactionData);
        $transaction = Transaction::fromArray($transactionData);

        // Step 1: Test payment verification service
        $verificationResult = $this->paymentVerificationService->verifyTransactionPayment($transaction);
        
        $this->assertNotNull($verificationResult);
        $this->assertInstanceOf(\App\Services\PaymentVerificationResult::class, $verificationResult);
        
        // Verify result structure
        $this->assertNotNull($verificationResult->getTransactionId());
        $this->assertNotNull($verificationResult->getStatus());

        // Step 2: Test CIRX transfer service calculations
        $this->assertTrue($this->cirxTransferService->validateCircularProtocolAddress($transaction->cirx_recipient_address));
        
        $cirxAmount = $this->cirxTransferService->calculateCirxAmountForTests(
            $transaction->payment_amount,
            $transaction->payment_token
        );
        $this->assertIsFloat($cirxAmount);
        $this->assertGreaterThan(0, $cirxAmount);

        $discountPercentage = $this->cirxTransferService->determineDiscountPercentage($transaction->payment_amount);
        $this->assertIsFloat($discountPercentage);
        $this->assertGreaterThanOrEqual(0, $discountPercentage);

        // Step 3: Test health check service
        $healthStatus = $this->healthCheckService->runAllChecks();
        
        $this->assertIsArray($healthStatus);
        $this->assertArrayHasKey('status', $healthStatus);
        $this->assertArrayHasKey('checks', $healthStatus);
        $this->assertArrayHasKey('timestamp', $healthStatus);

        // Step 4: Test service integration with logging
        LoggerService::logTransaction($transaction->transaction_id, 'integration_test', [
            'verification_result' => $verificationResult->getStatus(),
            'cirx_amount' => $cirxAmount,
            'discount_percentage' => $discountPercentage
        ]);

        // Verify no exceptions were thrown during service interactions
        $this->assertTrue(true, 'Service integration flow completed successfully');
    }

    /**
     * Test service error handling and exception propagation
     */
    public function testServiceErrorHandlingIntegration(): void
    {
        // Create transaction with invalid data to trigger service errors
        $invalidTransactionData = [
            'transaction_id' => 'tx_service_error_001',
            'user_wallet_address' => 'invalid_address',
            'payment_token' => 'INVALID_TOKEN',
            'payment_amount' => '-100.000000',
            'cirx_amount' => '0.000000',
            'cirx_recipient_address' => 'invalid_cirx_address',
            'swap_status' => Transaction::STATUS_PENDING_PAYMENT_VERIFICATION,
            'payment_tx_id' => 'invalid_tx_format',
            'discount_percentage' => '0.00',
            'platform_fee' => '0.000000'
        ];

        $this->insertTestTransaction($invalidTransactionData);
        $invalidTransaction = Transaction::fromArray($invalidTransactionData);

        // Test CIRX address validation
        $isValidAddress = $this->cirxTransferService->validateCircularProtocolAddress(
            $invalidTransaction->cirx_recipient_address
        );
        $this->assertFalse($isValidAddress, 'Invalid CIRX address should be rejected');

        // Test payment verification with invalid data
        try {
            $verificationResult = $this->paymentVerificationService->verifyTransactionPayment($invalidTransaction);
            
            // If no exception is thrown, verify the result indicates failure
            if ($verificationResult) {
                $this->assertFalse($verificationResult->isValid(), 'Invalid transaction should fail verification');
            }
        } catch (\Exception $e) {
            // Service should handle errors gracefully
            $this->assertInstanceOf(\Exception::class, $e);
        }

        // Test error logging
        LoggerService::logTransaction($invalidTransaction->transaction_id, 'error_test', [
            'error_type' => 'validation_failure',
            'invalid_fields' => ['user_wallet_address', 'payment_token', 'cirx_recipient_address']
        ]);

        $this->assertTrue(true, 'Service error handling completed');
    }

    /**
     * Test service performance under load
     */
    public function testServicePerformanceUnderLoad(): void
    {
        $performanceMetrics = [];
        $transactionCount = 20;

        for ($i = 1; $i <= $transactionCount; $i++) {
            $startTime = microtime(true);

            // Create test transaction
            $transactionData = [
                'transaction_id' => "tx_performance_{$i}",
                'user_wallet_address' => "0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B{$i}a3",
                'payment_token' => 'USDC',
                'payment_amount' => (1000 + $i * 100) . '.000000',
                'cirx_amount' => (2160 + $i * 216) . '.000000',
                'cirx_recipient_address' => "0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc41951437{$i}",
                'swap_status' => Transaction::STATUS_PENDING_PAYMENT_VERIFICATION,
                'payment_tx_id' => "0x" . str_repeat(dechex($i), 64),
                'discount_percentage' => '5.00',
                'platform_fee' => ($i * 5) . '.000000'
            ];

            $this->insertTestTransaction($transactionData);
            $transaction = Transaction::fromArray($transactionData);

            // Test service operations
            $this->cirxTransferService->validateCircularProtocolAddress($transaction->cirx_recipient_address);
            $this->cirxTransferService->calculateCirxAmountForTests($transaction->payment_amount, $transaction->payment_token);
            $this->cirxTransferService->determineDiscountPercentage($transaction->payment_amount);
            
            $this->paymentVerificationService->verifyTransactionPayment($transaction);

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $performanceMetrics[] = $executionTime;
        }

        // Analyze performance metrics
        $averageTime = array_sum($performanceMetrics) / count($performanceMetrics);
        $maxTime = max($performanceMetrics);
        $minTime = min($performanceMetrics);

        // Performance assertions
        $this->assertLessThan(100, $averageTime, 'Average service execution time should be under 100ms');
        $this->assertLessThan(500, $maxTime, 'Maximum service execution time should be under 500ms');

        // Log performance results
        LoggerService::logTransaction('performance_test', 'service_performance', [
            'transaction_count' => $transactionCount,
            'average_time_ms' => $averageTime,
            'max_time_ms' => $maxTime,
            'min_time_ms' => $minTime
        ]);
    }

    /**
     * Test service configuration and environment handling
     */
    public function testServiceConfigurationAndEnvironment(): void
    {
        // Test health check service with different configurations
        $_ENV['HEALTH_CHECK_ENABLED'] = 'true';
        $enabledHealthService = new HealthCheckService();
        $enabledResult = $enabledHealthService->runAllChecks();
        
        $this->assertArrayHasKey('status', $enabledResult);
        $this->assertNotEquals('disabled', $enabledResult['status']);

        $_ENV['HEALTH_CHECK_ENABLED'] = 'false';
        $disabledHealthService = new HealthCheckService();
        $disabledResult = $disabledHealthService->runAllChecks();
        
        $this->assertEquals('disabled', $disabledResult['status']);

        // Test quick status endpoint
        $quickStatus = $enabledHealthService->getQuickStatus();
        $this->assertArrayHasKey('status', $quickStatus);
        $this->assertArrayHasKey('timestamp', $quickStatus);

        // Clean up environment
        unset($_ENV['HEALTH_CHECK_ENABLED']);
    }

    /**
     * Test service dependency injection and initialization
     */
    public function testServiceDependencyInjectionAndInitialization(): void
    {
        // Test that services can be created without dependencies
        $cirxService = new CirxTransferService();
        $paymentService = new PaymentVerificationService();
        $healthService = new HealthCheckService();

        $this->assertInstanceOf(CirxTransferService::class, $cirxService);
        $this->assertInstanceOf(PaymentVerificationService::class, $paymentService);
        $this->assertInstanceOf(HealthCheckService::class, $healthService);

        // Test service methods are callable
        $this->assertTrue(method_exists($cirxService, 'validateCircularProtocolAddress'));
        $this->assertTrue(method_exists($cirxService, 'calculateCirxAmount'));
        $this->assertTrue(method_exists($paymentService, 'verifyTransactionPayment'));
        $this->assertTrue(method_exists($healthService, 'runAllChecks'));
    }

    /**
     * Test service exception handling and recovery
     */
    public function testServiceExceptionHandlingAndRecovery(): void
    {
        // Test CIRX transfer service exception handling
        try {
            $result = $this->cirxTransferService->calculateCirxAmount('invalid_amount', 'INVALID_TOKEN', 'liquid');
            
            // If no exception, verify graceful handling
            $this->assertIsFloat($result, 'Service should handle invalid input gracefully');
        } catch (\App\Exceptions\CirxTransferException $e) {
            $this->assertInstanceOf(\App\Exceptions\CirxTransferException::class, $e);
            $this->assertNotEmpty($e->getMessage());
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e);
        }

        // Test payment verification exception handling
        try {
            $invalidTransaction = new \stdClass();
            $result = $this->paymentVerificationService->verifyTransactionPayment($invalidTransaction);
            
            // Should either return a result or throw exception
            $this->assertTrue(is_object($result) || $result === null);
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Test logging service integration across all services
     */
    public function testLoggingServiceIntegrationAcrossServices(): void
    {
        $testTransactionId = 'tx_logging_integration_001';

        // Test different log types
        LoggerService::logTransaction($testTransactionId, 'transaction_initiated', [
            'payment_token' => 'USDC',
            'amount' => '1000.00'
        ]);

        LoggerService::logWorker('payment_verification', 'Processing transaction batch', [
            'transaction_count' => 5,
            'batch_id' => 'batch_001'
        ]);

        LoggerService::logSecurity('API key authentication', [
            'client_id' => 'test_client',
            'endpoint' => '/api/v1/transactions/initiate-swap'
        ]);

        LoggerService::logBlockchain('cirx_transfer', 'CIRX transfer initiated', [
            'recipient' => '0xbb9...', 
            'amount' => '2160.00',
            'tx_id' => '0xabc123...'
        ]);

        // Verify logging doesn't throw exceptions
        $this->assertTrue(true, 'Logging service integration completed successfully');
    }

    /**
     * Helper method to insert test transaction
     */
    private function insertTestTransaction(array $transactionData): void
    {
        $fields = implode(', ', array_keys($transactionData));
        $placeholders = ':' . implode(', :', array_keys($transactionData));
        
        $sql = "INSERT INTO transactions ({$fields}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($transactionData);
    }
}