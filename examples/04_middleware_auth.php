<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Handler\HandlerStack;
use PFinal\AsyncioHttp\Handler\AsyncioHandler;
use PFinal\AsyncioHttp\Middleware\AuthMiddleware;
use PFinal\AsyncioHttp\Middleware\RetryMiddleware;
use PFinal\AsyncioHttp\Middleware\RedirectMiddleware;
use function PfinalClub\Asyncio\run;

/**
 * 中间件示例
 * 
 * 展示如何使用中间件：认证、重试、重定向等
 */

echo "=== 中间件示例 ===\n\n";

run(function() {
    // 创建自定义处理器栈
    $handler = new AsyncioHandler();
    $stack = HandlerStack::create($handler);

    // 添加重试中间件
    $stack->push(new RetryMiddleware([
        'max' => 3,
        'delay' => RetryMiddleware::exponentialBackoff(500, 5000),
    ]), 'retry');

    // 添加重定向中间件
    $stack->push(new RedirectMiddleware([
        'max' => 5,
        'referer' => true,
    ]), 'redirect');

    // 添加认证中间件
    $stack->push(AuthMiddleware::basic('user', 'pass'), 'auth');

    // 创建客户端
    $client = new Client([
        'handler' => $stack,
        'timeout' => 10,
    ]);

    echo "1. Basic 认证请求：\n";
    try {
        $response = $client->get('https://httpbin.org/basic-auth/user/pass');
        echo "状态码: {$response->getStatusCode()}\n";
        echo "认证成功!\n\n";
    } catch (\Exception $e) {
        echo "认证失败: {$e->getMessage()}\n\n";
    }

    echo "2. 重定向请求：\n";
    $response = $client->get('https://httpbin.org/redirect/3');
    echo "状态码: {$response->getStatusCode()}\n";
    echo "经过重定向后的最终响应\n\n";

    echo "3. 带重试的请求：\n";
    try {
        // 这个请求会失败并重试
        $response = $client->get('https://httpbin.org/status/503', [
            'retry' => [
                'max' => 3,
                'delay' => 1000,  // 1秒
                'on_retry' => function($attempt, $request, $exception, $response) {
                    echo "  重试第 {$attempt} 次...\n";
                },
            ],
        ]);
    } catch (\Exception $e) {
        echo "  请求最终失败: {$e->getMessage()}\n";
    }

    echo "\n✅ 中间件示例完成!\n";
});
