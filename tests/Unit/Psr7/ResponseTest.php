<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Tests\Unit\Psr7;

use PHPUnit\Framework\TestCase;
use PFinal\AsyncioHttp\Psr7\Response;
use PFinal\AsyncioHttp\Psr7\Stream;

class ResponseTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $response = new Response();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getReasonPhrase());
        $this->assertSame('1.1', $response->getProtocolVersion());
    }

    public function testConstructorWithStatusCode(): void
    {
        $response = new Response(404);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testConstructorWithCustomReasonPhrase(): void
    {
        $response = new Response(200, [], null, '1.1', 'Custom Reason');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Custom Reason', $response->getReasonPhrase());
    }

    public function testGetStatusCode(): void
    {
        $response = new Response(201);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testWithStatus(): void
    {
        $response = new Response(200);
        $new = $response->withStatus(404);

        $this->assertNotSame($response, $new);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(404, $new->getStatusCode());
        $this->assertSame('Not Found', $new->getReasonPhrase());
    }

    public function testWithStatusAndCustomReason(): void
    {
        $response = new Response();
        $new = $response->withStatus(200, 'Custom OK');

        $this->assertSame('Custom OK', $new->getReasonPhrase());
    }

    public function testGetReasonPhrase(): void
    {
        $response = new Response(404);

        $this->assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testStandardStatusCodes(): void
    {
        $codes = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
        ];

        foreach ($codes as $code => $phrase) {
            $response = new Response($code);
            $this->assertSame($phrase, $response->getReasonPhrase());
        }
    }

    public function testWithHeader(): void
    {
        $response = new Response();
        $new = $response->withHeader('Content-Type', 'application/json');

        $this->assertNotSame($response, $new);
        $this->assertFalse($response->hasHeader('Content-Type'));
        $this->assertTrue($new->hasHeader('Content-Type'));
        $this->assertSame('application/json', $new->getHeaderLine('Content-Type'));
    }

    public function testWithAddedHeader(): void
    {
        $response = new Response(200, ['X-Custom' => 'value1']);
        $new = $response->withAddedHeader('X-Custom', 'value2');

        $this->assertSame(['value1'], $response->getHeader('X-Custom'));
        $this->assertSame(['value1', 'value2'], $new->getHeader('X-Custom'));
    }

    public function testWithoutHeader(): void
    {
        $response = new Response(200, ['X-Custom' => 'value']);
        $new = $response->withoutHeader('X-Custom');

        $this->assertTrue($response->hasHeader('X-Custom'));
        $this->assertFalse($new->hasHeader('X-Custom'));
    }

    public function testGetBody(): void
    {
        $body = Stream::create('Response body');
        $response = new Response(200, [], $body);

        $this->assertSame($body, $response->getBody());
    }

    public function testWithBody(): void
    {
        $body1 = Stream::create('Body 1');
        $body2 = Stream::create('Body 2');

        $response = new Response(200, [], $body1);
        $new = $response->withBody($body2);

        $this->assertNotSame($response, $new);
        $this->assertSame($body1, $response->getBody());
        $this->assertSame($body2, $new->getBody());
    }

    public function testGetProtocolVersion(): void
    {
        $response = new Response();

        $this->assertSame('1.1', $response->getProtocolVersion());
    }

    public function testWithProtocolVersion(): void
    {
        $response = new Response();
        $new = $response->withProtocolVersion('2.0');

        $this->assertNotSame($response, $new);
        $this->assertSame('1.1', $response->getProtocolVersion());
        $this->assertSame('2.0', $new->getProtocolVersion());
    }

    public function testImmutability(): void
    {
        $response = new Response(200, ['X-Test' => 'value']);

        $new1 = $response->withStatus(404);
        $new2 = $response->withHeader('X-Test', 'new-value');
        $new3 = $response->withProtocolVersion('2.0');

        $this->assertNotSame($response, $new1);
        $this->assertNotSame($response, $new2);
        $this->assertNotSame($response, $new3);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('value', $response->getHeaderLine('X-Test'));
        $this->assertSame('1.1', $response->getProtocolVersion());
    }
}

