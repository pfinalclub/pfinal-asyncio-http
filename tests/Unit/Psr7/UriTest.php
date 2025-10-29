<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Tests\Unit\Psr7;

use PHPUnit\Framework\TestCase;
use PFinal\AsyncioHttp\Psr7\Uri;

class UriTest extends TestCase
{
    public function testConstructorParsesUri(): void
    {
        $uri = new Uri('https://user:pass@example.com:8080/path?query=value#fragment');

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('user:pass', $uri->getUserInfo());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame(8080, $uri->getPort());
        $this->assertSame('/path', $uri->getPath());
        $this->assertSame('query=value', $uri->getQuery());
        $this->assertSame('fragment', $uri->getFragment());
    }

    public function testEmptyUri(): void
    {
        $uri = new Uri('');

        $this->assertSame('', $uri->getScheme());
        $this->assertSame('', $uri->getUserInfo());
        $this->assertSame('', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertSame('', $uri->getPath());
        $this->assertSame('', $uri->getQuery());
        $this->assertSame('', $uri->getFragment());
    }

    public function testWithScheme(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withScheme('https');

        $this->assertNotSame($uri, $new);
        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('https', $new->getScheme());
    }

    public function testWithUserInfo(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withUserInfo('user', 'pass');

        $this->assertNotSame($uri, $new);
        $this->assertSame('', $uri->getUserInfo());
        $this->assertSame('user:pass', $new->getUserInfo());
    }

    public function testWithUserInfoWithoutPassword(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withUserInfo('user');

        $this->assertSame('user', $new->getUserInfo());
    }

    public function testWithHost(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withHost('example.org');

        $this->assertNotSame($uri, $new);
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('example.org', $new->getHost());
    }

    public function testWithPort(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withPort(8080);

        $this->assertNotSame($uri, $new);
        $this->assertNull($uri->getPort());
        $this->assertSame(8080, $new->getPort());
    }

    public function testWithPortNull(): void
    {
        $uri = new Uri('http://example.com:8080');
        $new = $uri->withPort(null);

        $this->assertSame(8080, $uri->getPort());
        $this->assertNull($new->getPort());
    }

    public function testWithPath(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withPath('/path');

        $this->assertNotSame($uri, $new);
        $this->assertSame('', $uri->getPath());
        $this->assertSame('/path', $new->getPath());
    }

    public function testWithQuery(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withQuery('foo=bar');

        $this->assertNotSame($uri, $new);
        $this->assertSame('', $uri->getQuery());
        $this->assertSame('foo=bar', $new->getQuery());
    }

    public function testWithFragment(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withFragment('section');

        $this->assertNotSame($uri, $new);
        $this->assertSame('', $uri->getFragment());
        $this->assertSame('section', $new->getFragment());
    }

    public function testGetAuthority(): void
    {
        $uri = new Uri('http://user:pass@example.com:8080/path');
        $this->assertSame('user:pass@example.com:8080', $uri->getAuthority());

        $uri = new Uri('http://example.com/path');
        $this->assertSame('example.com', $uri->getAuthority());

        $uri = new Uri('http://example.com:8080/path');
        $this->assertSame('example.com:8080', $uri->getAuthority());

        $uri = new Uri('/path');
        $this->assertSame('', $uri->getAuthority());
    }

    public function testToString(): void
    {
        $uri = 'https://user:pass@example.com:8080/path?query=value#fragment';
        $this->assertSame($uri, (string)(new Uri($uri)));
    }

    public function testDefaultPorts(): void
    {
        $uri = new Uri('http://example.com:80');
        $this->assertNull($uri->getPort());

        $uri = new Uri('https://example.com:443');
        $this->assertNull($uri->getPort());

        $uri = new Uri('http://example.com:8080');
        $this->assertSame(8080, $uri->getPort());
    }

    public function testImmutability(): void
    {
        $uri = new Uri('http://example.com');

        $new1 = $uri->withScheme('https');
        $new2 = $uri->withHost('example.org');

        $this->assertNotSame($uri, $new1);
        $this->assertNotSame($uri, $new2);
        $this->assertNotSame($new1, $new2);

        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('example.com', $uri->getHost());
    }

    public function testCaseNormalization(): void
    {
        $uri = new Uri('HTTP://EXAMPLE.COM/PATH');
        
        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('/PATH', $uri->getPath()); // Path 保持大小写
    }
}

