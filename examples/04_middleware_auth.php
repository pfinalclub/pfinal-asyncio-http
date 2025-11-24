<?php

/**
 * 认证中间件示例
 */

require __DIR__ . '/../vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Middleware\AuthMiddleware;
use function PfinalClub\Asyncio\run;

function main(): void
{
    $client = new Client();

    echo "=== Basic 认证示例 ===\n";
    $client->getHandlerStack()->push(
        AuthMiddleware::basic('username', 'password')
    );
    
    $response = $client->get('https://httpbin.org/basic-auth/username/password');
    echo "Status: {$response->getStatusCode()}\n";
    echo "Body: {$response->getBody()}\n\n";

    echo "=== Bearer Token 认证示例 ===\n";
    $client2 = new Client();
    $client2->getHandlerStack()->push(
        AuthMiddleware::bearer('your-api-token-here')
    );
    
    $response = $client2->get('https://httpbin.org/bearer');
    echo "Status: {$response->getStatusCode()}\n";
    echo "Body: {$response->getBody()}\n\n";

    echo "=== 使用 auth 选项 ===\n";
    $client3 = new Client();
    $response = $client3->get('https://httpbin.org/basic-auth/user/pass', [
        'auth' => ['user', 'pass', 'basic'],
    ]);
    echo "Status: {$response->getStatusCode()}\n";
    echo "Body: {$response->getBody()}\n";
}

run(main(...));

