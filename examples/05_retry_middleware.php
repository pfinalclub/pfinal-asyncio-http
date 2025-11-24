<?php

/**
 * 重试中间件示例
 */

require __DIR__ . '/../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Middleware\RetryMiddleware;
use function PfinalClub\Asyncio\run;

function main(): void
{
    $client = new Client();

    echo "=== 重试中间件示例 ===\n";
    
    // 添加重试中间件
    $client->getHandlerStack()->push(
        new RetryMiddleware([
            'max' => 3,
            'delay' => RetryMiddleware::exponentialBackoff(1000, 60000),
            'on_retry' => function ($attempt, $request, $exception, $response) {
                echo "重试第 $attempt 次...\n";
            },
        ])
    );

    try {
        // 这个请求会失败，触发重试
        $response = $client->get('https://httpbin.org/status/500');
        echo "Status: {$response->getStatusCode()}\n";
    } catch (\Exception $e) {
        echo "请求失败（已重试3次）: {$e->getMessage()}\n";
    }

    echo "\n=== 自定义重试策略 ===\n";
    
    $client2 = new Client();
    $client2->getHandlerStack()->push(
        new RetryMiddleware([
            'max' => 5,
            'delay' => RetryMiddleware::linearBackoff(2000), // 线性退避：2s, 4s, 6s...
            'decide' => function ($attempt, $request, $response, $exception) {
                // 只重试 5xx 错误
                if ($response && $response->getStatusCode() >= 500) {
                    return true;
                }
                // 重试连接错误
                if ($exception) {
                    return true;
                }
                return false;
            },
        ])
    );

    try {
        $response = $client2->get('https://httpbin.org/status/503');
        echo "Status: {$response->getStatusCode()}\n";
    } catch (\Exception $e) {
        echo "请求失败: {$e->getMessage()}\n";
    }
}

run(main(...));

