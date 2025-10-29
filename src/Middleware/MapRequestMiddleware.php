<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;

/**
 * 请求映射中间件
 * 在发送请求前修改请求
 */
class MapRequestMiddleware
{
    private $fn;

    public function __construct(callable $fn)
    {
        $this->fn = $fn;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $fn = $this->fn;
            $request = $fn($request);

            return $handler($request, $options);
        };
    }
}

