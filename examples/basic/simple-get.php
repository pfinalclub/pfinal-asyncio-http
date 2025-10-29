<?php

require __DIR__ . '/../../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use function PfinalClub\Asyncio\run;

function main(): void
{
    $client = new Client([
        'timeout' => 10,
        'verify' => false, // 仅演示用，生产环境应该验证证书
    ]);

    echo "发送 GET 请求到 httpbin.org...\n";

    try {
        $response = $client->get('http://httpbin.org/get', [
            'query' => [
                'foo' => 'bar',
                'name' => 'pfinal-asyncio-http',
            ],
        ]);

        echo "状态码: " . $response->getStatusCode() . "\n";
        echo "原因短语: " . $response->getReasonPhrase() . "\n";
        echo "\n响应头:\n";
        foreach ($response->getHeaders() as $name => $values) {
            echo "  $name: " . implode(', ', $values) . "\n";
        }

        echo "\n响应体:\n";
        echo $response->getBody() . "\n";
    } catch (\Throwable $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }
}

run(main(...));

