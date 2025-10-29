<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 历史记录中间件
 * 记录所有请求和响应
 */
class HistoryMiddleware
{
    private array $container;

    public function __construct(array &$container)
    {
        $this->container = &$container;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) use ($request, $options) {
                    $this->container[] = [
                        'request' => $request,
                        'response' => $response,
                        'error' => null,
                        'options' => $options,
                    ];

                    return $response;
                },
                function (\Exception $reason) use ($request, $options) {
                    $this->container[] = [
                        'request' => $request,
                        'response' => null,
                        'error' => $reason,
                        'options' => $options,
                    ];

                    throw $reason;
                }
            );
        };
    }
}

