<?php

require __DIR__ . '/../../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Pool;
use function PFinal\Asyncio\run;

function main(): void
{
    $client = new Client(['verify' => false]);

    echo "使用 Pool 进行并发请求（限制并发数为 3）...\n\n";
    $startTime = microtime(true);

    // 创建请求生成器
    $requests = function () use ($client) {
        for ($i = 1; $i <= 10; $i++) {
            yield "request-$i" => $client->getAsync("http://httpbin.org/delay/1");
        }
    };

    // 创建池并执行
    $pool = new Pool($client, $requests(), [
        'concurrency' => 3, // 最多同时3个请求
        'fulfilled' => function ($response, $index) {
            echo "✓ $index 完成 (状态: {$response->getStatusCode()})\n";
        },
        'rejected' => function ($reason, $index) {
            echo "✗ $index 失败: {$reason->getMessage()}\n";
        },
    ]);

    $results = $pool->promise()->wait();

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    echo "\n总计:\n";
    echo "  请求数: 10\n";
    echo "  并发限制: 3\n";
    echo "  总耗时: {$duration} 秒\n";
    echo "  成功: " . count(array_filter($results, fn($r) => $r['state'] === 'fulfilled')) . "\n";
    echo "  失败: " . count(array_filter($results, fn($r) => $r['state'] === 'rejected')) . "\n";
}

run(main(...));

