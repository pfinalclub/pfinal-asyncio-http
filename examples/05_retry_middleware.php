<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Handler\HandlerStack;
use PFinal\AsyncioHttp\Handler\AsyncioHandler;
use PFinal\AsyncioHttp\Middleware\RetryMiddleware;
use function PfinalClub\Asyncio\run;

/**
 * 重试中间件高级示例
 * 
 * 展示重试策略和自定义重试决策
 */

echo "=== 重试中间件高级示例 ===\n\n";

run(function() {
    // 创建带重试的客户端
    $handler = new AsyncioHandler();
    $stack = HandlerStack::create($handler);

    // 添加重试中间件（指数退避）
    $stack->push(new RetryMiddleware([
        'max' => 5,
        'delay' => RetryMiddleware::exponentialBackoff(500, 10000),
        'decide' => RetryMiddleware::statusCodeDecider([500, 502, 503, 504]),
        'on_retry' => function($attempt, $request, $exception, $response) {
            if ($exception) {
                echo "⚠️  第 {$attempt} 次重试（异常: {$exception->getMessage()}）\n";
            } else {
                echo "⚠️  第 {$attempt} 次重试（状态码: {$response->getStatusCode()}）\n";
            }
        },
    ]), 'retry');

    $client = new Client(['handler' => $stack, 'timeout' => 10]);

    echo "1. 测试 503 错误（会重试）：\n";
    try {
        $response = $client->get('https://httpbin.org/status/503');
        echo "✅ 请求成功！状态码: {$response->getStatusCode()}\n\n";
    } catch (\Exception $e) {
        echo "❌ 重试 5 次后仍然失败\n\n";
    }

    echo "2. 测试 404 错误（不会重试）：\n";
    try {
        $response = $client->get('https://httpbin.org/status/404');
        echo "✅ 请求成功！状态码: {$response->getStatusCode()}\n\n";
    } catch (\Exception $e) {
        echo "❌ 请求失败: {$e->getMessage()}\n\n";
    }

    echo "3. 线性退避策略：\n";
    $handler2 = new AsyncioHandler();
    $stack2 = HandlerStack::create($handler2);
    $stack2->push(new RetryMiddleware([
        'max' => 3,
        'delay' => RetryMiddleware::linearBackoff(1000),  // 每次增加 1 秒
    ]), 'retry');
    $client2 = new Client(['handler' => $stack2, 'timeout' => 10]);

    echo "（线性退避：1秒、2秒、3秒）\n";
    echo "\n✅ 重试示例完成!\n";
});
