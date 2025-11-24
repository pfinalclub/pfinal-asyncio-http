<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use PFinal\AsyncioHttp\Cookie\CookieJarInterface;
use PFinal\AsyncioHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cookie 中间件
 * 自动管理请求和响应中的 Cookie
 */
class CookieMiddleware
{
    private CookieJarInterface $cookieJar;

    public function __construct(CookieJarInterface $cookieJar)
    {
        $this->cookieJar = $cookieJar;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            // 从选项中获取 Cookie Jar
            $cookieJar = $options[RequestOptions::COOKIES] ?? $this->cookieJar;

            if ($cookieJar instanceof CookieJarInterface) {
                // 添加 Cookie 到请求
                $request = $cookieJar->withCookieHeader($request);

                // 执行请求
                $response = $handler($request, $options);

                // 从响应中提取 Cookie
                $cookieJar->extractCookies($request, $response);

                    return $response;
                }

            return $handler($request, $options);
        };
    }
}
