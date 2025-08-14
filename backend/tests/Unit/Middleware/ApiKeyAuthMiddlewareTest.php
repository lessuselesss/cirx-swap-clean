<?php

namespace Tests\Unit\Middleware;

use App\Middleware\ApiKeyAuthMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * @covers \App\Middleware\ApiKeyAuthMiddleware
 */
class ApiKeyAuthMiddlewareTest extends TestCase
{
    private ApiKeyAuthMiddleware $middleware;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        // Mock valid API keys for testing
        $_ENV['API_KEY_REQUIRED'] = 'true';
        $_ENV['API_KEYS'] = 'test_key_123,test_key_456';
        
        $this->middleware = new ApiKeyAuthMiddleware();
        
        // Mock handler that returns success response
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->handler->method('handle')->willReturn(new Response());
    }

    protected function tearDown(): void
    {
        unset($_ENV['API_KEY_REQUIRED'], $_ENV['API_KEYS']);
    }

    public function testHealthCheckExemptFromAuthentication(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/health');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testOptionsRequestExemptFromAuthentication(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('OPTIONS', '/api/v1/transactions/initiate-swap');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testMissingApiKeyReturnsUnauthorized(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/transactions/initiate-swap');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('API key required', (string) $response->getBody());
    }

    public function testInvalidApiKeyReturnsUnauthorized(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/transactions/initiate-swap')
            ->withHeader('X-API-Key', 'invalid_key');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Invalid API key', (string) $response->getBody());
    }

    public function testValidApiKeyInHeaderAllowsRequest(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/transactions/initiate-swap')
            ->withHeader('X-API-Key', 'test_key_123');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testValidApiKeyInAuthorizationHeaderAllowsRequest(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/transactions/initiate-swap')
            ->withHeader('Authorization', 'Bearer test_key_456');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testApiKeyDisabledAllowsAllRequests(): void
    {
        $_ENV['API_KEY_REQUIRED'] = 'false';
        $middleware = new ApiKeyAuthMiddleware();
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/transactions/initiate-swap');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testApiKeyIsAddedToRequestAttributes(): void
    {
        $capturedRequest = null;
        
        // Handler that captures the request
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response();
        });
        
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/transactions/initiate-swap')
            ->withHeader('X-API-Key', 'test_key_123');
        
        $this->middleware->process($request, $handler);
        
        $this->assertNotNull($capturedRequest);
        $this->assertStringContainsString('test****_123', $capturedRequest->getAttribute('api_key'));
    }

    public function testUnauthorizedResponseIncludesWwwAuthenticateHeader(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/transactions/initiate-swap');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('WWW-Authenticate'));
        $this->assertStringContainsString('Bearer realm="CIRX OTC API"', $response->getHeaderLine('WWW-Authenticate'));
    }
}