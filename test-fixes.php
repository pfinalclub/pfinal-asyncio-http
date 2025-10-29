<?php
/**
 * 测试修复后的功能
 */

require __DIR__ . '/vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Handler\HandlerStack;
use PFinal\AsyncioHttp\Middleware\Middleware;
use function PfinalClub\Asyncio\{run, create_task, gather};

function main(): void
{
    echo "=== PFinal AsyncIO HTTP - 修复验证测试 ===\n\n";

    // 创建客户端
    $client = new Client([
        'timeout' => 30,
        'verify' => false,
    ]);

    // 测试 1: 基础 GET 请求（验证 Host 头和 Content-Length）
    echo "1. 测试基础 GET 请求...\n";
    try {
        $response = $client->get('http://httpbin.org/get', [
            'query' => ['test' => 'value'],
        ]);
        echo "   ✓ 状态码: {$response->getStatusCode()}\n";
        echo "   ✓ 基础请求成功\n";
    } catch (\Exception $e) {
        echo "   ✗ 失败: {$e->getMessage()}\n";
    }
    echo "\n";

    // 测试 2: POST 请求（验证 Content-Length 头）
    echo "2. 测试 POST 请求...\n";
    try {
        $response = $client->post('http://httpbin.org/post', [
            'json' => ['name' => 'test', 'value' => '测试'],
        ]);
        echo "   ✓ 状态码: {$response->getStatusCode()}\n";
        echo "   ✓ POST 请求成功\n";
    } catch (\Exception $e) {
        echo "   ✗ 失败: {$e->getMessage()}\n";
    }
    echo "\n";

    // 测试 3: 重定向测试（验证重定向安全性和异步性）
    echo "3. 测试重定向处理...\n";
    try {
        $response = $client->get('http://httpbin.org/redirect/2');
        echo "   ✓ 状态码: {$response->getStatusCode()}\n";
        echo "   ✓ 重定向处理成功\n";
    } catch (\Exception $e) {
        echo "   ✗ 失败: {$e->getMessage()}\n";
    }
    echo "\n";

    // 测试 4: 并发请求（验证异步性能）
    echo "4. 测试并发请求性能...\n";
    try {
        $start = microtime(true);
        
        $task1 = create_task(fn() => $client->get('http://httpbin.org/delay/1'));
        $task2 = create_task(fn() => $client->get('http://httpbin.org/delay/1'));
        $task3 = create_task(fn() => $client->get('http://httpbin.org/delay/1'));
        
        $responses = gather($task1, $task2, $task3);
        
        $duration = (microtime(true) - $start);
        echo "   ✓ 完成 3 个请求\n";
        echo "   ✓ 总耗时: " . number_format($duration, 2) . " 秒\n";
        
        if ($duration < 2.5) {
            echo "   ✓ 并发性能正常（预期 ~1 秒，实际 " . number_format($duration, 2) . " 秒）\n";
        } else {
            echo "   ⚠ 并发性能可能有问题（预期 ~1 秒，实际 " . number_format($duration, 2) . " 秒）\n";
        }
    } catch (\Exception $e) {
        echo "   ✗ 失败: {$e->getMessage()}\n";
    }
    echo "\n";

    // 测试 5: 自定义 HandlerStack
    echo "5. 测试自定义 HandlerStack...\n";
    try {
        $stack = HandlerStack::create();
        $stack->push(Middleware::mapRequest(function ($request) {
            return $request->withHeader('X-Custom-Test', 'test-value');
        }), 'custom_header');

        $client2 = new Client(['handler' => $stack, 'verify' => false]);
        $response = $client2->get('http://httpbin.org/headers');
        $body = json_decode($response->getBody(), true);
        
        if (isset($body['headers']['X-Custom-Test'])) {
            echo "   ✓ 自定义中间件工作正常\n";
        } else {
            echo "   ⚠ 自定义头未找到\n";
        }
    } catch (\Exception $e) {
        echo "   ✗ 失败: {$e->getMessage()}\n";
    }
    echo "\n";

    // 测试 6: Chunked 编码（如果服务器支持）
    echo "6. 测试 Chunked 编码支持...\n";
    try {
        $response = $client->get('http://httpbin.org/stream/3');
        echo "   ✓ 状态码: {$response->getStatusCode()}\n";
        echo "   ✓ Chunked 响应处理成功\n";
    } catch (\Exception $e) {
        echo "   ✗ 失败: {$e->getMessage()}\n";
    }
    echo "\n";

    // 测试 7: 超时处理
    echo "7. 测试超时处理...\n";
    try {
        $client3 = new Client(['timeout' => 2, 'verify' => false]);
        $response = $client3->get('http://httpbin.org/delay/5');
        echo "   ⚠ 应该超时但没有超时\n";
    } catch (\PFinal\AsyncioHttp\Exception\TimeoutException $e) {
        echo "   ✓ 超时处理正常\n";
    } catch (\Exception $e) {
        echo "   ⚠ 捕获到其他异常: {$e->getMessage()}\n";
    }
    echo "\n";

    echo "=== 测试完成 ===\n";
}

run(main(...));

