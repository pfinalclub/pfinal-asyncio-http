<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Tests\Unit\Handler;

use PHPUnit\Framework\TestCase;
use PFinal\AsyncioHttp\Handler\HandlerStack;
use PFinal\AsyncioHttp\Handler\AsyncioHandler;
use PFinal\AsyncioHttp\Handler\HandlerInterface;
use PFinal\AsyncioHttp\Psr7\Request;
use PFinal\AsyncioHttp\Psr7\Response;
use PFinal\AsyncioHttp\Promise\PromiseInterface;
use Mockery as m;

class HandlerStackTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testConstructorWithDefaultHandler()
    {
        $stack = new HandlerStack();
        $this->assertInstanceOf(HandlerStack::class, $stack);
        $this->assertInstanceOf(AsyncioHandler::class, $stack->getHandler());
    }

    public function testConstructorWithCustomHandler()
    {
        $handler = m::mock(HandlerInterface::class);
        $stack = new HandlerStack($handler);
        $this->assertSame($handler, $stack->getHandler());
    }

    public function testPushMiddleware()
    {
        $stack = new HandlerStack();
        
        // 创建一个简单的中间件
        $middleware1 = function (callable $handler) {
            return function ($request, $options) use ($handler) {
                $request = $request->withHeader('X-Test1', 'Value1');
                return $handler($request, $options);
            };
        };
        
        $middleware2 = function (callable $handler) {
            return function ($request, $options) use ($handler) {
                $request = $request->withHeader('X-Test2', 'Value2');
                return $handler($request, $options);
            };
        };
        
        $stack->push($middleware1, 'middleware1');
        $stack->push($middleware2, 'middleware2');
        
        // 检查中间件是否按正确顺序添加
        $debug = $stack->debug();
        $this->assertStringContainsString('[1] middleware1', $debug);
        $this->assertStringContainsString('[2] middleware2', $debug);
    }

    public function testUnshiftMiddleware()
    {
        $stack = new HandlerStack();
        
        $middleware1 = function (callable $handler) {
            return $handler;
        };
        
        $middleware2 = function (callable $handler) {
            return $handler;
        };
        
        $stack->push($middleware1, 'middleware1');
        $stack->unshift($middleware2, 'middleware2');
        
        $debug = $stack->debug();
        $this->assertStringContainsString('[1] middleware2', $debug);
        $this->assertStringContainsString('[2] middleware1', $debug);
    }

    public function testBeforeMiddleware()
    {
        $stack = new HandlerStack();
        
        $middleware1 = function (callable $handler) {
            return $handler;
        };
        
        $middleware2 = function (callable $handler) {
            return $handler;
        };
        
        $stack->push($middleware1, 'middleware1');
        $stack->before('middleware1', $middleware2, 'middleware2');
        
        $debug = $stack->debug();
        $this->assertStringContainsString('[1] middleware2', $debug);
        $this->assertStringContainsString('[2] middleware1', $debug);
    }

    public function testAfterMiddleware()
    {
        $stack = new HandlerStack();
        
        $middleware1 = function (callable $handler) {
            return $handler;
        };
        
        $middleware2 = function (callable $handler) {
            return $handler;
        };
        
        $stack->push($middleware1, 'middleware1');
        $stack->after('middleware1', $middleware2, 'middleware2');
        
        $debug = $stack->debug();
        $this->assertStringContainsString('[1] middleware1', $debug);
        $this->assertStringContainsString('[2] middleware2', $debug);
    }

    public function testRemoveMiddleware()
    {
        $stack = new HandlerStack();
        
        $middleware1 = function (callable $handler) {
            return $handler;
        };
        
        $middleware2 = function (callable $handler) {
            return $handler;
        };
        
        $stack->push($middleware1, 'middleware1');
        $stack->push($middleware2, 'middleware2');
        $stack->remove('middleware1');
        
        $debug = $stack->debug();
        $this->assertStringNotContainsString('middleware1', $debug);
        $this->assertStringContainsString('[1] middleware2', $debug);
    }

    public function testCreateDefaultStack()
    {
        $stack = HandlerStack::create();
        $debug = $stack->debug();
        
        $this->assertStringContainsString('prepare_body', $debug);
        $this->assertStringContainsString('http_errors', $debug);
    }

    public function testInvokeWithMiddlewareChain()
    {
        // 创建模拟的底层处理器
        $handler = m::mock(HandlerInterface::class);
        $handler->shouldReceive('__invoke')
            ->once()
            ->andReturn(m::mock(PromiseInterface::class));
        
        $stack = new HandlerStack($handler);
        
        // 添加一个修改请求的中间件
        $middleware = function (callable $handler) {
            return function ($request, $options) use ($handler) {
                $request = $request->withHeader('X-Middleware', 'Applied');
                return $handler($request, $options);
            };
        };
        
        $stack->push($middleware);
        
        // 创建请求并调用处理器栈
        $request = new Request('GET', 'http://example.com');
        $result = $stack->__invoke($request, []);
        
        $this->assertInstanceOf(PromiseInterface::class, $result);
    }
}