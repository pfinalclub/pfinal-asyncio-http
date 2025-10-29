<?php
/**
 * 请求/响应映射中间件示例
 * 演示如何转换请求和响应
 */

require __DIR__ . '/../../vendor/autoload.php';

use function PFinal\Asyncio\run;
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Middleware\Middleware;
use PFinal\AsyncioHttp\Handler\HandlerStack;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function main(): void
{
    // 创建处理器栈
    $stack = HandlerStack::create();

    // 添加请求映射中间件 - 添加认证头
    $stack->push(
        Middleware::mapRequest(function (RequestInterface $request) {
            echo "🔹 映射请求：添加认证头\n";
            return $request->withHeader('Authorization', 'Bearer my-secret-token');
        }),
        'add_auth'
    );

    // 添加请求映射中间件 - 修改 User-Agent
    $stack->push(
        Middleware::mapRequest(function (RequestInterface $request) {
            echo "🔹 映射请求：修改 User-Agent\n";
            return $request->withHeader('User-Agent', 'PFinal-AsyncIO-HTTP/1.0');
        }),
        'user_agent'
    );

    // 添加响应映射中间件 - 处理响应体
    $stack->push(
        Middleware::mapResponse(function (ResponseInterface $response) {
            echo "🔧 映射响应：添加自定义头\n";
            return $response->withHeader('X-Processed-At', date('Y-m-d H:i:s'));
        }),
        'process_response'
    );

    // 创建客户端
    $client = new Client(['handler' => $stack]);

    echo "=== 执行请求 ===\n";
    $response = $client->get('https://httpbin.org/headers');

    echo "\n=== 响应信息 ===\n";
    echo "状态码：{$response->getStatusCode()}\n";
    echo "自定义头：{$response->getHeaderLine('X-Processed-At')}\n";

    // 查看服务器收到的头
    $body = json_decode($response->getBody()->getContents(), true);
    echo "\n=== 服务器收到的请求头 ===\n";
    if (isset($body['headers'])) {
        foreach (['Authorization', 'User-Agent'] as $header) {
            $key = str_replace('-', '', ucwords($header, '-'));
            if (isset($body['headers'][$key])) {
                echo "{$header}: {$body['headers'][$key]}\n";
            }
        }
    }

    echo "\n=== 中间件栈 ===\n";
    echo $stack->debug() . "\n";
}

run(main(...));

