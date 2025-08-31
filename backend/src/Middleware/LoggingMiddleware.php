<?php

namespace App\Middleware;

use App\Services\LoggerService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Logging Middleware for API requests and responses
 * 
 * Automatically logs all API requests with timing, status codes, and context
 */
class LoggingMiddleware implements MiddlewareInterface
{
    private bool $enabled;
    private array $exemptPaths;
    private bool $logRequestBody;
    private bool $logResponseBody;

    public function __construct()
    {
        $this->enabled = (bool) ($_ENV['API_LOGGING_ENABLED'] ?? true);
        $this->logRequestBody = (bool) ($_ENV['LOG_REQUEST_BODY'] ?? false);
        $this->logResponseBody = (bool) ($_ENV['LOG_RESPONSE_BODY'] ?? false);
        
        // Paths to exclude from detailed logging
        $this->exemptPaths = [
            '/api/v1/health'
        ];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $startTime = microtime(true);
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $clientId = $this->getClientIdentifier($request);
        $requestId = $this->generateRequestId();

        // Add request ID to request attributes
        $request = $request->withAttribute('request_id', $requestId);

        // Log request start
        if (!$this->isExemptPath($path)) {
            $this->logRequestStart($method, $path, $clientId, $requestId, $request);
        }

        try {
            // Process the request
            $response = $handler->handle($request);
            
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2); // Duration in milliseconds
            $statusCode = $response->getStatusCode();

            // Log request completion
            $this->logRequestComplete($method, $path, $clientId, $requestId, $statusCode, $duration, $request, $response);

            return $response;

        } catch (\Throwable $exception) {
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            // Log request error
            $this->logRequestError($method, $path, $clientId, $requestId, $duration, $exception);

            throw $exception;
        }
    }

    /**
     * Log request start
     */
    private function logRequestStart(
        string $method,
        string $path,
        string $clientId,
        string $requestId,
        ServerRequestInterface $request
    ): void {
        $context = [
            'request_id' => $requestId,
            'client_id' => $clientId,
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'origin' => $request->getHeaderLine('Origin'),
            'query_params' => $request->getQueryParams()
        ];

        // Add request body if enabled and not sensitive
        if ($this->logRequestBody && !$this->isSensitivePath($path)) {
            $body = (string) $request->getBody();
            if (!empty($body)) {
                // Parse JSON and redact sensitive fields
                $parsedBody = json_decode($body, true);
                if (is_array($parsedBody)) {
                    $parsedBody = $this->redactSensitiveData($parsedBody);
                    $context['request_body'] = $parsedBody;
                } else {
                    $context['request_body_size'] = strlen($body);
                }
            }
        }

        LoggerService::logApiRequest($method, $path, $context, $clientId);
    }

    /**
     * Log request completion
     */
    private function logRequestComplete(
        string $method,
        string $path,
        string $clientId,
        string $requestId,
        int $statusCode,
        float $duration,
        ServerRequestInterface $request,
        ResponseInterface $response
    ): void {
        $context = [
            'request_id' => $requestId,
            'duration_ms' => $duration,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];

        // Add response body if enabled and not too large
        if ($this->logResponseBody && !$this->isExemptPath($path)) {
            $responseBody = (string) $response->getBody();
            if (!empty($responseBody) && strlen($responseBody) < 10000) {
                $parsedBody = json_decode($responseBody, true);
                if (is_array($parsedBody)) {
                    $context['response_body'] = $parsedBody;
                } else {
                    $context['response_body_size'] = strlen($responseBody);
                }
            }
        }

        // Add rate limit headers if present
        if ($response->hasHeader('X-RateLimit-Remaining')) {
            $context['rate_limit_remaining'] = $response->getHeaderLine('X-RateLimit-Remaining');
        }

        // Determine log level based on status code
        $level = 'info';
        if ($statusCode >= 400 && $statusCode < 500) {
            $level = 'warning';
        } elseif ($statusCode >= 500) {
            $level = 'error';
        }

        LoggerService::logApiRequest($method, $path, $context, $clientId, $statusCode);

        // Log slow requests
        if ($duration > 1000) { // Requests taking more than 1 second
            try {
                LoggerService::getLogger('performance')->warning(
                    "Slow API request: {$method} {$path} took {$duration}ms",
                    $context
                );
            } catch (\UnexpectedValueException $e) {
                if (strpos($e->getMessage(), 'Broken pipe') === false) {
                    throw $e;  // Re-throw non-broken-pipe errors
                }
                // Silently ignore broken pipe logging errors
            }
        }
    }

    /**
     * Log request error
     */
    private function logRequestError(
        string $method,
        string $path,
        string $clientId,
        string $requestId,
        float $duration,
        \Throwable $exception
    ): void {
        $context = [
            'request_id' => $requestId,
            'duration_ms' => $duration,
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'stack_trace' => $exception->getTraceAsString()
        ];

        try {
            LoggerService::getLogger('error')->error(
                "API request error: {$method} {$path} - {$exception->getMessage()}",
                $context
            );
        } catch (\UnexpectedValueException $e) {
            if (strpos($e->getMessage(), 'Broken pipe') === false) {
                throw $e;  // Re-throw non-broken-pipe errors
            }
            // Silently ignore broken pipe logging errors
        }
    }

    /**
     * Get client identifier for logging
     */
    private function getClientIdentifier(ServerRequestInterface $request): string
    {
        // Try to get API key first
        $apiKey = $request->getAttribute('api_key');
        if ($apiKey) {
            return "api_key:{$apiKey}";
        }

        // Fall back to IP address
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded) {
            $ips = explode(',', $forwarded);
            return 'ip:' . trim($ips[0]);
        }

        $realIp = $request->getHeaderLine('X-Real-IP');
        if ($realIp) {
            return 'ip:' . $realIp;
        }

        $serverParams = $request->getServerParams();
        $ip = $serverParams['REMOTE_ADDR'] ?? 'unknown';
        
        return 'ip:' . $ip;
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }

    /**
     * Check if path is exempt from detailed logging
     */
    private function isExemptPath(string $path): bool
    {
        foreach ($this->exemptPaths as $exemptPath) {
            if (strpos($path, $exemptPath) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if path contains sensitive data
     */
    private function isSensitivePath(string $path): bool
    {
        $sensitivePaths = [
            '/api/v1/security',
            '/api/v1/admin'
        ];

        foreach ($sensitivePaths as $sensitivePath) {
            if (strpos($path, $sensitivePath) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Redact sensitive data from request/response bodies
     */
    private function redactSensitiveData(array $data): array
    {
        $sensitiveKeys = [
            'password', 'token', 'api_key', 'private_key', 
            'secret', 'auth', 'authorization', 'wallet_private_key'
        ];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            
            // Redact sensitive keys
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (strpos($lowerKey, $sensitiveKey) !== false) {
                    $data[$key] = '[REDACTED]';
                    continue 2;
                }
            }

            // Recursively redact nested arrays
            if (is_array($value)) {
                $data[$key] = $this->redactSensitiveData($value);
            }
        }

        return $data;
    }

    /**
     * Add exempt path
     */
    public function addExemptPath(string $path): void
    {
        if (!in_array($path, $this->exemptPaths)) {
            $this->exemptPaths[] = $path;
        }
    }
}