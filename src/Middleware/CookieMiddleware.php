<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cookie 中间件
 * 自动管理Cookie
 */
class CookieMiddleware
{
    private $cookieJar;

    public function __construct($cookieJar)
    {
        $this->cookieJar = $cookieJar;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            // 从 CookieJar 添加 Cookie 到请求
            if (method_exists($this->cookieJar, 'withCookieHeader')) {
                $request = $this->cookieJar->withCookieHeader($request);
            }

            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) use ($request) {
                    // 从响应提取 Cookie 到 CookieJar
                    if (method_exists($this->cookieJar, 'extractCookies')) {
                        $this->cookieJar->extractCookies($request, $response);
                    }

                    return $response;
                }
            );
        };
    }
}

