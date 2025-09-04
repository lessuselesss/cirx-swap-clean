<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS (Cross-Origin Resource Sharing) Middleware
 * 
 * Handles CORS configuration with environment-based allowed origins
 */
class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;
    private array $allowedHeaders;
    private array $allowedMethods;
    private int $maxAge;
    private bool $allowCredentials;

    public function __construct()
    {
        // Parse allowed origins from environment
        $originsString = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*';
        $this->allowedOrigins = $originsString === '*' ? ['*'] : array_map('trim', explode(',', $originsString));
        
        // Configure allowed headers
        $this->allowedHeaders = [
            'X-Requested-With',
            'Content-Type',
            'Accept',
            'Origin',
            'Authorization',
            'X-API-Key',
            'X-Request-ID',
            'Cache-Control'
        ];
        
        // Configure allowed methods
        $this->allowedMethods = [
            'GET',
            'POST',
            'PUT',
            'DELETE',
            'PATCH',
            'OPTIONS'
        ];
        
        // Preflight cache duration (24 hours)
        $this->maxAge = (int) ($_ENV['CORS_MAX_AGE'] ?? 86400);
        
        // Whether to allow credentials
        $this->allowCredentials = (bool) ($_ENV['CORS_ALLOW_CREDENTIALS'] ?? false);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($request);
        }

        // Process the actual request
        $response = $handler->handle($request);

        // Add CORS headers to the response
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle CORS preflight requests
     */
    private function handlePreflightRequest(ServerRequestInterface $request): ResponseInterface
    {
        $response = new \Slim\Psr7\Response();
        
        $origin = $request->getHeaderLine('Origin');
        
        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            return $response->withStatus(403);
        }

        // Add preflight headers
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $this->getAllowedOrigin($origin))
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response->withStatus(200);
    }

    /**
     * Add CORS headers to response
     */
    private function addCorsHeaders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        
        // Only add CORS headers if origin is provided and allowed
        if ($origin && $this->isOriginAllowed($origin)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $this->getAllowedOrigin($origin))
                ->withHeader('Access-Control-Expose-Headers', implode(', ', [
                    'X-RateLimit-Limit',
                    'X-RateLimit-Remaining',
                    'X-RateLimit-Reset'
                ]));

            if ($this->allowCredentials) {
                $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
            }
        } elseif (in_array('*', $this->allowedOrigins, true)) {
            // Allow all origins if configured
            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        }

        return $response;
    }

    /**
     * Check if origin is allowed
     */
    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return true; // Allow requests without origin (like from Postman)
        }

        // Allow all origins if * is configured
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        // Check exact match
        if (in_array($origin, $this->allowedOrigins, true)) {
            return true;
        }

        // Check wildcard patterns
        foreach ($this->allowedOrigins as $allowedOrigin) {
            if ($this->matchesWildcardPattern($origin, $allowedOrigin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the allowed origin to return in headers
     */
    private function getAllowedOrigin(string $origin): string
    {
        // If allowing all origins, return the specific origin for credentials support
        if (in_array('*', $this->allowedOrigins, true) && $this->allowCredentials) {
            return $origin;
        }

        // If allowing all origins without credentials, return *
        if (in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }

        // Return the specific origin
        return $origin;
    }

    /**
     * Match origin against wildcard pattern
     */
    private function matchesWildcardPattern(string $origin, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        // First escape special regex chars, then replace * with .*
        $pattern = str_replace(['.', '/'], ['\.', '\/'], $pattern);
        $pattern = str_replace('*', '.*', $pattern);
        $pattern = '/^' . $pattern . '$/i';
        
        return preg_match($pattern, $origin) === 1;
    }

    /**
     * Add allowed origin
     */
    public function addAllowedOrigin(string $origin): void
    {
        if (!in_array($origin, $this->allowedOrigins, true)) {
            $this->allowedOrigins[] = $origin;
        }
    }

    /**
     * Add allowed header
     */
    public function addAllowedHeader(string $header): void
    {
        if (!in_array($header, $this->allowedHeaders, true)) {
            $this->allowedHeaders[] = $header;
        }
    }

    /**
     * Get current CORS configuration
     */
    public function getConfiguration(): array
    {
        return [
            'allowed_origins' => $this->allowedOrigins,
            'allowed_headers' => $this->allowedHeaders,
            'allowed_methods' => $this->allowedMethods,
            'max_age' => $this->maxAge,
            'allow_credentials' => $this->allowCredentials
        ];
    }
}