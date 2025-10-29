<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * 日志中间件
 * 记录HTTP请求和响应
 */
class LogMiddleware
{
    private LoggerInterface $logger;
    private $formatter;

    public function __construct(LoggerInterface $logger, $formatter = null)
    {
        $this->logger = $logger;
        $this->formatter = $formatter;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) use ($request) {
                    $this->logSuccess($request, $response);
                    return $response;
                },
                function (\Exception $reason) use ($request) {
                    $this->logFailure($request, $reason);
                    throw $reason;
                }
            );
        };
    }

    private function logSuccess(RequestInterface $request, ResponseInterface $response): void
    {
        $message = $this->format($request, $response, null);
        $this->logger->info($message, [
            'request' => $request,
            'response' => $response,
        ]);
    }

    private function logFailure(RequestInterface $request, \Exception $error): void
    {
        $message = $this->format($request, null, $error);
        $this->logger->error($message, [
            'request' => $request,
            'error' => $error,
        ]);
    }

    private function format(RequestInterface $request, ?ResponseInterface $response, ?\Exception $error): string
    {
        if (is_callable($this->formatter)) {
            return ($this->formatter)($request, $response, $error);
        }

        if ($response) {
            return sprintf(
                '%s %s -> %d %s',
                $request->getMethod(),
                $request->getUri(),
                $response->getStatusCode(),
                $response->getReasonPhrase()
            );
        }

        return sprintf(
            '%s %s -> ERROR: %s',
            $request->getMethod(),
            $request->getUri(),
            $error ? $error->getMessage() : 'Unknown'
        );
    }
}

