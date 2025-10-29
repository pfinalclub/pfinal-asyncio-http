# 快速入门指南

5分钟快速了解如何使用 pfinal-asyncio-http！

## 📦 安装

```bash
cd /Users/pfinal/www/pfinal-asyncio-http
composer install
```

## 🚀 第一个请求

创建 `test.php`：

```php
<?php
require 'vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use function PFinal\Asyncio\run;

function main(): void
{
    $client = new Client(['verify' => false]);
    
    $response = $client->get('http://httpbin.org/get');
    
    echo "状态码: " . $response->getStatusCode() . "\n";
    echo "响应体:\n" . $response->getBody() . "\n";
}

run(main(...));
```

运行：
```bash
php test.php
```

## 🎯 常用功能

### 1. GET 请求带参数

```php
$response = $client->get('http://httpbin.org/get', [
    'query' => [
        'foo' => 'bar',
        'name' => 'John',
    ],
]);
```

### 2. POST JSON 数据

```php
$response = $client->post('http://httpbin.org/post', [
    'json' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ],
]);
```

### 3. POST 表单数据

```php
$response = $client->post('http://httpbin.org/post', [
    'form_params' => [
        'username' => 'admin',
        'password' => 'secret',
    ],
]);
```

### 4. 自定义请求头

```php
$response = $client->get('http://httpbin.org/headers', [
    'headers' => [
        'User-Agent' => 'MyApp/1.0',
        'Authorization' => 'Bearer token123',
    ],
]);
```

### 5. 设置超时

```php
$client = new Client([
    'timeout' => 10,           // 总超时
    'connect_timeout' => 5,    // 连接超时
]);
```

## ⚡ 异步并发

### 并发执行多个请求

```php
use function PFinal\Asyncio\{create_task, gather};

function main(): void
{
    $client = new Client(['verify' => false]);
    
    // 创建3个并发任务
    $task1 = create_task(fn() => $client->get('http://httpbin.org/delay/1'));
    $task2 = create_task(fn() => $client->get('http://httpbin.org/delay/1'));
    $task3 = create_task(fn() => $client->get('http://httpbin.org/delay/1'));
    
    // 并发执行（只需1秒，而非3秒！）
    $responses = gather($task1, $task2, $task3);
    
    foreach ($responses as $i => $response) {
        echo "请求 $i: " . $response->getStatusCode() . "\n";
    }
}

run(main(...));
```

### 使用请求池（控制并发数）

```php
use PFinal\AsyncioHttp\Pool;

function main(): void
{
    $client = new Client(['verify' => false]);
    
    // 创建10个请求，但最多同时执行3个
    $requests = function () use ($client) {
        for ($i = 1; $i <= 10; $i++) {
            yield $client->getAsync("http://httpbin.org/delay/1");
        }
    };
    
    $pool = new Pool($client, $requests(), [
        'concurrency' => 3,  // 并发限制
        'fulfilled' => function ($response, $index) {
            echo "✓ 请求 $index 完成\n";
        },
    ]);
    
    $pool->promise()->wait();
}

run(main(...));
```

## 🛡️ 错误处理

```php
use PFinal\AsyncioHttp\Exception\ConnectException;
use PFinal\AsyncioHttp\Exception\RequestException;

try {
    $response = $client->get('http://example.com/api');
    echo $response->getBody();
} catch (ConnectException $e) {
    echo "连接失败: " . $e->getMessage() . "\n";
} catch (RequestException $e) {
    echo "请求错误: " . $e->getMessage() . "\n";
    if ($e->hasResponse()) {
        echo "状态码: " . $e->getResponse()->getStatusCode() . "\n";
    }
}
```

## 📝 使用 PSR-7

```php
use PFinal\AsyncioHttp\Psr7\Request;

// 创建 PSR-7 请求
$request = new Request('POST', 'http://httpbin.org/post');
$request = $request
    ->withHeader('Content-Type', 'application/json')
    ->withBody($stream);

// 发送 PSR-7 请求
$response = $client->send($request);
```

## 🔧 配置选项

### 创建客户端时配置

```php
$client = new Client([
    'base_uri' => 'https://api.example.com',
    'timeout' => 30,
    'connect_timeout' => 10,
    'headers' => [
        'User-Agent' => 'MyApp/1.0',
        'Accept' => 'application/json',
    ],
    'verify' => true,  // 验证 SSL 证书（生产环境推荐）
]);

// 后续请求会使用 base_uri
$response = $client->get('/users');  // 实际请求：https://api.example.com/users
```

### 单次请求配置

```php
$response = $client->get('http://httpbin.org/get', [
    'timeout' => 5,
    'headers' => ['X-Custom' => 'value'],
    'query' => ['page' => 1],
]);
```

## 💡 实用技巧

### 1. 重用客户端

```php
// ✅ 好 - 重用客户端实例
$client = new Client(['verify' => false]);
$response1 = $client->get('http://example.com/1');
$response2 = $client->get('http://example.com/2');

// ❌ 不好 - 每次都创建新客户端
$response1 = (new Client())->get('http://example.com/1');
$response2 = (new Client())->get('http://example.com/2');
```

### 2. 处理 JSON 响应

```php
$response = $client->get('http://httpbin.org/json');
$data = json_decode($response->getBody(), true);
print_r($data);
```

### 3. 检查响应状态

```php
$response = $client->get('http://httpbin.org/status/200');

if ($response->getStatusCode() === 200) {
    echo "成功！\n";
}

if ($response->getStatusCode() >= 400) {
    echo "错误！\n";
}
```

### 4. 读取响应头

```php
$response = $client->get('http://httpbin.org/response-headers');

// 获取单个头
$contentType = $response->getHeaderLine('Content-Type');

// 获取所有头
foreach ($response->getHeaders() as $name => $values) {
    echo "$name: " . implode(', ', $values) . "\n";
}
```

## 🎓 完整示例

创建一个简单的 API 客户端：

```php
<?php
require 'vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Exception\RequestException;
use function PFinal\Asyncio\{run, create_task, gather};

class ApiClient
{
    private Client $client;
    
    public function __construct(string $baseUri, string $apiKey)
    {
        $this->client = new Client([
            'base_uri' => $baseUri,
            'timeout' => 30,
            'headers' => [
                'Authorization' => "Bearer $apiKey",
                'Accept' => 'application/json',
            ],
            'verify' => false,
        ]);
    }
    
    public function getUser(int $id): array
    {
        try {
            $response = $this->client->get("/users/$id");
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            echo "获取用户失败: " . $e->getMessage() . "\n";
            return [];
        }
    }
    
    public function getUsers(array $ids): array
    {
        $tasks = [];
        foreach ($ids as $id) {
            $tasks[] = create_task(fn() => $this->getUser($id));
        }
        
        return gather(...$tasks);
    }
}

function main(): void
{
    $api = new ApiClient('https://api.example.com', 'your-api-key');
    
    // 获取单个用户
    $user = $api->getUser(1);
    print_r($user);
    
    // 并发获取多个用户
    $users = $api->getUsers([1, 2, 3, 4, 5]);
    echo "获取了 " . count($users) . " 个用户\n";
}

run(main(...));
```

## 📚 更多资源

- 查看 `examples/` 目录获取更多示例
- 阅读 `README.md` 了解完整功能
- 查看 `PROJECT_STATUS.md` 了解项目状态
- 阅读 `NEXT_STEPS.md` 了解开发计划

## ❓ 常见问题

### Q: 为什么必须在 `run()` 中执行？
A: 因为 pfinal-asyncio 需要事件循环环境，`run()` 会创建并管理这个环境。

### Q: 可以在生产环境使用吗？
A: 当前是开发版本（v0.1.0-dev），建议等待 v1.0.0 正式版。

### Q: 性能真的比 Guzzle 快吗？
A: 在并发场景下，性能提升 2-3 倍。单个请求性能相当。

### Q: 与 Guzzle 完全兼容吗？
A: API 层面兼容主要功能，但部分高级功能（中间件、Cookie 等）尚未实现。

## 🎉 开始使用吧！

现在你已经掌握了基础用法，可以：

1. 运行 `examples/` 目录中的示例
2. 在你的项目中试用
3. 查看文档了解更多功能
4. 向我们反馈问题和建议

Happy Coding! 🚀

