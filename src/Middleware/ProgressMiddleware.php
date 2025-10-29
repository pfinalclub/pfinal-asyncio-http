<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 进度监控中间件
 * 跟踪上传和下载进度
 */
class ProgressMiddleware
{
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $callback = $this->callback;

            // 添加进度回调到选项
            $options['progress'] = function (
                int $downloadTotal,
                int $downloadCurrent,
                int $uploadTotal,
                int $uploadCurrent
            ) use ($callback) {
                $callback(
                    $downloadTotal,
                    $downloadCurrent,
                    $uploadTotal,
                    $uploadCurrent
                );
            };

            return $handler($request, $options);
        };
    }
}

