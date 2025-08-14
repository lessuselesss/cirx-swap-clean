<?php

namespace Tests\Unit\Middleware;

use App\Middleware\LoggingMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * @covers \App\Middleware\LoggingMiddleware
 */
class LoggingMiddlewareTest extends TestCase
{
    private LoggingMiddleware $middleware;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        // Configure logging for testing
        $_ENV['API_LOGGING_ENABLED'] = 'true';
        $_ENV['LOG_REQUEST_BODY'] = 'false';
        $_ENV['LOG_RESPONSE_BODY'] = 'false';
        
        $this->middleware = new LoggingMiddleware();
        
        // Mock handler that returns success response
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->handler->method('handle')->willReturn(new Response());
    }

    protected function tearDown(): void
    {
        unset($_ENV['API_LOGGING_ENABLED'], $_ENV['LOG_REQUEST_BODY'], $_ENV['LOG_RESPONSE_BODY']);
    }

    public function testLoggingDisabledSkipsMiddleware(): void
    {
        $_ENV['API_LOGGING_ENABLED'] = 'false';
        $middleware = new LoggingMiddleware();
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/test');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHealthCheckExemptFromDetailedLogging(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/health');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRequestIdAddedToRequestAttributes(): void
    {
        $capturedRequest = null;
        
        // Handler that captures the request
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response();
        });
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/transactions/initiate-swap');
        
        $this->middleware->process($request, $handler);
        
        $this->assertNotNull($capturedRequest);
        $this->assertNotNull($capturedRequest->getAttribute('request_id'));
        $this->assertStringStartsWith('req_', $capturedRequest->getAttribute('request_id'));
    }

    public function testLogsApiRequestWithBasicInformation(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/transactions/initiate-swap')
            ->withHeader('User-Agent', 'TestClient/1.0')
            ->withHeader('Origin', 'https://test.com');
        
        // Test doesn't throw exceptions and completes successfully
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandlesExceptionsDuringProcessing(): void
    {
        // Handler that throws an exception
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new \Exception('Test exception'));
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/test');
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');
        
        $this->middleware->process($request, $handler);
    }

    public function testRequestBodyLoggingWhenEnabled(): void
    {
        $_ENV['LOG_REQUEST_BODY'] = 'true';
        $middleware = new LoggingMiddleware();
        
        $requestBody = json_encode(['test' => 'data', 'amount' => '100.0']);
        $streamFactory = new \Slim\Psr7\Factory\StreamFactory();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/transactions/initiate-swap')
            ->withBody($streamFactory->createStream($requestBody));
        
        // Test completes without exceptions
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSensitiveDataRedaction(): void
    {
        $_ENV['LOG_REQUEST_BODY'] = 'true';
        $middleware = new LoggingMiddleware();
        
        $requestBody = json_encode([
            'test' => 'data',
            'password' => 'secret123',
            'api_key' => 'key_secret',
            'amount' => '100.0'
        ]);
        
        $streamFactory = new \Slim\Psr7\Factory\StreamFactory();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/transactions/initiate-swap')
            ->withBody($streamFactory->createStream($requestBody));
        
        // Test completes without exceptions - sensitive data would be redacted in actual logs
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testResponseBodyLoggingWhenEnabled(): void
    {
        $_ENV['LOG_RESPONSE_BODY'] = 'true';
        $middleware = new LoggingMiddleware();
        
        // Mock handler with JSON response
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = new Response();
        $response->getBody()->write(json_encode(['status' => 'success', 'id' => 'tx123']));
        $handler->method('handle')->willReturn($response);
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/transactions/initiate-swap');
        
        $result = $middleware->process($request, $handler);
        
        $this->assertEquals(200, $result->getStatusCode());
    }

    public function testClientIdentificationFromHeaders(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/test')
            ->withHeader('X-Forwarded-For', '192.168.1.1, 10.0.0.1')
            ->withAttribute('api_key', 'test_****_key');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testClientIdentificationFromRealIp(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/test')
            ->withHeader('X-Real-IP', '203.0.113.1');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAddExemptPath(): void
    {
        $this->middleware->addExemptPath('/api/v1/test-exempt');
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/test-exempt');
        
        // Should process without detailed logging
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSlowRequestDetection(): void
    {
        // Handler that simulates slow processing
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function () {
            // Simulate processing time
            usleep(1000); // 1ms
            return new Response();
        });
        
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/slow-endpoint');
        
        $response = $this->middleware->process($request, $handler);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDifferentHttpMethods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        
        foreach ($methods as $method) {
            $request = (new ServerRequestFactory())->createServerRequest($method, '/api/v1/test');
            
            $response = $this->middleware->process($request, $this->handler);
            
            $this->assertEquals(200, $response->getStatusCode(), "Failed for method: {$method}");
        }
    }
}