# pfinal-asyncio-http

> 基于 `pfinalclub/asyncio` 的异步 HTTP 客户端，提供 PSR-7/18 支持

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://www.php.net/)

## ✨ 特性

- 🚀 **真正的异步** - 基于 PHP 8.1+ Fiber，性能卓越
- ⚡ **零配置并发** - 内置 `gather` 和 `semaphore` 支持
- 📦 **PSR 标准** - 完全符合 PSR-7/18 规范
- 🔧 **中间件系统** - 灵活的洋葱模型中间件
- 🎯 **简洁 API** - 类似 `requests` 库的直观接口
- 🔄 **连接复用** - 自动 HTTP Keep-Alive
- 🛡️ **异常处理** - 完整的错误传播机制

## 📋 要求

- **PHP >= 8.1** （需要 Fiber 支持）
- **pfinalclub/asyncio ^2.1** （底层异步引擎）
- **Workerman >= 4.1**（事件循环）

## 📦 安装

```bash
composer require pfinal/asyncio-http-psr
```

## 🚀 快速开始

### 基础请求

```php
<?php
require 'vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use function PfinalClub\Asyncio\run;

// 所有代码必须在 run() 函数内
run(function() {
$client = new Client();

// GET 请求
    $response = $client->get('https://api.example.com/users');
echo $response->getBody();

    // POST JSON
$response = $client->post('https://api.example.com/users', [
        'json' => ['name' => '张三', 'email' => 'zhangsan@example.com']
]);
    
    echo "状态码: {$response->getStatusCode()}\n";
});
```

### 并发请求

```php
use function PfinalClub\Asyncio\{run, create_task, gather};

run(function() {
    $client = new Client();
    
    // 创建并发任务
    $tasks = [
        create_task(fn() => $client->get('https://api.example.com/users/1')),
        create_task(fn() => $client->get('https://api.example.com/users/2')),
        create_task(fn() => $client->get('https://api.example.com/users/3')),
    ];
    
    // 并发执行，等待所有完成
    $responses = gather(...$tasks);
    
    foreach ($responses as $response) {
        echo "状态码: {$response->getStatusCode()}\n";
    }
});
```

### Pool 批量请求

```php
use PFinal\AsyncioHttp\Pool;

run(function() {
    $client = new Client();
    
    // 创建 100 个请求
    $requests = [];
        for ($i = 1; $i <= 100; $i++) {
        $requests[] = fn() => $client->get("https://api.example.com/users/{$i}");
    }
    
    // 限制并发数为 25
    $results = Pool::batch($client, $requests, [
        'concurrency' => 25,
        'fulfilled' => fn($response, $index) => echo "✅ 请求 {$index} 成功\n",
        'rejected' => fn($e, $index) => echo "❌ 请求 {$index} 失败\n",
    ]);
    
    echo "成功: " . count(array_filter($results, fn($r) => $r['state'] === 'fulfilled')) . " 个\n";
});
```

## 📖 核心概念

### 为什么没有 `Async` 方法？

与传统的 Promise-based 异步库不同，`pfinalclub/asyncio` 基于 **PHP Fiber**。在 Fiber 中，所有操作**看起来是同步的，实际是异步的**。

```php
// ❌ 传统方式（其他库）
$promise = $client->getAsync('https://api.example.com');
$response = $promise->wait();  // 需要 wait()

// ✅ pfinalclub/asyncio 方式
$response = $client->get('https://api.example.com');  // 直接调用，自动异步
```

### 并发 vs 串行

```php
run(function() {
    $client = new Client();
    
    // 串行执行（3 秒）
    $r1 = $client->get('https://httpbin.org/delay/1');
    $r2 = $client->get('https://httpbin.org/delay/1');
    $r3 = $client->get('https://httpbin.org/delay/1');
    
    // 并发执行（1 秒）
    $tasks = [
        create_task(fn() => $client->get('https://httpbin.org/delay/1')),
        create_task(fn() => $client->get('https://httpbin.org/delay/1')),
        create_task(fn() => $client->get('https://httpbin.org/delay/1')),
    ];
    $responses = gather(...$tasks);
});
```

## 🔧 高级用法

### 中间件

```php
use PFinal\AsyncioHttp\Handler\{HandlerStack, AsyncioHandler};
use PFinal\AsyncioHttp\Middleware\{RetryMiddleware, RedirectMiddleware};

run(function() {
    // 创建自定义处理器栈
    $handler = new AsyncioHandler();
    $stack = HandlerStack::create($handler);
    
    // 添加重试中间件
    $stack->push(new RetryMiddleware([
        'max' => 3,
        'delay' => RetryMiddleware::exponentialBackoff(500, 5000),
    ]), 'retry');
    
    // 添加重定向中间件
    $stack->push(new RedirectMiddleware(['max' => 5]), 'redirect');
    
    $client = new Client(['handler' => $stack]);
    
    // 请求会自动重试和处理重定向
    $response = $client->get('https://api.example.com/data');
});
```

### 内置中间件

- `RetryMiddleware` - 自动重试失败请求
- `RedirectMiddleware` - 处理 HTTP 重定向
- `AuthMiddleware` - Basic/Bearer 认证
- `CookieMiddleware` - Cookie 管理
- `LogMiddleware` - 请求日志
- `HistoryMiddleware` - 请求历史记录
- `HttpErrorsMiddleware` - HTTP 错误异常化

### 请求选项

```php
$response = $client->request('POST', 'https://api.example.com/data', [
    // 查询参数
    'query' => ['page' => 1, 'limit' => 20],
    
    // 请求头
    'headers' => [
        'User-Agent' => 'My-App/1.0',
        'Accept' => 'application/json',
    ],
    
    // JSON 请求体
    'json' => ['name' => '李四', 'age' => 30],
    
    // 表单请求体
    'form_params' => ['username' => 'lisi', 'password' => '123456'],
    
    // 原始请求体
    'body' => 'raw data',
    
    // 超时（秒）
    'timeout' => 10,
    
    // SSL 验证
    'verify' => true,
    
    // 重试配置
    'retry' => [
        'max' => 3,
        'delay' => 1000,  // 毫秒
    ],
    
    // 重定向配置
    'allow_redirects' => [
        'max' => 5,
        'strict' => false,
    ],
]);
```

## 🎯 实际应用

### API 客户端

```php
class GitHubClient
{
    private Client $client;
    
    public function __construct(string $token)
    {
        $this->client = new Client([
            'base_uri' => 'https://api.github.com',
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github.v3+json',
            ],
            'timeout' => 10,
        ]);
    }
    
    public function getUser(string $username): array
    {
        $response = $this->client->get("/users/{$username}");
        return json_decode($response->getBody(), true);
    }
    
    public function getRepos(string $username): array
    {
        // 并发获取多页
        $tasks = [];
        for ($page = 1; $page <= 3; $page++) {
            $tasks[] = create_task(fn() => $this->client->get("/users/{$username}/repos", [
                'query' => ['page' => $page, 'per_page' => 100]
            ]));
        }
        
        $responses = gather(...$tasks);
        
        $repos = [];
        foreach ($responses as $response) {
            $repos = array_merge($repos, json_decode($response->getBody(), true));
        }
        
        return $repos;
    }
}

// 使用
run(function() {
    $github = new GitHubClient('your-token');
    
    $user = $github->getUser('octocat');
    echo "用户: {$user['name']}\n";
    
    $repos = $github->getRepos('octocat');
    echo "仓库数: " . count($repos) . "\n";
});
```

### 网页爬虫

```php
run(function() {
    $client = new Client(['timeout' => 10]);
    
    // 获取首页链接
    $response = $client->get('https://news.example.com');
    preg_match_all('/<a href="(.*?)">/', $response->getBody(), $matches);
    $links = array_slice($matches[1], 0, 50);
    
    // 并发抓取所有链接（限制 10 个并发）
    $tasks = [];
    foreach ($links as $link) {
        $tasks[] = fn() => $client->get($link);
    }
    
    $results = Pool::batch($client, $tasks, [
        'concurrency' => 10,
        'fulfilled' => fn($response, $index) => echo "✅ 抓取: {$links[$index]}\n",
        'rejected' => fn($e, $index) => echo "❌ 失败: {$links[$index]}\n",
    ]);
    
    echo "抓取完成: " . count($results) . " 个页面\n";
});
```

## 🔍 与其他库对比

| 特性 | pfinal-asyncio-http | Guzzle | ReactPHP |
|------|---------------------|--------|----------|
| 基础技术 | PHP Fiber | cURL | Event Loop |
| 异步模型 | 原生协程 | 同步 | Callback/Promise |
| 代码风格 | 同步风格（实际异步） | 同步 | 回调风格 |
| 性能 | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| 学习曲线 | 低 | 低 | 高 |
| 并发控制 | 内置 | 手动 | 复杂 |

## 📚 更多示例

查看 `examples/` 目录获取更多示例：

- `01_basic_request.php` - 基础请求
- `02_concurrent_requests.php` - 并发请求
- `03_pool_example.php` - Pool 使用
- `04_middleware_auth.php` - 中间件
- `05_retry_middleware.php` - 重试策略

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📄 协议

MIT License

## 🔗 相关链接

- [pfinalclub/asyncio](https://github.com/pfinalclub/asyncio) - 底层异步引擎
- [PSR-7](https://www.php-fig.org/psr/psr-7/) - HTTP 消息接口
- [PSR-18](https://www.php-fig.org/psr/psr-18/) - HTTP 客户端接口
- [Workerman](https://www.workerman.net/) - 高性能事件循环

---

**注意：本项目基于 Fiber，必须在 `run()` 函数内使用。所有操作在 Fiber 中自动异步，无需手动管理 Promise 或回调。**
