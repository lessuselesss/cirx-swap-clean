<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * API Key Authentication Middleware
 * 
 * Validates API keys for protected endpoints
 */
class ApiKeyAuthMiddleware implements MiddlewareInterface
{
    private array $validApiKeys;
    private array $exemptPaths;
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = filter_var($_ENV['API_KEY_REQUIRED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        
        // Load valid API keys from environment
        $apiKeysString = $_ENV['API_KEYS'] ?? $_ENV['API_KEY'] ?? '';
        $this->validApiKeys = array_filter(explode(',', $apiKeysString));
        
        // Exempt paths that don't require API key authentication
        $this->exemptPaths = [
            '/',                // Root route
            '/api/v1/health',
            '/api/v1/debug',  // Allow all debug endpoints
            '/favicon.ico'
        ];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Skip authentication if disabled or path is exempt
        if (!$this->enabled || $this->isExemptPath($request->getUri()->getPath())) {
            return $handler->handle($request);
        }

        // Skip authentication for OPTIONS requests (CORS preflight)
        if ($request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }

        // Check for API key in headers
        $apiKey = $this->extractApiKey($request);

        if (!$apiKey) {
            return $this->createUnauthorizedResponse('API key required');
        }

        if (!$this->isValidApiKey($apiKey)) {
            return $this->createUnauthorizedResponse('Invalid API key');
        }

        // Add API key info to request attributes for logging
        $request = $request->withAttribute('api_key', $this->maskApiKey($apiKey));

        return $handler->handle($request);
    }

    /**
     * Extract API key from request headers
     */
    private function extractApiKey(ServerRequestInterface $request): ?string
    {
        // Try X-API-Key header first
        $apiKey = $request->getHeaderLine('X-API-Key');
        
        if (!$apiKey) {
            // Try Authorization header with Bearer token
            $authHeader = $request->getHeaderLine('Authorization');
            if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
                $apiKey = $matches[1];
            }
        }

        return $apiKey ?: null;
    }

    /**
     * Check if API key is valid
     */
    private function isValidApiKey(string $apiKey): bool
    {
        return in_array($apiKey, $this->validApiKeys, true);
    }

    /**
     * Check if path is exempt from authentication
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
     * Mask API key for logging (show first 4 and last 4 characters)
     */
    private function maskApiKey(string $apiKey): string
    {
        if (strlen($apiKey) <= 8) {
            return str_repeat('*', strlen($apiKey));
        }
        
        return substr($apiKey, 0, 4) . str_repeat('*', strlen($apiKey) - 8) . substr($apiKey, -4);
    }

    /**
     * Create unauthorized response
     */
    private function createUnauthorizedResponse(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => $message,
            'code' => 'UNAUTHORIZED',
            'timestamp' => date('c')
        ]));
        
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('WWW-Authenticate', 'Bearer realm="CIRX OTC API"');
    }

    /**
     * Add API key to exempt paths
     */
    public function addExemptPath(string $path): void
    {
        $this->exemptPaths[] = $path;
    }
}