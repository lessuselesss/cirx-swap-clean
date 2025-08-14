<?php

namespace Tests\Unit\Middleware;

use App\Middleware\CorsMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * @covers \App\Middleware\CorsMiddleware
 */
class CorsMiddlewareTest extends TestCase
{
    private CorsMiddleware $middleware;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        // Configure CORS for testing
        $_ENV['CORS_ALLOWED_ORIGINS'] = 'https://app.test.com,https://admin.test.com';
        $_ENV['CORS_ALLOW_CREDENTIALS'] = 'false';
        $_ENV['CORS_MAX_AGE'] = '3600';
        
        $this->middleware = new CorsMiddleware();
        
        // Mock handler that returns success response
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->handler->method('handle')->willReturn(new Response());
    }

    protected function tearDown(): void
    {
        unset($_ENV['CORS_ALLOWED_ORIGINS'], $_ENV['CORS_ALLOW_CREDENTIALS'], $_ENV['CORS_MAX_AGE']);
    }

    public function testPreflightRequestWithAllowedOrigin(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('OPTIONS', '/api/v1/test')
            ->withHeader('Origin', 'https://app.test.com');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('https://app.test.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Methods'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Headers'));
        $this->assertEquals('3600', $response->getHeaderLine('Access-Control-Max-Age'));
    }

    public function testPreflightRequestWithDisallowedOrigin(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('OPTIONS', '/api/v1/test')
            ->withHeader('Origin', 'https://malicious.com');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPreflightRequestWithWildcardOrigins(): void
    {
        $_ENV['CORS_ALLOWED_ORIGINS'] = '*';
        $middleware = new CorsMiddleware();
        
        $request = (new ServerRequestFactory())
            ->createServerRequest('OPTIONS', '/api/v1/test')
            ->withHeader('Origin', 'https://any-origin.com');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('https://any-origin.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testActualRequestWithAllowedOrigin(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/test')
            ->withHeader('Origin', 'https://app.test.com');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('https://app.test.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertTrue($response->hasHeader('Access-Control-Expose-Headers'));
    }

    public function testActualRequestWithDisallowedOrigin(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/test')
            ->withHeader('Origin', 'https://malicious.com');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode()); // Request still processes
        $this->assertEmpty($response->getHeaderLine('Access-Control-Allow-Origin')); // But no CORS headers
    }

    public function testRequestWithoutOrigin(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/test');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCredentialsAllowedConfiguration(): void
    {
        $_ENV['CORS_ALLOW_CREDENTIALS'] = 'true';
        $_ENV['CORS_ALLOWED_ORIGINS'] = 'https://app.test.com';
        $middleware = new CorsMiddleware();
        
        $request = (new ServerRequestFactory())
            ->createServerRequest('OPTIONS', '/api/v1/test')
            ->withHeader('Origin', 'https://app.test.com');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    public function testCredentialsWithWildcardOrigin(): void
    {
        $_ENV['CORS_ALLOW_CREDENTIALS'] = 'true';
        $_ENV['CORS_ALLOWED_ORIGINS'] = '*';
        $middleware = new CorsMiddleware();
        
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/v1/test')
            ->withHeader('Origin', 'https://app.test.com');
        
        $response = $middleware->process($request, $this->handler);
        
        // With credentials enabled and wildcard, should return specific origin
        $this->assertEquals('https://app.test.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertEquals('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    public function testWildcardPatternMatching(): void
    {
        $_ENV['CORS_ALLOWED_ORIGINS'] = 'https://*.test.com';
        $middleware = new CorsMiddleware();
        
        $request = (new ServerRequestFactory())
            ->createServerRequest('OPTIONS', '/api/v1/test')
            ->withHeader('Origin', 'https://app.test.com');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('https://app.test.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testExposeHeaders(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/test')
            ->withHeader('Origin', 'https://app.test.com');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $exposeHeaders = $response->getHeaderLine('Access-Control-Expose-Headers');
        $this->assertStringContainsString('X-RateLimit-Limit', $exposeHeaders);
        $this->assertStringContainsString('X-RateLimit-Remaining', $exposeHeaders);
        $this->assertStringContainsString('X-RateLimit-Reset', $exposeHeaders);
    }

    public function testAddAllowedOrigin(): void
    {
        $this->middleware->addAllowedOrigin('https://new-app.test.com');
        
        $request = (new ServerRequestFactory())
            ->createServerRequest('OPTIONS', '/api/v1/test')
            ->withHeader('Origin', 'https://new-app.test.com');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('https://new-app.test.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testAddAllowedHeader(): void
    {
        $this->middleware->addAllowedHeader('X-Custom-Header');
        
        $request = (new ServerRequestFactory())
            ->createServerRequest('OPTIONS', '/api/v1/test')
            ->withHeader('Origin', 'https://app.test.com');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $allowedHeaders = $response->getHeaderLine('Access-Control-Allow-Headers');
        $this->assertStringContainsString('X-Custom-Header', $allowedHeaders);
    }

    public function testGetConfiguration(): void
    {
        $config = $this->middleware->getConfiguration();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('allowed_origins', $config);
        $this->assertArrayHasKey('allowed_headers', $config);
        $this->assertArrayHasKey('allowed_methods', $config);
        $this->assertArrayHasKey('max_age', $config);
        $this->assertArrayHasKey('allow_credentials', $config);
        
        $this->assertIsArray($config['allowed_origins']);
        $this->assertIsArray($config['allowed_headers']);
        $this->assertIsArray($config['allowed_methods']);
        $this->assertIsInt($config['max_age']);
        $this->assertIsBool($config['allow_credentials']);
    }

    public function testMultipleOriginsConfiguration(): void
    {
        $origins = ['https://app.test.com', 'https://admin.test.com', 'https://api.test.com'];
        $_ENV['CORS_ALLOWED_ORIGINS'] = implode(',', $origins);
        $middleware = new CorsMiddleware();
        
        foreach ($origins as $origin) {
            $request = (new ServerRequestFactory())
                ->createServerRequest('OPTIONS', '/api/v1/test')
                ->withHeader('Origin', $origin);
            
            $response = $middleware->process($request, $this->handler);
            
            $this->assertEquals(200, $response->getStatusCode(), "Failed for origin: {$origin}");
            $this->assertEquals($origin, $response->getHeaderLine('Access-Control-Allow-Origin'));
        }
    }

    public function testCaseInsensitiveWildcardMatching(): void
    {
        $_ENV['CORS_ALLOWED_ORIGINS'] = 'https://*.TEST.com';
        $middleware = new CorsMiddleware();
        
        $request = (new ServerRequestFactory())
            ->createServerRequest('OPTIONS', '/api/v1/test')
            ->withHeader('Origin', 'https://app.test.com');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
}