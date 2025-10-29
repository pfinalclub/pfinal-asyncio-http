<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use PFinal\AsyncioHttp\Middleware\PrepareBodyMiddleware;
use PFinal\AsyncioHttp\Psr7\Request;
use PFinal\AsyncioHttp\Psr7\Stream;

class PrepareBodyMiddlewareTest extends TestCase
{
    public function testInvokeReturnsCallable()
    {
        $middleware = new PrepareBodyMiddleware();
        $handler = function ($request, $options) {
            return $request;
        };
        
        $result = $middleware($handler);
        
        $this->assertIsCallable($result);
    }

    public function testWithExistingBody()
    {
        $middleware = new PrepareBodyMiddleware();
        
        // 创建带有body的请求
        $body = new Stream(fopen('php://temp', 'r+'));
        $body->write('test content');
        $body->rewind();
        
        $request = new Request('POST', 'http://example.com', [], $body);
        
        // 创建简单的处理器
        $handler = function ($request, $options) {
            return $request;
        };
        
        $processedHandler = $middleware($handler);
        $result = $processedHandler($request, []);
        
        // 验证原始body未被修改
        $this->assertEquals('test content', (string) $result->getBody());
    }

    public function testWithJsonOption()
    {
        $middleware = new PrepareBodyMiddleware();
        
        $request = new Request('POST', 'http://example.com');
        $options = [
            'json' => ['foo' => 'bar', 'test' => 123],
        ];
        
        $handler = function ($request, $options) {
            return [$request, $options];
        };
        
        $processedHandler = $middleware($handler);
        [$resultRequest, $resultOptions] = $processedHandler($request, $options);
        
        // 验证Content-Type头被设置
        $this->assertEquals('application/json', $resultRequest->getHeaderLine('Content-Type'));
        
        // 验证body包含JSON数据
        $expectedJson = json_encode(['foo' => 'bar', 'test' => 123]);
        $this->assertEquals($expectedJson, (string) $resultRequest->getBody());
        
        // 验证json选项被移除
        $this->assertFalse(isset($resultOptions['json']));
    }

    public function testWithFormParamsOption()
    {
        $middleware = new PrepareBodyMiddleware();
        
        $request = new Request('POST', 'http://example.com');
        $options = [
            'form_params' => ['foo' => 'bar', 'test' => 123],
        ];
        
        $handler = function ($request, $options) {
            return [$request, $options];
        };
        
        $processedHandler = $middleware($handler);
        [$resultRequest, $resultOptions] = $processedHandler($request, $options);
        
        // 验证Content-Type头被设置
        $this->assertEquals('application/x-www-form-urlencoded', $resultRequest->getHeaderLine('Content-Type'));
        
        // 验证body包含表单数据
        $expectedForm = 'foo=bar&test=123';
        $this->assertEquals($expectedForm, (string) $resultRequest->getBody());
        
        // 验证form_params选项被移除
        $this->assertFalse(isset($resultOptions['form_params']));
    }

    public function testWithStringBodyOption()
    {
        $middleware = new PrepareBodyMiddleware();
        
        $request = new Request('POST', 'http://example.com');
        $options = [
            'body' => 'test string body',
        ];
        
        $handler = function ($request, $options) {
            return [$request, $options];
        };
        
        $processedHandler = $middleware($handler);
        [$resultRequest, $resultOptions] = $processedHandler($request, $options);
        
        // 验证body包含字符串数据
        $this->assertEquals('test string body', (string) $resultRequest->getBody());
        
        // 验证body选项被移除
        $this->assertFalse(isset($resultOptions['body']));
    }

    public function testWithResourceBodyOption()
    {
        $middleware = new PrepareBodyMiddleware();
        
        $request = new Request('POST', 'http://example.com');
        
        // 创建临时文件资源
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'resource content');
        rewind($resource);
        
        $options = [
            'body' => $resource,
        ];
        
        $handler = function ($request, $options) {
            return $request;
        };
        
        $processedHandler = $middleware($handler);
        $resultRequest = $processedHandler($request, $options);
        
        // 验证body包含资源内容
        $this->assertEquals('resource content', (string) $resultRequest->getBody());
    }

    public function testWithStreamBodyOption()
    {
        $middleware = new PrepareBodyMiddleware();
        
        $request = new Request('POST', 'http://example.com');
        
        // 创建Stream对象
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write('stream content');
        $stream->rewind();
        
        $options = [
            'body' => $stream,
        ];
        
        $handler = function ($request, $options) {
            return $request;
        };
        
        $processedHandler = $middleware($handler);
        $resultRequest = $processedHandler($request, $options);
        
        // 验证body包含流内容
        $this->assertEquals('stream content', (string) $resultRequest->getBody());
    }

    public function testWithInvalidJson()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $middleware = new PrepareBodyMiddleware();
        
        $request = new Request('POST', 'http://example.com');
        
        // 创建一个无法JSON编码的值
        $options = [
            'json' => [new \stdClass()], // 循环引用，无法JSON编码
        ];
        
        $handler = function ($request, $options) {
            return $request;
        };
        
        $processedHandler = $middleware($handler);
        $processedHandler($request, $options);
    }
}