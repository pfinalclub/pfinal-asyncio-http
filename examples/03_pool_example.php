<?php

/**
 * 请求池示例 - 批量请求
 */

require __DIR__ . '/../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Pool;
use function PfinalClub\Asyncio\run;

function main(): void
{
    $client = new Client();

    echo "=== 使用 Pool 批量请求 ===\n";
    $startTime = microtime(true);

    // 生成 20 个请求
    $requests = function () use ($client) {
        for ($i = 1; $i <= 20; $i++) {
            yield $client->getAsync("https://httpbin.org/delay/1?id=$i");
        }
    };

    // 创建请求池，并发限制为 5
    $pool = new Pool($client, $requests(), [
        'concurrency' => 5,
        'fulfilled' => function ($response, $index) {
            echo "✓ Request $index completed (status: {$response->getStatusCode()})\n";
        },
        'rejected' => function ($reason, $index) {
            echo "✗ Request $index failed: {$reason->getMessage()}\n";
        },
    ]);

    // 执行所有请求
    $results = $pool->promise()->wait();

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    echo "\n统计:\n";
    echo "  总请求数: 20\n";
    echo "  并发限制: 5\n";
    echo "  总耗时: {$duration} 秒\n";
    echo "  理论耗时: " . ceil(20 / 5) . " 秒（20个请求 / 5并发）\n";
}

run(main(...));

