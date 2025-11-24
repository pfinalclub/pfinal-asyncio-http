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
            try {
                $response = $handler($request, $options);
                
                $this->container[] = [
                    'request' => $request,
                    'response' => $response,
                    'error' => null,
                    'options' => $options,
                ];

                return $response;
            } catch (\Exception $e) {
                $this->container[] = [
                    'request' => $request,
                    'response' => null,
                    'error' => $e,
                    'options' => $options,
                ];
                
                throw $e;
            }
        };
    }
}
