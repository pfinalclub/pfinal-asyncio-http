<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Tests\Unit\Psr7;

use PHPUnit\Framework\TestCase;
use PFinal\AsyncioHttp\Psr7\Request;
use PFinal\AsyncioHttp\Psr7\Uri;
use PFinal\AsyncioHttp\Psr7\Stream;

class RequestTest extends TestCase
{
    public function testConstructor(): void
    {
        $request = new Request('GET', '/path');

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/path', (string)$request->getUri());
    }

    public function testConstructorWithUriObject(): void
    {
        $uri = new Uri('http://example.com/path');
        $request = new Request('GET', $uri);

        $this->assertSame($uri, $request->getUri());
    }

    public function testGetMethod(): void
    {
        $request = new Request('POST', '/');

        $this->assertSame('POST', $request->getMethod());
    }

    public function testWithMethod(): void
    {
        $request = new Request('GET', '/');
        $new = $request->withMethod('POST');

        $this->assertNotSame($request, $new);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('POST', $new->getMethod());
    }

    public function testGetUri(): void
    {
        $uri = new Uri('http://example.com/path');
        $request = new Request('GET', $uri);

        $this->assertSame($uri, $request->getUri());
    }

    public function testWithUri(): void
    {
        $uri1 = new Uri('http://example.com/path1');
        $uri2 = new Uri('http://example.com/path2');

        $request = new Request('GET', $uri1);
        $new = $request->withUri($uri2);

        $this->assertNotSame($request, $new);
        $this->assertSame($uri1, $request->getUri());
        $this->assertSame($uri2, $new->getUri());
    }

    public function testWithUriPreservesHost(): void
    {
        $request = new Request('GET', 'http://example.com', ['Host' => 'original.com']);
        $new = $request->withUri(new Uri('http://changed.com'));

        // Host 头应该被保留
        $this->assertSame('original.com', $new->getHeaderLine('Host'));
    }

    public function testWithUriUpdatesHostWhenNotPresent(): void
    {
        $request = new Request('GET', 'http://example.com');
        $new = $request->withUri(new Uri('http://changed.com'));

        // Host 头应该更新
        $this->assertSame('changed.com', $new->getHeaderLine('Host'));
    }

    public function testGetRequestTarget(): void
    {
        $request = new Request('GET', 'http://example.com/path?query=value');

        $this->assertSame('/path?query=value', $request->getRequestTarget());
    }

    public function testGetRequestTargetDefault(): void
    {
        $request = new Request('GET', 'http://example.com');

        $this->assertSame('/', $request->getRequestTarget());
    }

    public function testWithRequestTarget(): void
    {
        $request = new Request('GET', '/');
        $new = $request->withRequestTarget('/path?query=value');

        $this->assertNotSame($request, $new);
        $this->assertSame('/', $request->getRequestTarget());
        $this->assertSame('/path?query=value', $new->getRequestTarget());
    }

    public function testGetHeaders(): void
    {
        $request = new Request('GET', '/', [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        $headers = $request->getHeaders();

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('Accept', $headers);
    }

    public function testWithHeader(): void
    {
        $request = new Request('GET', '/');
        $new = $request->withHeader('X-Custom', 'value');

        $this->assertNotSame($request, $new);
        $this->assertFalse($request->hasHeader('X-Custom'));
        $this->assertTrue($new->hasHeader('X-Custom'));
        $this->assertSame('value', $new->getHeaderLine('X-Custom'));
    }

    public function testWithAddedHeader(): void
    {
        $request = new Request('GET', '/', ['X-Custom' => 'value1']);
        $new = $request->withAddedHeader('X-Custom', 'value2');

        $this->assertSame(['value1'], $request->getHeader('X-Custom'));
        $this->assertSame(['value1', 'value2'], $new->getHeader('X-Custom'));
    }

    public function testWithoutHeader(): void
    {
        $request = new Request('GET', '/', ['X-Custom' => 'value']);
        $new = $request->withoutHeader('X-Custom');

        $this->assertTrue($request->hasHeader('X-Custom'));
        $this->assertFalse($new->hasHeader('X-Custom'));
    }

    public function testGetBody(): void
    {
        $body = Stream::create('Hello, World!');
        $request = new Request('POST', '/', [], $body);

        $this->assertSame($body, $request->getBody());
    }

    public function testWithBody(): void
    {
        $body1 = Stream::create('Body 1');
        $body2 = Stream::create('Body 2');

        $request = new Request('POST', '/', [], $body1);
        $new = $request->withBody($body2);

        $this->assertNotSame($request, $new);
        $this->assertSame($body1, $request->getBody());
        $this->assertSame($body2, $new->getBody());
    }

    public function testGetProtocolVersion(): void
    {
        $request = new Request('GET', '/');

        $this->assertSame('1.1', $request->getProtocolVersion());
    }

    public function testWithProtocolVersion(): void
    {
        $request = new Request('GET', '/');
        $new = $request->withProtocolVersion('2.0');

        $this->assertNotSame($request, $new);
        $this->assertSame('1.1', $request->getProtocolVersion());
        $this->assertSame('2.0', $new->getProtocolVersion());
    }

    public function testMethodCaseInsensitive(): void
    {
        $request = new Request('get', '/');

        $this->assertSame('GET', $request->getMethod());
    }

    public function testHostHeaderFromUri(): void
    {
        $request = new Request('GET', 'http://example.com:8080/path');

        $this->assertTrue($request->hasHeader('Host'));
        $this->assertSame('example.com:8080', $request->getHeaderLine('Host'));
    }
}

