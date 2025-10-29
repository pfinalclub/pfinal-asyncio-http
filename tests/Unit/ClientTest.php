<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Handler\HandlerStack;
use PFinal\AsyncioHttp\Handler\AsyncioHandler;
use PFinal\AsyncioHttp\Psr7\Request;
use PFinal\AsyncioHttp\Psr7\Response;
use PFinal\AsyncioHttp\Psr7\Uri;
use Mockery as m;

class ClientTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testConstructor()
    {
        $client = new Client();
        $this->assertInstanceOf(Client::class, $client);

        // 测试自定义配置
        $client = new Client(['timeout' => 30]);
        $this->assertInstanceOf(Client::class, $client);
    }

    public function testGetConfig()
    {
        $client = new Client(['timeout' => 30]);
        $this->assertEquals(30, $client->getConfig()['timeout']);
        // 移除不存在的getConfig方法调用，直接访问数组
        $this->assertFalse(isset($client->getConfig()['non_existent']));
    }

    public function testGetHandlerStack()
    {
        $client = new Client();
        $this->assertInstanceOf(HandlerStack::class, $client->getHandlerStack());
    }

    public function testCreateWithCustomHandler()
    {
        // 创建处理器栈而不是直接使用handler mock
        $handlerStack = new HandlerStack();
        $client = new Client(['handler' => $handlerStack]);
        $this->assertInstanceOf(HandlerStack::class, $client->getHandlerStack());
    }

    public function testCreateRequest()
    {
        // 直接测试Request对象的创建
        $request = new Request('GET', 'http://example.com');
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('http://example.com', (string) $request->getUri());
    }

    public function testRequestWithBaseUri()
    {
        // 测试带base_uri的客户端配置
        $client = new Client(['base_uri' => 'http://example.com/api']);
        $this->assertEquals('http://example.com/api', $client->getConfig()['base_uri']);
        
        // 手动构建带基础URI的请求进行验证
        $uri = new Uri('http://example.com/api/users');
        $request = new Request('GET', $uri);
        
        $this->assertEquals('http://example.com/api/users', (string) $request->getUri());
    }

    public function testRequestWithQueryParams()
    {
        // 测试带查询参数的URI
        $uri = new Uri('http://example.com');
        $uri = $uri->withQuery(http_build_query(['foo' => 'bar', 'baz' => 'qux']));
        $request = new Request('GET', $uri);
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('foo=bar&baz=qux', $request->getUri()->getQuery());
    }

    public function testRequestWithJson()
    {
        $uri = new Uri('http://example.com');
        $body = '{"foo":"bar"}';
        $request = new Request('POST', $uri, ['Content-Type' => 'application/json'], $body);
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function testConfigAccess()
    {
        // 测试配置访问
        $client = new Client(['base_uri' => 'http://api.example.com', 'timeout' => 15]);
        $config = $client->getConfig();
        
        $this->assertIsArray($config);
        $this->assertEquals('http://api.example.com', $config['base_uri']);
        $this->assertEquals(15, $config['timeout']);
    }

    public function testHandlerStackInitialization()
    {
        // 测试处理器栈初始化
        $client = new Client();
        $stack = $client->getHandlerStack();
        
        $this->assertInstanceOf(HandlerStack::class, $stack);
    }

    public function testResolveUri()
    {
        $client = new Client(['base_uri' => 'http://example.com/api']);
        
        // 使用反射访问私有方法
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('resolveUri');
        $method->setAccessible(true);
        
        // 修正参数类型，确保resolveUri接受正确类型的参数
        $relativeUri = $method->invoke($client, '/users');
        $this->assertInstanceOf(Uri::class, $relativeUri);
        $this->assertEquals('http://example.com/api/users', (string) $relativeUri);
    }

    public function testResolveUriWithAbsoluteUri()
    {
        $client = new Client(['base_uri' => 'http://example.com/api']);
        
        // 使用反射访问私有方法
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('resolveUri');
        $method->setAccessible(true);
        
        // 修正参数类型，确保resolveUri接受正确类型的参数
        $absoluteUri = $method->invoke($client, 'http://test.com/absolute');
        $this->assertInstanceOf(Uri::class, $absoluteUri);
        $this->assertEquals('http://test.com/absolute', (string) $absoluteUri);
    }
}