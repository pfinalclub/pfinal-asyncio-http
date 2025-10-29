<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Tests\Unit\Cookie;

use PHPUnit\Framework\TestCase;
use PFinal\AsyncioHttp\Cookie\CookieJar;
use PFinal\AsyncioHttp\Cookie\SetCookie;
use PFinal\AsyncioHttp\Psr7\Request;
use PFinal\AsyncioHttp\Psr7\Response;

class CookieJarTest extends TestCase
{
    public function testConstructor(): void
    {
        $jar = new CookieJar();

        $this->assertCount(0, $jar);
    }

    public function testSetCookie(): void
    {
        $jar = new CookieJar();
        $cookie = new SetCookie(['Name' => 'test', 'Value' => 'value', 'Domain' => 'example.com']);

        $jar->setCookie($cookie);

        $this->assertCount(1, $jar);
    }

    public function testSetCookieOverwrite(): void
    {
        $jar = new CookieJar();
        $cookie1 = new SetCookie(['Name' => 'test', 'Value' => 'value1', 'Domain' => 'example.com']);
        $cookie2 = new SetCookie(['Name' => 'test', 'Value' => 'value2', 'Domain' => 'example.com']);

        $jar->setCookie($cookie1);
        $jar->setCookie($cookie2);

        // 应该只有一个 Cookie（被覆盖）
        $this->assertCount(1, $jar);

        foreach ($jar as $cookie) {
            $this->assertSame('value2', $cookie->getValue());
        }
    }

    public function testExtractCookies(): void
    {
        $jar = new CookieJar();
        $request = new Request('GET', 'http://example.com/path');
        $response = new Response(200, [
            'Set-Cookie' => [
                'test1=value1; Domain=example.com; Path=/',
                'test2=value2; Domain=example.com; Path=/path',
            ],
        ]);

        $jar->extractCookies($request, $response);

        $this->assertCount(2, $jar);
    }

    public function testWithCookieHeader(): void
    {
        $jar = new CookieJar();
        $cookie1 = new SetCookie(['Name' => 'test1', 'Value' => 'value1', 'Domain' => 'example.com', 'Path' => '/']);
        $cookie2 = new SetCookie(['Name' => 'test2', 'Value' => 'value2', 'Domain' => 'example.com', 'Path' => '/']);

        $jar->setCookie($cookie1);
        $jar->setCookie($cookie2);

        $request = new Request('GET', 'http://example.com/path');
        $newRequest = $jar->withCookieHeader($request);

        $this->assertTrue($newRequest->hasHeader('Cookie'));
        $cookieHeader = $newRequest->getHeaderLine('Cookie');

        $this->assertStringContainsString('test1=value1', $cookieHeader);
        $this->assertStringContainsString('test2=value2', $cookieHeader);
    }

    public function testWithCookieHeaderFiltersByDomain(): void
    {
        $jar = new CookieJar();
        $cookie1 = new SetCookie(['Name' => 'test1', 'Value' => 'value1', 'Domain' => 'example.com']);
        $cookie2 = new SetCookie(['Name' => 'test2', 'Value' => 'value2', 'Domain' => 'other.com']);

        $jar->setCookie($cookie1);
        $jar->setCookie($cookie2);

        $request = new Request('GET', 'http://example.com/');
        $newRequest = $jar->withCookieHeader($request);

        $cookieHeader = $newRequest->getHeaderLine('Cookie');

        $this->assertStringContainsString('test1=value1', $cookieHeader);
        $this->assertStringNotContainsString('test2=value2', $cookieHeader);
    }

    public function testWithCookieHeaderFiltersByPath(): void
    {
        $jar = new CookieJar();
        $cookie1 = new SetCookie(['Name' => 'test1', 'Value' => 'value1', 'Domain' => 'example.com', 'Path' => '/']);
        $cookie2 = new SetCookie(['Name' => 'test2', 'Value' => 'value2', 'Domain' => 'example.com', 'Path' => '/admin']);

        $jar->setCookie($cookie1);
        $jar->setCookie($cookie2);

        // 请求 / 路径，应该只包含 cookie1
        $request = new Request('GET', 'http://example.com/');
        $newRequest = $jar->withCookieHeader($request);
        $cookieHeader = $newRequest->getHeaderLine('Cookie');

        $this->assertStringContainsString('test1=value1', $cookieHeader);
        $this->assertStringNotContainsString('test2=value2', $cookieHeader);

        // 请求 /admin 路径，应该包含两个 Cookie
        $request = new Request('GET', 'http://example.com/admin');
        $newRequest = $jar->withCookieHeader($request);
        $cookieHeader = $newRequest->getHeaderLine('Cookie');

        $this->assertStringContainsString('test1=value1', $cookieHeader);
        $this->assertStringContainsString('test2=value2', $cookieHeader);
    }

    public function testClear(): void
    {
        $jar = new CookieJar();
        $cookie1 = new SetCookie(['Name' => 'test1', 'Value' => 'value1', 'Domain' => 'example.com']);
        $cookie2 = new SetCookie(['Name' => 'test2', 'Value' => 'value2', 'Domain' => 'example.com']);

        $jar->setCookie($cookie1);
        $jar->setCookie($cookie2);

        $this->assertCount(2, $jar);

        $jar->clear();

        $this->assertCount(0, $jar);
    }

    public function testClearByDomain(): void
    {
        $jar = new CookieJar();
        $cookie1 = new SetCookie(['Name' => 'test1', 'Value' => 'value1', 'Domain' => 'example.com']);
        $cookie2 = new SetCookie(['Name' => 'test2', 'Value' => 'value2', 'Domain' => 'other.com']);

        $jar->setCookie($cookie1);
        $jar->setCookie($cookie2);

        $jar->clear('example.com');

        $this->assertCount(1, $jar);

        foreach ($jar as $cookie) {
            $this->assertSame('other.com', $cookie->getDomain());
        }
    }

    public function testClearSessionCookies(): void
    {
        $jar = new CookieJar();
        $session = new SetCookie(['Name' => 'session', 'Value' => 'abc']);
        $persistent = new SetCookie(['Name' => 'persistent', 'Value' => 'xyz', 'Expires' => time() + 3600]);

        $jar->setCookie($session);
        $jar->setCookie($persistent);

        $this->assertCount(2, $jar);

        $jar->clearSessionCookies();

        $this->assertCount(1, $jar);

        foreach ($jar as $cookie) {
            $this->assertSame('persistent', $cookie->getName());
        }
    }

    public function testToArray(): void
    {
        $jar = new CookieJar();
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
            'Domain' => 'example.com',
            'Path' => '/path',
        ]);

        $jar->setCookie($cookie);

        $array = $jar->toArray();

        $this->assertIsArray($array);
        $this->assertCount(1, $array);
        $this->assertSame('test', $array[0]['Name']);
        $this->assertSame('value', $array[0]['Value']);
    }

    public function testFromArray(): void
    {
        $data = [
            [
                'Name' => 'test',
                'Value' => 'value',
                'Domain' => 'example.com',
                'Path' => '/path',
            ],
        ];

        $jar = CookieJar::fromArray($data);

        $this->assertCount(1, $jar);

        foreach ($jar as $cookie) {
            $this->assertSame('test', $cookie->getName());
            $this->assertSame('value', $cookie->getValue());
        }
    }

    public function testIterator(): void
    {
        $jar = new CookieJar();
        $cookie1 = new SetCookie(['Name' => 'test1', 'Value' => 'value1', 'Domain' => 'example.com']);
        $cookie2 = new SetCookie(['Name' => 'test2', 'Value' => 'value2', 'Domain' => 'example.com']);

        $jar->setCookie($cookie1);
        $jar->setCookie($cookie2);

        $count = 0;
        foreach ($jar as $cookie) {
            $this->assertInstanceOf(SetCookie::class, $cookie);
            $count++;
        }

        $this->assertSame(2, $count);
    }

    public function testRemoveExpiredCookies(): void
    {
        $jar = new CookieJar();
        $valid = new SetCookie(['Name' => 'valid', 'Value' => 'value', 'Domain' => 'example.com']);
        $expired = new SetCookie(['Name' => 'expired', 'Value' => 'value', 'Domain' => 'example.com', 'Expires' => time() - 3600]);

        $jar->setCookie($valid);
        $jar->setCookie($expired);

        // 过期的 Cookie 在设置时会被自动删除
        $this->assertCount(1, $jar);
    }
}

