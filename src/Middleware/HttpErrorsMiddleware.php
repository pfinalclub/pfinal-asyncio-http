<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use PFinal\AsyncioHttp\Exception\ClientException;
use PFinal\AsyncioHttp\Exception\ServerException;

/**
 * HTTP 错误处理中间件
 * 当响应状态码为 4xx 或 5xx 时抛出异常
 */
class HttpErrorsMiddleware
{
    private bool $enabled;

    public function __construct(bool $enabled = true)
    {
        $this->enabled = $enabled;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $enabled = $options['http_errors'] ?? $this->enabled;

            if (!$enabled) {
                return $handler($request, $options);
            }

            $response = $handler($request, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400 && $statusCode < 500) {
                throw new ClientException(
                    sprintf(
                        'Client error: `%s %s` resulted in a `%s %s` response',
                        $request->getMethod(),
                        $request->getUri(),
                        $response->getStatusCode(),
                        $response->getReasonPhrase()
                    ),
                    $request,
                    $response
                );
            }

            if ($statusCode >= 500) {
                throw new ServerException(
                    sprintf(
                        'Server error: `%s %s` resulted in a `%s %s` response',
                        $request->getMethod(),
                        $request->getUri(),
                        $response->getStatusCode(),
                        $response->getReasonPhrase()
                    ),
                    $request,
                    $response
                );
            }

            return $response;
        };
    }

    /**
     * 工厂方法
     */
    public static function create(bool $enabled = true): callable
    {
        return function (callable $handler) use ($enabled): callable {
            $middleware = new self($enabled);
            return $middleware($handler);
        };
    }
}
