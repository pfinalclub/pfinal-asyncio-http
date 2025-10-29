<?php

require __DIR__ . '/../../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use function PfinalClub\Asyncio\run;

function main(): void
{
    $client = new Client(['verify' => false]);

    echo "发送 POST 请求（JSON）...\n";

    try {
        $response = $client->post('http://httpbin.org/post', [
            'json' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'age' => 30,
            ],
        ]);

        echo "状态码: " . $response->getStatusCode() . "\n";
        echo "\n响应体:\n";
        echo $response->getBody() . "\n";
    } catch (\Throwable $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }

    echo "\n发送 POST 请求（表单）...\n";

    try {
        $response = $client->post('http://httpbin.org/post', [
            'form_params' => [
                'username' => 'admin',
                'password' => 'secret',
            ],
        ]);

        echo "状态码: " . $response->getStatusCode() . "\n";
        echo "\n响应体:\n";
        echo $response->getBody() . "\n";
    } catch (\Throwable $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }
}

run(main(...));

