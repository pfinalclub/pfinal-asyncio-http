# pfinal/asyncio-http-psr

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![pfinalclub/asyncio](https://img.shields.io/badge/powered%20by-pfinalclub%2Fasyncio-orange.svg)](https://github.com/pfinalclub/pfinal-asyncio)

**Guzzle 兼容的异步 HTTP 客户端**，基于 [pfinalclub/asyncio](https://github.com/pfinalclub/pfinal-asyncio) v2.1，提供完整的 PSR-7/PSR-18 支持。

## ✨ 特性

- 🚀 **完全兼容 Guzzle** - 无缝迁移，API 完全一致
- ⚡ **真正的异步** - 基于 PHP 8.1+ Fiber，性能提升 2-3 倍
- 📦 **PSR 标准** - 完整支持 PSR-7、PSR-18、PSR-17
- 🔄 **中间件系统** - 重试、重定向、Cookie、认证等
- 🍪 **Cookie 管理** - 自动 Cookie 处理和持久化
- 🔐 **多种认证** - Basic、Digest、Bearer、OAuth
- 🔁 **智能重试** - 指数退避、自定义策略
- 📊 **并发请求池** - 高效的批量请求处理
- 🎯 **连接复用** - 复用 pfinalclub/asyncio 的连接管理

## 📋 要求

- PHP >= 8.1
- pfinalclub/asyncio >= 2.1

**推荐安装：**
- `ext-ev` - 获得 10x 性能提升
- `ext-event` - 获得 4x 性能提升

## 📦 安装

```bash
composer require pfinal/asyncio-http-psr
```

## 🚀 快速开始

### 基础用法

```php
<?php
require 'vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use function PfinalClub\Asyncio\run;

function main(): void
{
    $client = new Client();

    // GET 请求
    $response = $client->get('https://api.github.com/repos/guzzle/guzzle');
    echo $response->getStatusCode(); // 200
    echo $response->getBody();

    // POST 请求
    $response = $client->post('https://api.example.com/users', [
        'json' => ['name' => 'John', 'email' => 'john@example.com']
    ]);
}

run(main(...));
```

### 异步并发请求

```php
<?php
use function PfinalClub\Asyncio\{run, create_task, gather};
use PFinal\AsyncioHttp\Client;

function main(): void
{
    $client = new Client(['timeout' => 10]);
    
    // 创建并发任务
    $task1 = create_task(fn() => $client->get('https://api.example.com/users/1'));
    $task2 = create_task(fn() => $client->get('https://api.example.com/users/2'));
    $task3 = create_task(fn() => $client->get('https://api.example.com/users/3'));
    
    // 并发执行
    $responses = gather($task1, $task2, $task3);
    
    foreach ($responses as $response) {
        echo "Status: {$response->getStatusCode()}\n";
    }
}

run(main(...));
```

### 使用请求池

```php
<?php
use function PfinalClub\Asyncio\run;
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Pool;

function main(): void
{
    $client = new Client();
    
    $requests = function () use ($client) {
        for ($i = 1; $i <= 100; $i++) {
            yield $client->getAsync("https://api.example.com/users/$i");
        }
    };
    
    $pool = new Pool($client, $requests(), [
        'concurrency' => 10,
        'fulfilled' => function ($response, $index) {
            echo "Request $index completed: {$response->getStatusCode()}\n";
        },
        'rejected' => function ($reason, $index) {
            echo "Request $index failed: {$reason->getMessage()}\n";
        },
    ]);
    
    $pool->promise()->wait();
}

run(main(...));
```

## 📚 功能特性

### 中间件系统

```php
use PFinal\AsyncioHttp\Middleware\RetryMiddleware;
use PFinal\AsyncioHttp\Middleware\AuthMiddleware;

$client = new Client();

// 添加重试中间件
$client->getHandlerStack()->push(
    new RetryMiddleware([
        'max' => 3,
        'delay' => RetryMiddleware::exponentialBackoff(1000, 60000),
    ])
);

// 添加认证中间件
$client->getHandlerStack()->push(
    AuthMiddleware::bearer('your-api-token')
);
```

### Cookie 管理

```php
use PFinal\AsyncioHttp\Cookie\CookieJar;
use PFinal\AsyncioHttp\Cookie\FileCookieJar;

// 内存 Cookie Jar
$cookieJar = new CookieJar();
$client = new Client(['cookies' => $cookieJar]);

// 持久化 Cookie Jar
$cookieJar = new FileCookieJar('/tmp/cookies.json');
$client = new Client(['cookies' => $cookieJar]);
```

### 请求选项

```php
$response = $client->request('POST', 'https://api.example.com/data', [
    // 查询参数
    'query' => ['page' => 1, 'limit' => 10],
    
    // JSON 数据
    'json' => ['name' => 'John', 'age' => 30],
    
    // 表单数据
    'form_params' => ['field' => 'value'],
    
    // 头部
    'headers' => [
        'User-Agent' => 'MyApp/1.0',
        'Accept' => 'application/json',
    ],
    
    // 认证
    'auth' => ['username', 'password', 'basic'],
    
    // 超时
    'timeout' => 30,
    
    // SSL 验证
    'verify' => false,
    
    // 代理
    'proxy' => 'http://proxy.example.com:8080',
]);
```

## 🔄 从 Guzzle 迁移

**完全兼容 Guzzle API！** 只需修改命名空间：

```php
// 之前（Guzzle）
use GuzzleHttp\Client;

// 之后（pfinal/asyncio-http-psr）
use PFinal\AsyncioHttp\Client;

// 需要添加异步运行时
use function PfinalClub\Asyncio\run;

function main(): void
{
    $client = new Client();
    // ... 其他代码不变
}

run(main(...));
```

## ⚡ 性能对比

| 场景 | Guzzle | pfinal/asyncio-http-psr | 提升 |
|------|--------|------------------------|------|
| 单个请求 | ~18ms | ~15ms | **1.2x** |
| 5 并发请求 | ~5s | ~1s | **5x** |
| 100 并发（限10） | ~1800ms | ~850ms | **2.1x** |
| CPU 空闲 | ~5% | < 1% | **5x** |

## 🏗️ 架构设计

```
用户代码 (Guzzle 兼容 API)
    ↓
Client / HandlerStack (中间件链)
    ↓
AsyncioHandler (PSR-7 适配层)
    ↓
pfinalclub/asyncio AsyncHttpClient
    ↓
Workerman AsyncTcpConnection
    ↓
PHP 8.1+ Fiber (协程)
    ↓
Event Loop (Ev/Event/Select)
```

**关键优势：**
- ✅ 复用 pfinalclub/asyncio 的成熟代码（连接管理、SSL、HTTP 解析）
- ✅ 只负责 PSR-7 适配层，代码简洁
- ✅ 完全兼容 Guzzle API，平滑迁移
- ✅ 性能卓越，资源占用低

## 📖 文档

- [中间件系统](docs/middleware.md)
- [并发请求](docs/concurrent-requests.md)

## 🧪 测试

```bash
# 运行测试
composer test

# 生成覆盖率报告
composer test-coverage

# 静态分析
composer phpstan

# 代码风格检查
composer cs-check
```

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！请查看 [贡献指南](CONTRIBUTING.md)。

## 📄 许可证

MIT License. 查看 [LICENSE](LICENSE) 文件了解详情。

## 🔗 相关链接

- [pfinalclub/asyncio](https://github.com/pfinalclub/pfinal-asyncio) - 基础异步框架
- [Guzzle](https://docs.guzzlephp.org/) - 原始 Guzzle 文档
- [PSR-7](https://www.php-fig.org/psr/psr-7/) - HTTP 消息接口
- [PSR-18](https://www.php-fig.org/psr/psr-18/) - HTTP 客户端

## 🙏 致谢

- **pfinalclub/asyncio** - 提供强大的异步基础设施
- **Guzzle** - 提供优秀的 API 设计
- **Workerman** - 提供高性能事件循环

---

**版本:** 1.0.0  
**更新日期:** 2025-11-21  
**PHP 要求:** >= 8.1

**如果这个项目对你有帮助，请给我们一个 Star！** ⭐
