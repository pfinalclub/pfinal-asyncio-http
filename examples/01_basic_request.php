<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use function PfinalClub\Asyncio\run;

/**
 * 基础 HTTP 请求示例
 * 
 * 展示如何使用 Client 发送基本的 GET/POST 请求
 */

echo "=== 基础 HTTP 请求示例 ===\n\n";

// 所有代码必须在 run() 函数内
run(function() {
    $client = new Client([
        'timeout' => 10,
    ]);

    echo "1. GET 请求：\n";
    $response = $client->get('https://httpbin.org/get?name=张三&age=25');
    echo "状态码: {$response->getStatusCode()}\n";
    echo "响应体: " . substr($response->getBody(), 0, 200) . "...\n\n";

    echo "2. POST JSON 请求：\n";
    $response = $client->post('https://httpbin.org/post', [
        'json' => [
            'name' => '李四',
            'email' => 'lisi@example.com',
            'age' => 30,
        ],
    ]);
    echo "状态码: {$response->getStatusCode()}\n";
    echo "响应体: " . substr($response->getBody(), 0, 200) . "...\n\n";

    echo "3. POST 表单请求：\n";
    $response = $client->post('https://httpbin.org/post', [
        'form_params' => [
            'username' => 'wangwu',
            'password' => '123456',
        ],
    ]);
    echo "状态码: {$response->getStatusCode()}\n";
    echo "响应体: " . substr($response->getBody(), 0, 200) . "...\n\n";

    echo "4. 自定义请求头：\n";
    $response = $client->get('https://httpbin.org/headers', [
        'headers' => [
            'User-Agent' => 'My-Custom-Agent/1.0',
            'Accept-Language' => 'zh-CN,zh;q=0.9',
            'X-Custom-Header' => 'Custom Value',
        ],
    ]);
    echo "状态码: {$response->getStatusCode()}\n";
    echo "响应体: " . substr($response->getBody(), 0, 200) . "...\n\n";

    echo "✅ 所有请求完成!\n";
});
