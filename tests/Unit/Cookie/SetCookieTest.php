<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Tests\Unit\Cookie;

use PHPUnit\Framework\TestCase;
use PFinal\AsyncioHttp\Cookie\SetCookie;

class SetCookieTest extends TestCase
{
    public function testConstructor(): void
    {
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
            'Domain' => 'example.com',
            'Path' => '/path',
        ]);

        $this->assertSame('test', $cookie->getName());
        $this->assertSame('value', $cookie->getValue());
        $this->assertSame('example.com', $cookie->getDomain());
        $this->assertSame('/path', $cookie->getPath());
    }

    public function testFromString(): void
    {
        $cookie = SetCookie::fromString('name=value; Domain=example.com; Path=/; Secure; HttpOnly');

        $this->assertSame('name', $cookie->getName());
        $this->assertSame('value', $cookie->getValue());
        $this->assertSame('example.com', $cookie->getDomain());
        $this->assertSame('/', $cookie->getPath());
        $this->assertTrue($cookie->getSecure());
        $this->assertTrue($cookie->getHttpOnly());
    }

    public function testFromStringWithExpires(): void
    {
        $expires = 'Wed, 21 Oct 2025 07:28:00 GMT';
        $cookie = SetCookie::fromString("name=value; Expires={$expires}");

        $this->assertIsInt($cookie->getExpires());
        $this->assertGreaterThan(0, $cookie->getExpires());
    }

    public function testFromStringWithMaxAge(): void
    {
        $cookie = SetCookie::fromString('name=value; Max-Age=3600');

        $this->assertSame(3600, $cookie->getMaxAge());
    }

    public function testFromStringWithSameSite(): void
    {
        $cookie = SetCookie::fromString('name=value; SameSite=Lax');

        $this->assertSame('Lax', $cookie->getSameSite());
    }

    public function testIsExpired(): void
    {
        // 未过期的 Cookie
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
            'Expires' => time() + 3600,
        ]);
        $this->assertFalse($cookie->isExpired());

        // 已过期的 Cookie
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
            'Expires' => time() - 3600,
        ]);
        $this->assertTrue($cookie->isExpired());

        // 会话 Cookie（永不过期）
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
        ]);
        $this->assertFalse($cookie->isExpired());
    }

    public function testIsSession(): void
    {
        // 会话 Cookie
        $cookie = new SetCookie(['Name' => 'test', 'Value' => 'value']);
        $this->assertTrue($cookie->isSession());

        // 持久化 Cookie (Expires)
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
            'Expires' => time() + 3600,
        ]);
        $this->assertFalse($cookie->isSession());

        // 持久化 Cookie (Max-Age)
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
            'Max-Age' => 3600,
        ]);
        $this->assertFalse($cookie->isSession());
    }

    public function testMatchesDomain(): void
    {
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
            'Domain' => 'example.com',
        ]);

        // 精确匹配
        $this->assertTrue($cookie->matchesDomain('example.com'));

        // 子域名匹配
        $this->assertTrue($cookie->matchesDomain('www.example.com'));
        $this->assertTrue($cookie->matchesDomain('api.example.com'));

        // 不匹配
        $this->assertFalse($cookie->matchesDomain('other.com'));
        $this->assertFalse($cookie->matchesDomain('example.org'));
    }

    public function testMatchesPath(): void
    {
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
            'Path' => '/path',
        ]);

        // 精确匹配
        $this->assertTrue($cookie->matchesPath('/path'));

        // 前缀匹配
        $this->assertTrue($cookie->matchesPath('/path/subpath'));

        // 不匹配
        $this->assertFalse($cookie->matchesPath('/other'));
        $this->assertFalse($cookie->matchesPath('/pat'));
    }

    public function testShouldSend(): void
    {
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
            'Domain' => 'example.com',
            'Path' => '/path',
            'Secure' => false,
        ]);

        // 应该发送
        $this->assertTrue($cookie->shouldSend('example.com', '/path', false));
        $this->assertTrue($cookie->shouldSend('www.example.com', '/path/sub', false));

        // 不应该发送（域名不匹配）
        $this->assertFalse($cookie->shouldSend('other.com', '/path', false));

        // 不应该发送（路径不匹配）
        $this->assertFalse($cookie->shouldSend('example.com', '/other', false));
    }

    public function testShouldSendSecure(): void
    {
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
            'Domain' => 'example.com',
            'Secure' => true,
        ]);

        // Secure Cookie 只能通过 HTTPS 发送
        $this->assertTrue($cookie->shouldSend('example.com', '/', true));
        $this->assertFalse($cookie->shouldSend('example.com', '/', false));
    }

    public function testShouldSendExpired(): void
    {
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
            'Domain' => 'example.com',
            'Expires' => time() - 3600, // 已过期
        ]);

        // 过期的 Cookie 不应该发送
        $this->assertFalse($cookie->shouldSend('example.com', '/', false));
    }

    public function testToString(): void
    {
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
        ]);

        $this->assertSame('test=value', $cookie->toString());
        $this->assertSame('test=value', (string)$cookie);
    }

    public function testToSetCookieString(): void
    {
        $cookie = new SetCookie([
            'Name' => 'test',
            'Value' => 'value',
            'Domain' => 'example.com',
            'Path' => '/path',
            'Secure' => true,
            'HttpOnly' => true,
            'SameSite' => 'Lax',
        ]);

        $str = $cookie->toSetCookieString();

        $this->assertStringContainsString('test=value', $str);
        $this->assertStringContainsString('Domain=example.com', $str);
        $this->assertStringContainsString('Path=/path', $str);
        $this->assertStringContainsString('Secure', $str);
        $this->assertStringContainsString('HttpOnly', $str);
        $this->assertStringContainsString('SameSite=Lax', $str);
    }

    public function testWith(): void
    {
        $cookie = new SetCookie(['Name' => 'test', 'Value' => 'value']);
        $new = $cookie->with('Domain', 'example.com');

        $this->assertNotSame($cookie, $new);
        $this->assertNull($cookie->getDomain());
        $this->assertSame('example.com', $new->getDomain());
    }
}

