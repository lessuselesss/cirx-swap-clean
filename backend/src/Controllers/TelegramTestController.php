<?php

namespace App\Controllers;

use App\Services\LoggerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Controller for testing Telegram notification functionality
 * 
 * IMPORTANT: This controller should only be available in development/testing environments
 */
class TelegramTestController
{
    /**
     * Test Telegram configuration and connectivity
     */
    public function testConnection(Request $request, Response $response): Response
    {
        // Security check: Only allow in non-production environments
        $environment = $_ENV['APP_ENV'] ?? 'production';
        if ($environment === 'production') {
            return $this->errorResponse($response, 403, 'Telegram testing endpoints are not available in production');
        }

        try {
            $results = LoggerService::testTelegramNotifications();
            
            $statusCode = $results['success'] ? 200 : 500;
            
            $responseData = [
                'status' => $results['success'] ? 'success' : 'error',
                'message' => $results['success'] ? 'Telegram notifications working correctly' : 'Telegram notifications failed',
                'tests' => $results['tests'],
                'errors' => $results['errors']
            ];
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 500, 'Failed to test Telegram notifications', [
                'exception' => $e->getMessage()
            ]);
        }

        $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    /**
     * Trigger a test error notification via the logging system
     */
    public function triggerTestError(Request $request, Response $response): Response
    {
        // Security check: Only allow in non-production environments
        $environment = $_ENV['APP_ENV'] ?? 'production';
        if ($environment === 'production') {
            return $this->errorResponse($response, 403, 'Telegram testing endpoints are not available in production');
        }

        try {
            // Parse request body for custom error details
            $body = $request->getBody()->getContents();
            $data = $body ? json_decode($body, true) : [];
            
            $errorType = $data['error_type'] ?? 'test_error';
            $errorMessage = $data['message'] ?? 'This is a test error triggered from the API';
            $context = array_merge([
                'test' => true,
                'triggered_by' => 'api',
                'timestamp' => date('c'),
                'request_id' => uniqid(),
                'user_agent' => $request->getHeaderLine('User-Agent')
            ], $data['context'] ?? []);

            // Get appropriate logger channel
            $logger = LoggerService::getLogger('api');
            
            // Log the error (this will trigger Telegram notification if configured)
            $logger->error($errorMessage, [
                'error_type' => $errorType,
                'context' => $context
            ]);

            $responseData = [
                'status' => 'success',
                'message' => 'Test error notification triggered',
                'details' => [
                    'error_type' => $errorType,
                    'message' => $errorMessage,
                    'telegram_configured' => !empty($_ENV['TELEGRAM_BOT_TOKEN']) && !empty($_ENV['TELEGRAM_CHAT_ID']),
                    'logged_at' => date('c')
                ]
            ];

        } catch (\Exception $e) {
            return $this->errorResponse($response, 500, 'Failed to trigger test error', [
                'exception' => $e->getMessage()
            ]);
        }

        $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    /**
     * Trigger different types of test errors for comprehensive testing
     */
    public function triggerMultipleErrors(Request $request, Response $response): Response
    {
        // Security check: Only allow in non-production environments
        $environment = $_ENV['APP_ENV'] ?? 'production';
        if ($environment === 'production') {
            return $this->errorResponse($response, 403, 'Telegram testing endpoints are not available in production');
        }

        try {
            $logger = LoggerService::getLogger('test-suite');
            $triggeredErrors = [];

            // Define test scenarios
            $testErrors = [
                [
                    'type' => 'cirx_transfer_failed',
                    'message' => 'Test CIRX transfer failure - should be critical priority',
                    'context' => ['transaction_id' => 'test-' . uniqid(), 'amount' => '1000.00']
                ],
                [
                    'type' => 'payment_verification_failed',
                    'message' => 'Test payment verification failure - should be critical priority',
                    'context' => ['tx_hash' => '0x' . bin2hex(random_bytes(32)), 'blockchain' => 'ethereum']
                ],
                [
                    'type' => 'api_rate_limit_exceeded',
                    'message' => 'Test API rate limit exceeded - should be high priority',
                    'context' => ['endpoint' => '/api/v1/swap', 'limit' => 100]
                ],
                [
                    'type' => 'general_error',
                    'message' => 'Test general error - should be low priority',
                    'context' => ['component' => 'test-suite']
                ]
            ];

            foreach ($testErrors as $testError) {
                $logger->error($testError['message'], [
                    'error_type' => $testError['type'],
                    'test' => true,
                    'triggered_by' => 'test_suite',
                    'timestamp' => date('c')
                ] + $testError['context']);
                
                $triggeredErrors[] = [
                    'error_type' => $testError['type'],
                    'message' => $testError['message'],
                    'priority' => $this->getErrorPriority($testError['type'])
                ];
                
                // Add small delay to prevent overwhelming Telegram
                usleep(500000); // 0.5 seconds
            }

            $responseData = [
                'status' => 'success',
                'message' => 'Multiple test errors triggered',
                'triggered_errors' => $triggeredErrors,
                'note' => 'Check your Telegram chat for notifications. Critical errors should appear first.'
            ];

        } catch (\Exception $e) {
            return $this->errorResponse($response, 500, 'Failed to trigger multiple test errors', [
                'exception' => $e->getMessage()
            ]);
        }

        $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    /**
     * Get Telegram configuration status
     */
    public function getStatus(Request $request, Response $response): Response
    {
        try {
            $status = [
                'telegram_configured' => !empty($_ENV['TELEGRAM_BOT_TOKEN']) && !empty($_ENV['TELEGRAM_CHAT_ID']),
                'bot_token_set' => !empty($_ENV['TELEGRAM_BOT_TOKEN']),
                'chat_id_set' => !empty($_ENV['TELEGRAM_CHAT_ID']),
                'rate_limit_messages' => (int) ($_ENV['TELEGRAM_RATE_LIMIT_MESSAGES'] ?? 3),
                'rate_limit_window' => (int) ($_ENV['TELEGRAM_RATE_LIMIT_WINDOW'] ?? 300),
                'environment' => $_ENV['APP_ENV'] ?? 'unknown',
                'testing_available' => ($_ENV['APP_ENV'] ?? 'production') !== 'production'
            ];

            $responseData = [
                'status' => 'success',
                'telegram_status' => $status
            ];

        } catch (\Exception $e) {
            return $this->errorResponse($response, 500, 'Failed to get Telegram status', [
                'exception' => $e->getMessage()
            ]);
        }

        $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    /**
     * Get error priority for display
     */
    private function getErrorPriority(string $errorType): string
    {
        $criticalErrors = [
            'database_connection_failed',
            'cirx_transfer_failed',
            'payment_verification_failed',
            'blockchain_client_error',
            'wallet_configuration_error'
        ];

        $highPriorityErrors = [
            'transaction_timeout',
            'api_rate_limit_exceeded',
            'authentication_failure',
            'invalid_transaction_state',
            'blockchain_rpc_error'
        ];

        if (in_array($errorType, $criticalErrors)) {
            return 'critical';
        }

        if (in_array($errorType, $highPriorityErrors)) {
            return 'high';
        }

        return 'low';
    }

    /**
     * Generate error response
     */
    private function errorResponse(Response $response, int $statusCode, string $message, array $errors = []): Response
    {
        $data = [
            'status' => 'error',
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        if (!empty($errors)) {
            $data['errors'] = $errors;
        }

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}