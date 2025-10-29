<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Cookie;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cookie 容器接口
 */
interface CookieJarInterface extends \Countable, \IteratorAggregate
{
    /**
     * 从响应中提取 Cookie
     */
    public function extractCookies(RequestInterface $request, ResponseInterface $response): void;

    /**
     * 添加 Cookie 头到请求
     */
    public function withCookieHeader(RequestInterface $request): RequestInterface;

    /**
     * 设置 Cookie
     */
    public function setCookie(SetCookie $cookie): void;

    /**
     * 获取所有 Cookie
     * 
     * @return SetCookie[]
     */
    public function getIterator(): \Traversable;

    /**
     * 清空所有 Cookie
     */
    public function clear(?string $domain = null, ?string $path = null, ?string $name = null): void;

    /**
     * 清空会话 Cookie
     */
    public function clearSessionCookies(): void;

    /**
     * 获取 Cookie 数量
     */
    public function count(): int;
}

