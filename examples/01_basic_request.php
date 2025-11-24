<?php

/**
 * 基础 HTTP 请求示例
 */

require __DIR__ . '/../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use function PfinalClub\Asyncio\run;

function main(): void
{
    $client = new Client(['timeout' => 10]);

    echo "=== GET 请求示例 ===\n";
    $response = $client->get('https://httpbin.org/get', [
        'query' => ['page' => 1, 'limit' => 10],
    ]);
    
    echo "Status: {$response->getStatusCode()}\n";
    echo "Body: {$response->getBody()}\n\n";

    echo "=== POST JSON 示例 ===\n";
    $response = $client->post('https://httpbin.org/post', [
        'json' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ],
    ]);
    
    echo "Status: {$response->getStatusCode()}\n";
    echo "Body: {$response->getBody()}\n\n";

    echo "=== POST 表单示例 ===\n";
    $response = $client->post('https://httpbin.org/post', [
        'form_params' => [
            'username' => 'john',
            'password' => 'secret',
        ],
    ]);
    
    echo "Status: {$response->getStatusCode()}\n";
    echo "Body: {$response->getBody()}\n";
}

run(main(...));

