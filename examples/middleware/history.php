<?php
/**
 * 历史记录中间件示例
 * 追踪所有请求和响应
 */

require __DIR__ . '/../../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather};
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Middleware\Middleware;
use PFinal\AsyncioHttp\Handler\HandlerStack;

function main(): void
{
    // 创建历史记录容器
    $history = [];

    // 创建处理器栈并添加历史中间件
    $stack = HandlerStack::create();
    $stack->push(Middleware::history($history), 'history');

    // 创建客户端
    $client = new Client(['handler' => $stack]);

    echo "=== 执行多个请求 ===\n";

    // 执行多个请求
    $urls = [
        'https://httpbin.org/get',
        'https://httpbin.org/post',
        'https://httpbin.org/status/404', // 会失败
        'https://httpbin.org/delay/1',
    ];

    foreach ($urls as $url) {
        try {
            $method = strpos($url, '/post') !== false ? 'POST' : 'GET';
            if ($method === 'POST') {
                $response = $client->post($url, ['json' => ['test' => 'data']]);
            } else {
                $response = $client->get($url);
            }
            echo "✅ {$method} {$url} - {$response->getStatusCode()}\n";
        } catch (\Exception $e) {
            echo "❌ {$method} {$url} - {$e->getMessage()}\n";
        }
    }

    // 显示历史记录
    echo "\n=== 历史记录（{count($history)} 条）===\n";
    foreach ($history as $index => $entry) {
        $request = $entry['request'];
        $response = $entry['response'];
        $error = $entry['error'];

        echo sprintf(
            "[%d] %s %s",
            $index + 1,
            $request->getMethod(),
            $request->getUri()
        );

        if ($response) {
            echo sprintf(" → %d %s", $response->getStatusCode(), $response->getReasonPhrase());
        }

        if ($error) {
            echo sprintf(" → ERROR: %s", $error->getMessage());
        }

        echo "\n";
    }

    // 统计
    $successCount = count(array_filter($history, fn($e) => $e['response'] !== null));
    $errorCount = count(array_filter($history, fn($e) => $e['error'] !== null));

    echo "\n=== 统计 ===\n";
    echo "总请求数：" . count($history) . "\n";
    echo "成功：{$successCount}\n";
    echo "失败：{$errorCount}\n";
}

run(main(...));

