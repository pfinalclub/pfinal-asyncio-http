<?php
/**
 * 自定义中间件示例
 * 演示如何创建自己的中间件
 */

require __DIR__ . '/../../vendor/autoload.php';

use function PfinalClub\Asyncio\run;
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Handler\HandlerStack;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function main(): void
{
    // 自定义中间件 1：添加自定义请求头
    $addHeadersMiddleware = function (callable $handler) {
        return function (RequestInterface $request, array $options) use ($handler) {
            // 添加自定义头
            $request = $request
                ->withHeader('X-Custom-Header', 'MyValue')
                ->withHeader('X-Request-ID', uniqid('req_'));

            echo "🔹 添加自定义头：{$request->getHeaderLine('X-Request-ID')}\n";

            // 调用下一个处理器
            return $handler($request, $options);
        };
    };

    // 自定义中间件 2：记录请求时间
    $timingMiddleware = function (callable $handler) {
        return function (RequestInterface $request, array $options) use ($handler) {
            echo "⏱️  开始请求：{$request->getMethod()} {$request->getUri()}\n";
            $start = microtime(true);

            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) use ($start) {
                    $duration = (microtime(true) - $start) * 1000;
                    echo sprintf("✅ 请求完成，耗时：%.2f ms\n", $duration);
                    return $response;
                },
                function (\Exception $e) use ($start) {
                    $duration = (microtime(true) - $start) * 1000;
                    echo sprintf("❌ 请求失败，耗时：%.2f ms\n", $duration);
                    throw $e;
                }
            );
        };
    };

    // 自定义中间件 3：修改响应
    $modifyResponseMiddleware = function (callable $handler) {
        return function (RequestInterface $request, array $options) use ($handler) {
            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) {
                    // 添加自定义响应头
                    $response = $response->withHeader('X-Processed-By', 'PFinal-AsyncIO-HTTP');
                    echo "🔧 修改响应头\n";
                    return $response;
                }
            );
        };
    };

    // 创建处理器栈并添加中间件
    $stack = HandlerStack::create();
    $stack->push($addHeadersMiddleware, 'add_headers');
    $stack->push($timingMiddleware, 'timing');
    $stack->push($modifyResponseMiddleware, 'modify_response');

    // 创建客户端
    $client = new Client(['handler' => $stack]);

    echo "=== 执行带自定义中间件的请求 ===\n\n";

    $response = $client->get('https://httpbin.org/headers');

    echo "\n=== 响应信息 ===\n";
    echo "状态码：{$response->getStatusCode()}\n";
    echo "自定义响应头：{$response->getHeaderLine('X-Processed-By')}\n";

    // 解析响应体（httpbin 会返回我们发送的头）
    $body = json_decode($response->getBody()->getContents(), true);
    if (isset($body['headers']['X-Custom-Header'])) {
        echo "服务器收到的自定义头：{$body['headers']['X-Custom-Header']}\n";
    }
    if (isset($body['headers']['X-Request-Id'])) {
        echo "服务器收到的请求ID：{$body['headers']['X-Request-Id']}\n";
    }

    echo "\n=== 中间件栈 ===\n";
    echo $stack->debug() . "\n";
}

run(main(...));

