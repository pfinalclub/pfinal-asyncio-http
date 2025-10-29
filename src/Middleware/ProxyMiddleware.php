<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;

/**
 * 代理中间件
 * 配置HTTP代理
 */
class ProxyMiddleware
{
    private $proxy;

    public function __construct($proxy)
    {
        $this->proxy = $proxy;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $options['proxy'] = $this->proxy;
            return $handler($request, $options);
        };
    }
}

