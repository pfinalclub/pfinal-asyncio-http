<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Cookie;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cookie 容器
 * 内存中管理 Cookie
 */
class CookieJar implements CookieJarInterface
{
    /** @var SetCookie[] */
    private array $cookies = [];

    private bool $strictMode = false;

    public function __construct(bool $strictMode = false, array $cookieArray = [])
    {
        $this->strictMode = $strictMode;

        foreach ($cookieArray as $cookie) {
            $this->setCookie($cookie);
        }
    }

    /**
     * 从响应中提取 Cookie
     */
    public function extractCookies(RequestInterface $request, ResponseInterface $response): void
    {
        $uri = $request->getUri();
        $domain = $uri->getHost();
        $path = $uri->getPath() ?: '/';

        // 获取所有 Set-Cookie 头
        $setCookieHeaders = $response->getHeader('Set-Cookie');

        foreach ($setCookieHeaders as $cookieHeader) {
            $cookie = SetCookie::fromString($cookieHeader);

            // 如果 Cookie 没有指定域名，使用请求的域名
            if ($cookie->getDomain() === null) {
                $cookie = $cookie->with('Domain', $domain);
            }

            // 如果 Cookie 没有指定路径，使用请求的路径
            if ($cookie->getPath() === '/') {
                $defaultPath = $this->getCookiePath($path);
                $cookie = $cookie->with('Path', $defaultPath);
            }

            $this->setCookie($cookie);
        }
    }

    /**
     * 获取 Cookie 的默认路径
     */
    private function getCookiePath(string $uriPath): string
    {
        if ($uriPath === '' || $uriPath[0] !== '/') {
            return '/';
        }

        // 如果路径只有根路径
        if ($uriPath === '/') {
            return '/';
        }

        // 移除最后一个路径段
        $lastSlash = strrpos($uriPath, '/');
        if ($lastSlash === 0) {
            return '/';
        }

        return substr($uriPath, 0, $lastSlash);
    }

    /**
     * 添加 Cookie 头到请求
     */
    public function withCookieHeader(RequestInterface $request): RequestInterface
    {
        $uri = $request->getUri();
        $domain = $uri->getHost();
        $path = $uri->getPath() ?: '/';
        $secure = $uri->getScheme() === 'https';

        $cookies = $this->getMatchingCookies($domain, $path, $secure);

        if (empty($cookies)) {
            return $request;
        }

        $cookieString = implode('; ', array_map(
            fn($cookie) => $cookie->toString(),
            $cookies
        ));

        return $request->withHeader('Cookie', $cookieString);
    }

    /**
     * 获取匹配的 Cookie
     * 
     * @return SetCookie[]
     */
    private function getMatchingCookies(string $domain, string $path, bool $secure): array
    {
        $matching = [];

        foreach ($this->cookies as $cookie) {
            if ($cookie->shouldSend($domain, $path, $secure)) {
                $matching[] = $cookie;
            }
        }

        // 按路径长度排序（更具体的路径优先）
        usort($matching, function ($a, $b) {
            return strlen($b->getPath()) <=> strlen($a->getPath());
        });

        return $matching;
    }

    /**
     * 设置 Cookie
     */
    public function setCookie(SetCookie $cookie): void
    {
        // 生成 Cookie 的唯一键
        $key = $this->getCookieKey($cookie);

        // 如果 Cookie 已过期或值为空，删除它
        if ($cookie->isExpired() || $cookie->getValue() === null || $cookie->getValue() === '') {
            unset($this->cookies[$key]);
            return;
        }

        $this->cookies[$key] = $cookie;
    }

    /**
     * 获取 Cookie 的唯一键
     */
    private function getCookieKey(SetCookie $cookie): string
    {
        return sprintf(
            '%s;%s;%s',
            $cookie->getDomain() ?: '',
            $cookie->getPath(),
            $cookie->getName()
        );
    }

    /**
     * 获取所有 Cookie
     * 
     * @return \Traversable<SetCookie>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(array_values($this->cookies));
    }

    /**
     * 清空 Cookie
     */
    public function clear(?string $domain = null, ?string $path = null, ?string $name = null): void
    {
        if ($domain === null && $path === null && $name === null) {
            $this->cookies = [];
            return;
        }

        $this->cookies = array_filter(
            $this->cookies,
            function ($cookie) use ($domain, $path, $name) {
                if ($domain !== null && $cookie->getDomain() !== $domain) {
                    return true;
                }

                if ($path !== null && $cookie->getPath() !== $path) {
                    return true;
                }

                if ($name !== null && $cookie->getName() !== $name) {
                    return true;
                }

                return false;
            }
        );
    }

    /**
     * 清空会话 Cookie
     */
    public function clearSessionCookies(): void
    {
        $this->cookies = array_filter(
            $this->cookies,
            fn($cookie) => !$cookie->isSession()
        );
    }

    /**
     * 获取 Cookie 数量
     */
    public function count(): int
    {
        return count($this->cookies);
    }

    /**
     * 转换为数组（用于序列化）
     */
    public function toArray(): array
    {
        return array_map(
            fn($cookie) => [
                'Name' => $cookie->getName(),
                'Value' => $cookie->getValue(),
                'Domain' => $cookie->getDomain(),
                'Path' => $cookie->getPath(),
                'Expires' => $cookie->getExpires(),
                'Max-Age' => $cookie->getMaxAge(),
                'Secure' => $cookie->getSecure(),
                'HttpOnly' => $cookie->getHttpOnly(),
                'SameSite' => $cookie->getSameSite(),
            ],
            array_values($this->cookies)
        );
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $cookies): self
    {
        $jar = new self();

        foreach ($cookies as $cookieData) {
            $jar->setCookie(new SetCookie($cookieData));
        }

        return $jar;
    }
}

