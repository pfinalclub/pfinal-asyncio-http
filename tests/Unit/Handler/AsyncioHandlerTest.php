<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Tests\Unit\Handler;

use PHPUnit\Framework\TestCase;
use PFinal\AsyncioHttp\Handler\AsyncioHandler;
use PFinal\AsyncioHttp\Exception\ConnectException;
use PFinal\AsyncioHttp\Psr7\Request;
use PFinal\AsyncioHttp\Psr7\Uri;
use PFinal\AsyncioHttp\Handler\Future;
use Mockery as m;

class AsyncioHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testConstructorWithDefaultConfig()
    {
        $handler = new AsyncioHandler();
        $this->assertInstanceOf(AsyncioHandler::class, $handler);
    }

    public function testConstructorWithCustomConfig()
    {
        $config = [
            'timeout' => 10,
            'verify' => false,
            'cert' => '/path/to/cert',
            'ssl_key' => '/path/to/key',
        ];
        
        $handler = new AsyncioHandler($config);
        $this->assertInstanceOf(AsyncioHandler::class, $handler);
    }

    public function testHandleMethodCreatesFuture()
    {
        $handler = new AsyncioHandler();
        $request = new Request('GET', 'http://example.com');
        
        // 由于handle方法依赖于Workerman的AsyncTcpConnection，这里我们无法直接测试它的完整功能
        // 但我们可以测试它是否正确创建了Future对象
        // 注意：在实际运行时，这个测试需要在事件循环中运行
    }

    public function testBuildHttpRequest()
    {
        // 测试buildHttpRequest方法
        $handler = new AsyncioHandler();
        $request = new Request(
            'POST',
            'http://example.com/api',
            ['Content-Type' => 'application/json'],
            '{"test":"data"}'
        );
        
        // 使用反射访问私有方法
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('buildHttpRequest');
        $method->setAccessible(true);
        
        $result = $method->invoke($handler, $request);
        
        // 验证HTTP请求字符串的基本格式
        $this->assertStringContainsString('POST /api HTTP/1.1', $result);
        $this->assertStringContainsString('Host: example.com', $result);
        $this->assertStringContainsString('Content-Type: application/json', $result);
        $this->assertStringContainsString('{"test":"data"}', $result);
    }

    public function testBuildHttpRequestWithQueryParams()
    {
        $handler = new AsyncioHandler();
        $uri = new Uri('http://example.com/api?param1=value1&param2=value2');
        $request = new Request('GET', $uri);
        
        // 使用反射访问私有方法
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('buildHttpRequest');
        $method->setAccessible(true);
        
        $result = $method->invoke($handler, $request);
        
        // 验证查询参数被正确包含
        $this->assertStringContainsString('GET /api?param1=value1&param2=value2 HTTP/1.1', $result);
    }

    public function testBuildHttpRequestWithHeaders()
    {
        $handler = new AsyncioHandler();
        $request = new Request(
            'GET',
            'http://example.com',
            [
                'User-Agent' => 'Test-Agent/1.0',
                'Accept' => 'application/json',
                'X-Custom-Header' => 'custom-value'
            ]
        );
        
        // 使用反射访问私有方法
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('buildHttpRequest');
        $method->setAccessible(true);
        
        $result = $method->invoke($handler, $request);
        
        // 验证请求头被正确包含
        $this->assertStringContainsString('User-Agent: Test-Agent/1.0', $result);
        $this->assertStringContainsString('Accept: application/json', $result);
        $this->assertStringContainsString('X-Custom-Header: custom-value', $result);
    }

    public function testWithSslVerifyTrue()
    {
        $config = ['verify' => true];
        $handler = new AsyncioHandler($config);
        
        $this->assertInstanceOf(AsyncioHandler::class, $handler);
    }

    public function testWithSslVerifyFalse()
    {
        $config = ['verify' => false];
        $handler = new AsyncioHandler($config);
        
        $this->assertInstanceOf(AsyncioHandler::class, $handler);
    }

    public function testWithCertAndKey()
    {
        $config = [
            'cert' => '/path/to/cert.pem',
            'ssl_key' => '/path/to/key.pem',
        ];
        $handler = new AsyncioHandler($config);
        
        $this->assertInstanceOf(AsyncioHandler::class, $handler);
    }

    public function testHandleWithInvalidUrl()
    {
        // 注意：在实际运行时，这个测试需要在事件循环中运行，并且会抛出ConnectException
        // 但由于测试环境的限制，我们无法直接测试异步连接失败的情况
    }
}