<?php

/**
 * 并发请求示例
 */

require __DIR__ . '/../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use function PfinalClub\Asyncio\{run, create_task, gather};

function main(): void
{
    $client = new Client(['timeout' => 10]);

    echo "=== 开始并发请求（5个请求）===\n";
    $startTime = microtime(true);

    // 创建 5 个并发任务
    $urls = [
        'https://httpbin.org/delay/1',
        'https://httpbin.org/delay/1',
        'https://httpbin.org/delay/1',
        'https://httpbin.org/delay/1',
        'https://httpbin.org/delay/1',
    ];

    $tasks = [];
    foreach ($urls as $i => $url) {
        $tasks[] = create_task(function () use ($client, $url, $i) {
            echo "开始请求 $i...\n";
            $response = $client->get($url);
            echo "完成请求 $i (状态: {$response->getStatusCode()})\n";
            return $response;
        });
    }

    // 并发执行所有任务
    $responses = gather(...$tasks);

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    echo "\n总计:\n";
    echo "  请求数: " . count($responses) . "\n";
    echo "  总耗时: {$duration} 秒\n";
    echo "  平均耗时: " . round($duration / count($responses), 2) . " 秒/请求\n";
    echo "\n如果是顺序执行，大约需要 5 秒\n";
    echo "实际耗时 {$duration} 秒，性能提升 " . round(5 / $duration, 1) . "x\n";
}

run(main(...));

