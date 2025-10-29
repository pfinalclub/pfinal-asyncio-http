<?php
/**
 * 基础中间件示例
 * 演示如何使用内置中间件
 */

require __DIR__ . '/../../vendor/autoload.php';

use function PFinal\Asyncio\run;
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Middleware\Middleware;
use PFinal\AsyncioHttp\Handler\HandlerStack;

function main(): void
{
    // 创建自定义处理器栈
    $stack = HandlerStack::create();

    // 添加重定向中间件
    $stack->push(
        Middleware::redirect([
            'max' => 5,
            'strict' => false,
            'referer' => true,
            'track_redirects' => true,
        ]),
        'redirect'
    );

    // 添加重试中间件
    $stack->push(
        Middleware::retry([
            'max' => 3,
            'delay' => Middleware\RetryMiddleware::exponentialBackoff(1000),
            'on_retry' => function ($attempt, $request, $error) {
                echo "重试第 {$attempt} 次：{$request->getUri()}\n";
                if ($error) {
                    echo "  原因：{$error->getMessage()}\n";
                }
            },
        ]),
        'retry'
    );

    // 添加日志中间件
    if (class_exists('\Monolog\Logger')) {
        $logger = new \Monolog\Logger('http');
        $logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout'));

        $stack->push(Middleware::log($logger), 'log');
    }

    // 创建客户端
    $client = new Client(['handler' => $stack]);

    echo "=== 测试重定向 ===\n";
    try {
        $response = $client->get('http://httpbin.org/redirect/3');
        echo "最终状态码：{$response->getStatusCode()}\n";
    } catch (\Exception $e) {
        echo "错误：{$e->getMessage()}\n";
    }

    echo "\n=== 测试重试 ===\n";
    try {
        // 这个请求可能会失败并重试
        $response = $client->get('http://httpbin.org/delay/5', ['timeout' => 2]);
        echo "成功！状态码：{$response->getStatusCode()}\n";
    } catch (\Exception $e) {
        echo "最终失败：{$e->getMessage()}\n";
    }

    echo "\n=== 查看中间件栈 ===\n";
    echo $stack->debug() . "\n";
}

run(main(...));

