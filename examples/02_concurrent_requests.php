<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use function PfinalClub\Asyncio\{run, create_task, gather};

/**
 * 并发请求示例
 * 
 * 展示如何使用 create_task 和 gather 实现并发请求
 */

echo "=== 并发 HTTP 请求示例 ===\n\n";

run(function() {
    $client = new Client(['timeout' => 10]);

    echo "发送 5 个并发请求...\n";
    $startTime = microtime(true);

    // 创建任务（立即开始执行）
    $tasks = [
        create_task(fn() => $client->get('https://httpbin.org/delay/1')),
        create_task(fn() => $client->get('https://httpbin.org/delay/1')),
        create_task(fn() => $client->get('https://httpbin.org/delay/1')),
        create_task(fn() => $client->get('https://httpbin.org/delay/1')),
        create_task(fn() => $client->get('https://httpbin.org/delay/1')),
    ];

    // 等待所有任务完成
    $responses = gather(...$tasks);

    $elapsed = microtime(true) - $startTime;

    echo "\n结果：\n";
    foreach ($responses as $index => $response) {
        echo "  响应 " . ($index + 1) . ": 状态码 {$response->getStatusCode()}\n";
    }

    echo "\n总耗时: " . number_format($elapsed, 2) . " 秒\n";
    echo "（如果是串行执行，需要 ~5 秒；并发执行只需 ~1 秒）\n";
    echo "\n✅ 并发请求完成!\n";
});
