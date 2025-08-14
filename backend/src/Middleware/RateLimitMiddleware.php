<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Rate Limiting Middleware
 * 
 * Implements token bucket rate limiting with memory-based storage
 * In production, this should use Redis for distributed rate limiting
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $requestsPerWindow;
    private int $windowInSeconds;
    private array $buckets = [];
    private bool $enabled;
    private array $exemptPaths;

    public function __construct()
    {
        $this->requestsPerWindow = (int) ($_ENV['API_RATE_LIMIT_REQUESTS'] ?? 100);
        $this->windowInSeconds = (int) ($_ENV['API_RATE_LIMIT_WINDOW'] ?? 60);
        $this->enabled = filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        
        // Exempt paths from rate limiting
        $this->exemptPaths = [
            '/api/v1/health'
        ];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled || $this->isExemptPath($request->getUri()->getPath())) {
            return $handler->handle($request);
        }

        $clientId = $this->getClientIdentifier($request);
        $currentTime = time();

        // Clean up expired buckets periodically
        $this->cleanupExpiredBuckets($currentTime);

        // Check rate limit
        if (!$this->isAllowed($clientId, $currentTime)) {
            return $this->createRateLimitResponse($clientId, $currentTime);
        }

        // Process request
        $response = $handler->handle($request);

        // Add rate limit headers
        return $this->addRateLimitHeaders($response, $clientId, $currentTime);
    }

    /**
     * Get client identifier for rate limiting
     */
    private function getClientIdentifier(ServerRequestInterface $request): string
    {
        // Try to get API key first for authenticated requests
        $apiKey = $request->getAttribute('api_key');
        if ($apiKey) {
            return 'api_key:' . $apiKey;
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

        // Get from server params
        $serverParams = $request->getServerParams();
        $ip = $serverParams['REMOTE_ADDR'] ?? 'unknown';
        
        return 'ip:' . $ip;
    }

    /**
     * Check if request is allowed based on rate limits
     */
    private function isAllowed(string $clientId, int $currentTime): bool
    {
        if (!isset($this->buckets[$clientId])) {
            $this->buckets[$clientId] = [
                'tokens' => $this->requestsPerWindow - 1, // Consume one token
                'last_refill' => $currentTime,
                'requests_this_window' => 1
            ];
            return true;
        }

        $bucket = &$this->buckets[$clientId];
        
        // Calculate tokens to add based on time elapsed
        $timeElapsed = $currentTime - $bucket['last_refill'];
        $tokensToAdd = floor($timeElapsed * ($this->requestsPerWindow / $this->windowInSeconds));

        if ($tokensToAdd > 0) {
            $bucket['tokens'] = min($this->requestsPerWindow, $bucket['tokens'] + $tokensToAdd);
            $bucket['last_refill'] = $currentTime;
            
            // Reset request count if we're in a new window
            if ($timeElapsed >= $this->windowInSeconds) {
                $bucket['requests_this_window'] = 0;
            }
        }

        // Check if we have tokens available
        if ($bucket['tokens'] < 1) {
            return false;
        }

        // Consume a token
        $bucket['tokens']--;
        $bucket['requests_this_window']++;
        
        return true;
    }

    /**
     * Check if path is exempt from rate limiting
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
     * Create rate limit exceeded response
     */
    private function createRateLimitResponse(string $clientId, int $currentTime): ResponseInterface
    {
        $bucket = $this->buckets[$clientId] ?? null;
        $resetTime = $bucket ? $bucket['last_refill'] + $this->windowInSeconds : $currentTime + $this->windowInSeconds;
        
        $response = new Response();
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'Rate limit exceeded',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'limit' => $this->requestsPerWindow,
            'window' => $this->windowInSeconds,
            'reset_time' => date('c', $resetTime),
            'retry_after' => max(1, $resetTime - $currentTime)
        ]));
        
        return $response
            ->withStatus(429)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-RateLimit-Limit', (string) $this->requestsPerWindow)
            ->withHeader('X-RateLimit-Remaining', '0')
            ->withHeader('X-RateLimit-Reset', (string) $resetTime)
            ->withHeader('Retry-After', (string) max(1, $resetTime - $currentTime));
    }

    /**
     * Add rate limit headers to successful responses
     */
    private function addRateLimitHeaders(ResponseInterface $response, string $clientId, int $currentTime): ResponseInterface
    {
        $bucket = $this->buckets[$clientId] ?? null;
        if (!$bucket) {
            return $response;
        }

        $remaining = max(0, $bucket['tokens']);
        $resetTime = $bucket['last_refill'] + $this->windowInSeconds;

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->requestsPerWindow)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) $resetTime);
    }

    /**
     * Clean up expired buckets to prevent memory leaks
     */
    private function cleanupExpiredBuckets(int $currentTime): void
    {
        static $lastCleanup = 0;
        
        // Only cleanup every 5 minutes
        if ($currentTime - $lastCleanup < 300) {
            return;
        }

        foreach ($this->buckets as $clientId => $bucket) {
            // Remove buckets that haven't been used for more than 2 windows
            if ($currentTime - $bucket['last_refill'] > ($this->windowInSeconds * 2)) {
                unset($this->buckets[$clientId]);
            }
        }

        $lastCleanup = $currentTime;
    }

    /**
     * Get current rate limit status for a client
     */
    public function getRateLimitStatus(string $clientId, int $currentTime): array
    {
        if (!isset($this->buckets[$clientId])) {
            return [
                'limit' => $this->requestsPerWindow,
                'remaining' => $this->requestsPerWindow,
                'reset_time' => $currentTime + $this->windowInSeconds
            ];
        }

        $bucket = $this->buckets[$clientId];
        $resetTime = $bucket['last_refill'] + $this->windowInSeconds;

        return [
            'limit' => $this->requestsPerWindow,
            'remaining' => max(0, $bucket['tokens']),
            'reset_time' => $resetTime,
            'requests_this_window' => $bucket['requests_this_window']
        ];
    }
}