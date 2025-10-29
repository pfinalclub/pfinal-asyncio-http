<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\ResponseInterface;

/**
 * 响应映射中间件
 * 在返回响应前修改响应
 */
class MapResponseMiddleware
{
    private $fn;

    public function __construct(callable $fn)
    {
        $this->fn = $fn;
    }

    public function __invoke(callable $handler): callable
    {
        return function ($request, array $options) use ($handler) {
            $promise = $handler($request, $options);

            return $promise->then(function (ResponseInterface $response) {
                $fn = $this->fn;
                return $fn($response);
            });
        };
    }
}

