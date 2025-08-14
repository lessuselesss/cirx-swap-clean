<?php

namespace Tests\Unit\Middleware;

use App\Middleware\RateLimitMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * @covers \App\Middleware\RateLimitMiddleware
 */
class RateLimitMiddlewareTest extends TestCase
{
    private RateLimitMiddleware $middleware;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        // Configure rate limiting for testing
        $_ENV['RATE_LIMIT_ENABLED'] = 'true';
        $_ENV['API_RATE_LIMIT_REQUESTS'] = '5';
        $_ENV['API_RATE_LIMIT_WINDOW'] = '60';
        
        $this->middleware = new RateLimitMiddleware();
        
        // Mock handler that returns success response
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->handler->method('handle')->willReturn(new Response());
    }

    protected function tearDown(): void
    {
        unset($_ENV['RATE_LIMIT_ENABLED'], $_ENV['API_RATE_LIMIT_REQUESTS'], $_ENV['API_RATE_LIMIT_WINDOW']);
    }

    public function testHealthCheckExemptFromRateLimit(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/health');
        
        // Make many requests to health endpoint
        for ($i = 0; $i < 10; $i++) {
            $response = $this->middleware->process($request, $this->handler);
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function testRateLimitDisabledAllowsAllRequests(): void
    {
        $_ENV['RATE_LIMIT_ENABLED'] = 'false';
        $middleware = new RateLimitMiddleware();
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/transactions/initiate-swap');
        
        // Make many requests
        for ($i = 0; $i < 10; $i++) {
            $response = $middleware->process($request, $this->handler);
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function testRateLimitingBlocksExcessiveRequests(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/transactions/initiate-swap');
        
        // First 5 requests should succeed (within rate limit)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->middleware->process($request, $this->handler);
            $this->assertEquals(200, $response->getStatusCode());
        }
        
        // 6th request should be rate limited
        $response = $this->middleware->process($request, $this->handler);
        $this->assertEquals(429, $response->getStatusCode());
        $this->assertStringContainsString('Rate limit exceeded', (string) $response->getBody());
    }

    public function testRateLimitHeadersAddedToSuccessfulResponses(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/transactions/initiate-swap');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Reset'));
        $this->assertEquals('5', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertEquals('4', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testRateLimitHeadersAddedToRateLimitedResponses(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/transactions/initiate-swap');
        
        // Exhaust rate limit
        for ($i = 0; $i < 5; $i++) {
            $this->middleware->process($request, $this->handler);
        }
        
        // Next request should be rate limited with proper headers
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(429, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Reset'));
        $this->assertTrue($response->hasHeader('Retry-After'));
        $this->assertEquals('5', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertEquals('0', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testDifferentIpAddressesHaveSeparateRateLimits(): void
    {
        // Create requests with different server parameters manually
        $factory = new ServerRequestFactory();
        
        // Create first request with IP 1
        $request1 = $factory->createServerRequest('POST', '/api/v1/transactions/initiate-swap');
        // Inject server parameters via reflection since withServerParams doesn't exist
        $reflection = new \ReflectionClass($request1);
        $serverParams = $reflection->getProperty('serverParams');
        $serverParams->setAccessible(true);
        $serverParams->setValue($request1, ['REMOTE_ADDR' => '192.168.1.1']);
        
        // Create second request with IP 2
        $request2 = $factory->createServerRequest('POST', '/api/v1/transactions/initiate-swap');
        $reflection2 = new \ReflectionClass($request2);
        $serverParams2 = $reflection2->getProperty('serverParams');
        $serverParams2->setAccessible(true);
        $serverParams2->setValue($request2, ['REMOTE_ADDR' => '192.168.1.2']);
        
        // Exhaust rate limit for first IP
        for ($i = 0; $i < 5; $i++) {
            $response = $this->middleware->process($request1, $this->handler);
            $this->assertEquals(200, $response->getStatusCode());
        }
        
        // First IP should be rate limited
        $response = $this->middleware->process($request1, $this->handler);
        $this->assertEquals(429, $response->getStatusCode());
        
        // Second IP should still be allowed
        $response = $this->middleware->process($request2, $this->handler);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetRateLimitStatus(): void
    {
        $clientId = 'ip:192.168.1.1';
        $currentTime = time();
        
        $status = $this->middleware->getRateLimitStatus($clientId, $currentTime);
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('limit', $status);
        $this->assertArrayHasKey('remaining', $status);
        $this->assertArrayHasKey('reset_time', $status);
        $this->assertEquals(5, $status['limit']);
        $this->assertEquals(5, $status['remaining']);
    }
}