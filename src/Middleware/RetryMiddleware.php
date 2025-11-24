<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use PFinal\AsyncioHttp\Exception\ConnectException;

use function PfinalClub\Asyncio\sleep;

/**
 * 重试中间件
 * 在请求失败时自动重试
 */
class RetryMiddleware
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max' => 3,              // 最大重试次数
            'delay' => 0,            // 延迟（毫秒）或延迟函数
            'on_retry' => null,      // 重试回调
            'decide' => null,        // 决定是否重试的函数
        ], $config);
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $attempt = 0;
            $maxAttempts = $options['retry']['max'] ?? $this->config['max'];
            $delayOption = $options['retry']['delay'] ?? $this->config['delay'];
            $onRetry = $options['retry']['on_retry'] ?? $this->config['on_retry'];
            $decider = $options['retry']['decide'] ?? $this->config['decide'];

            while (true) {
                try {
                    $response = $handler($request, $options);

                    // 检查是否需要基于响应重试
                    if ($decider && $attempt < $maxAttempts) {
                        $shouldRetry = $decider($attempt, $request, $response, null);
                        if ($shouldRetry) {
                            $attempt++;
                            $this->delay($attempt, $delayOption);

                            if ($onRetry) {
                                $onRetry($attempt, $request, null, $response);
                            }

                            continue;
                        }
                    }

                    return $response;
                } catch (\Exception $e) {
                    $attempt++;

                    // 如果达到最大重试次数，抛出异常
                    if ($attempt >= $maxAttempts) {
                        throw $e;
                    }

                    // 检查是否应该重试
                    $shouldRetry = true;
                    if ($decider) {
                        $shouldRetry = $decider($attempt, $request, null, $e);
                    } else {
                        // 默认重试策略：只重试连接错误
                        $shouldRetry = $this->isRetryable($e);
                    }

                    if (!$shouldRetry) {
                        throw $e;
                    }

                    // 延迟后重试
                    $this->delay($attempt, $delayOption);

                    if ($onRetry) {
                        $onRetry($attempt, $request, $e, null);
                    }
                }
            }
        };
    }

    /**
     * 执行延迟
     */
    private function delay(int $attempt, $delayOption): void
    {
        if (is_callable($delayOption)) {
            $delayMs = $delayOption($attempt);
        } else {
            $delayMs = $delayOption;
        }

        if ($delayMs > 0) {
            // 使用 asyncio 的 sleep（不阻塞事件循环）
            sleep($delayMs / 1000);
        }
    }

    /**
     * 判断异常是否可重试
     */
    private function isRetryable(\Exception $e): bool
    {
        // 连接错误可以重试
        if ($e instanceof ConnectException) {
            return true;
        }

        // 超时可以重试
        if ($e instanceof \PFinal\AsyncioHttp\Exception\TimeoutException) {
            return true;
        }

        // 其他异常默认不重试
        return false;
    }

    /**
     * 指数退避延迟函数
     */
    public static function exponentialBackoff(int $baseDelay = 1000, int $maxDelay = 60000): callable
    {
        return function (int $attempt) use ($baseDelay, $maxDelay): int {
            $delay = $baseDelay * (2 ** ($attempt - 1));
            return min($delay, $maxDelay);
        };
    }

    /**
     * 线性退避延迟函数
     */
    public static function linearBackoff(int $baseDelay = 1000): callable
    {
        return function (int $attempt) use ($baseDelay): int {
            return $baseDelay * $attempt;
        };
    }

    /**
     * 固定延迟函数
     */
    public static function constantBackoff(int $delay = 1000): callable
    {
        return function (int $attempt) use ($delay): int {
            return $delay;
        };
    }

    /**
     * 基于状态码的重试决策器
     */
    public static function statusCodeDecider(array $statusCodes = [500, 502, 503, 504]): callable
    {
        return function (int $attempt, RequestInterface $request, ?ResponseInterface $response, ?\Exception $exception) use ($statusCodes): bool {
            // 如果有异常，检查是否可重试
            if ($exception) {
                return $exception instanceof ConnectException
                    || $exception instanceof \PFinal\AsyncioHttp\Exception\TimeoutException;
            }

            // 如果有响应，检查状态码
            if ($response) {
                return in_array($response->getStatusCode(), $statusCodes, true);
            }

            return false;
        };
    }
}
