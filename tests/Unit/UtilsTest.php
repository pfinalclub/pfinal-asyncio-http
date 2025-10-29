<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PFinal\AsyncioHttp\Utils;
use PFinal\AsyncioHttp\Psr7\Uri;

class UtilsTest extends TestCase
{
    public function testDefaultUserAgent(): void
    {
        $userAgent = Utils::defaultUserAgent();

        $this->assertStringContainsString('PFinal-AsyncIO-HTTP', $userAgent);
        $this->assertStringContainsString('PHP', $userAgent);
    }

    public function testNormalizeHeaderKeys(): void
    {
        $headers = [
            'content-type' => 'application/json',
            'ACCEPT' => 'application/json',
            'x-custom-header' => 'value',
        ];

        $normalized = Utils::normalizeHeaderKeys($headers);

        $this->assertArrayHasKey('Content-Type', $normalized);
        $this->assertArrayHasKey('Accept', $normalized);
        $this->assertArrayHasKey('X-Custom-Header', $normalized);
    }

    public function testIsHostInNoProxy(): void
    {
        $noProxy = ['localhost', '*.example.com', '.test.com'];

        $this->assertTrue(Utils::isHostInNoProxy('localhost', $noProxy));
        $this->assertTrue(Utils::isHostInNoProxy('api.example.com', $noProxy));
        $this->assertTrue(Utils::isHostInNoProxy('sub.test.com', $noProxy));
        $this->assertTrue(Utils::isHostInNoProxy('test.com', $noProxy));

        $this->assertFalse(Utils::isHostInNoProxy('example.org', $noProxy));
        $this->assertFalse(Utils::isHostInNoProxy('other.com', $noProxy));
    }

    public function testIsHostInNoProxyWildcard(): void
    {
        $noProxy = ['*'];

        $this->assertTrue(Utils::isHostInNoProxy('any.domain.com', $noProxy));
    }

    public function testJsonEncode(): void
    {
        $data = ['key' => 'value', 'number' => 123];
        $json = Utils::jsonEncode($data);

        $this->assertJson($json);
        $this->assertSame('{"key":"value","number":123}', $json);
    }

    public function testJsonEncodeThrowsOnError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON encode error');

        // 创建一个无法编码的资源
        $resource = fopen('php://memory', 'r');
        Utils::jsonEncode($resource);
        fclose($resource);
    }

    public function testJsonDecode(): void
    {
        $json = '{"key":"value","number":123}';
        $data = Utils::jsonDecode($json);

        $this->assertIsArray($data);
        $this->assertSame('value', $data['key']);
        $this->assertSame(123, $data['number']);
    }

    public function testJsonDecodeThrowsOnError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON decode error');

        Utils::jsonDecode('invalid json');
    }

    public function testUriTemplate(): void
    {
        $template = '/users/{id}/posts/{post_id}';
        $variables = ['id' => 123, 'post_id' => 456];

        $uri = Utils::uriTemplate($template, $variables);

        $this->assertSame('/users/123/posts/456', $uri);
    }

    public function testUriTemplateUrlEncodes(): void
    {
        $template = '/search/{query}';
        $variables = ['query' => 'hello world'];

        $uri = Utils::uriTemplate($template, $variables);

        $this->assertSame('/search/hello+world', $uri);
    }

    public function testDescribeType(): void
    {
        $this->assertStringContainsString('string', Utils::describeType('test'));
        $this->assertStringContainsString('integer', Utils::describeType(123));
        $this->assertStringContainsString('array', Utils::describeType([]));
        $this->assertStringContainsString('null', Utils::describeType(null));
        $this->assertStringContainsString('bool', Utils::describeType(true));
    }

    public function testParseContentType(): void
    {
        $result = Utils::parseContentType('text/html; charset=utf-8');

        $this->assertSame('text/html', $result['type']);
        $this->assertSame('utf-8', $result['charset']);
    }

    public function testParseContentTypeWithoutCharset(): void
    {
        $result = Utils::parseContentType('application/json');

        $this->assertSame('application/json', $result['type']);
        $this->assertArrayNotHasKey('charset', $result);
    }

    public function testFormatBytes(): void
    {
        $this->assertSame('0 B', Utils::formatBytes(0));
        $this->assertSame('1 KB', Utils::formatBytes(1024));
        $this->assertSame('1 MB', Utils::formatBytes(1024 * 1024));
        $this->assertSame('1 GB', Utils::formatBytes(1024 * 1024 * 1024));
    }

    public function testTruncate(): void
    {
        $str = 'This is a very long string that should be truncated';

        $truncated = Utils::truncate($str, 20);

        $this->assertSame('This is a very lo...', $truncated);
    }

    public function testTruncateShortString(): void
    {
        $str = 'Short';

        $truncated = Utils::truncate($str, 20);

        $this->assertSame('Short', $truncated);
    }

    public function testModifyQuery(): void
    {
        $uri = new Uri('http://example.com/path?foo=bar');

        $newUri = Utils::modifyQuery($uri, ['foo' => 'baz', 'new' => 'value']);

        $this->assertSame('foo=baz&new=value', $newUri->getQuery());
    }

    public function testMimetype(): void
    {
        $this->assertSame('text/plain', Utils::mimetype('file.txt'));
        $this->assertSame('text/html', Utils::mimetype('file.html'));
        $this->assertSame('application/json', Utils::mimetype('data.json'));
        $this->assertSame('image/jpeg', Utils::mimetype('photo.jpg'));
        $this->assertSame('image/png', Utils::mimetype('image.png'));
        $this->assertNull(Utils::mimetype('unknown.xyz'));
    }

    public function testCopyToString(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Hello, World!');
        rewind($resource);

        $string = Utils::copyToString($resource);

        $this->assertSame('Hello, World!', $string);

        fclose($resource);
    }

    public function testCopyToStringWithMaxLength(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Hello, World!');
        rewind($resource);

        $string = Utils::copyToString($resource, 5);

        $this->assertSame('Hello', $string);

        fclose($resource);
    }
}

