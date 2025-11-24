<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Pool;
use function PfinalClub\Asyncio\run;

/**
 * Pool 并发池示例
 * 
 * 展示如何使用 Pool 批量处理大量请求，并控制并发数
 */

echo "=== Pool 并发池示例 ===\n\n";

run(function() {
    $client = new Client(['timeout' => 10]);

    echo "使用 Pool 发送 20 个请求，最多 5 个并发...\n";
    $startTime = microtime(true);

    // 创建请求列表
    $requests = [];
    for ($i = 1; $i <= 20; $i++) {
        $requests[] = fn() => $client->get("https://httpbin.org/get?id={$i}");
    }

    // 使用 Pool 批量执行，限制并发数为 5
    $results = Pool::batch($client, $requests, [
        'concurrency' => 5,
        'fulfilled' => function($response, $index) {
            echo "✅ 请求 {$index} 成功: 状态码 {$response->getStatusCode()}\n";
        },
        'rejected' => function($e, $index) {
            echo "❌ 请求 {$index} 失败: {$e->getMessage()}\n";
        },
    ]);

    $elapsed = microtime(true) - $startTime;

    echo "\n总耗时: " . number_format($elapsed, 2) . " 秒\n";
    echo "成功: " . count(array_filter($results, fn($r) => $r['state'] === 'fulfilled')) . " 个\n";
    echo "失败: " . count(array_filter($results, fn($r) => $r['state'] === 'rejected')) . " 个\n";
    echo "\n✅ Pool 请求完成!\n";
});
