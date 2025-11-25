# 🌐 pfinal-asyncio 生态系统集成指南

## 📖 概述

`asyncio-http-core` 是 **pfinal-asyncio 生态系统**的官方 HTTP 客户端扩展包。本文档说明如何将此包与生态系统中的其他包集成使用。

## 🏗️ 生态系统架构

```
┌─────────────────────────────────────────────────────────┐
│                  pfinalclub/asyncio                      │
│              (核心异步运行时 v3.0+)                       │
│   ✅ Fiber 协程  ✅ 事件循环  ✅ 任务调度                  │
└────────────────────┬────────────────────────────────────┘
                     │
        ┌────────────┼────────────┬────────────┐
        │            │            │            │
┌───────▼───────┐ ┌──▼──────┐ ┌──▼─────────┐ │
│ asyncio-      │ │ asyncio-│ │ asyncio-   │ │
│ http-core     │ │ database│ │ redis      │ ...
│ (HTTP 客户端) │ │ (数据库) │ │ (Redis)    │
└───────────────┘ └─────────┘ └────────────┘
```

## 📦 生态系统包

### 核心包

#### [pfinalclub/asyncio](https://github.com/pfinalclub/pfinal-asyncio) `^3.0`

**核心异步运行时**

提供基础设施：
- ✅ 事件循环 (Event Loop)
- ✅ 协程调度 (Coroutine Scheduler)
- ✅ 任务管理 (Task Management)
- ✅ 信号量 (Semaphore)
- ✅ 上下文管理 (Context)

```bash
composer require pfinalclub/asyncio:^3.0
```

### 扩展包

#### [pfinalclub/asyncio-http-core](https://github.com/pfinalclub/asyncio-http-core) `^3.0`

**异步 HTTP 客户端** (本包)

特性：
- ✅ PSR-7/PSR-18 标准
- ✅ 中间件系统
- ✅ 连接复用
- ✅ 并发控制

```bash
composer require pfinalclub/asyncio-http-core:^3.0
```

#### [pfinalclub/asyncio-database](https://github.com/pfinalclub/asyncio-database) `^3.0`

**异步数据库连接池**

特性：
- ✅ PDO 连接池
- ✅ 查询构建器
- ✅ 事务支持
- ✅ 自动重连

```bash
composer require pfinalclub/asyncio-database:^3.0
```

#### [pfinalclub/asyncio-redis](https://github.com/pfinalclub/asyncio-redis) `^3.0`

**异步 Redis 客户端**

特性：
- ✅ Redis 连接池
- ✅ 管道支持
- ✅ 发布订阅
- ✅ 集群支持

```bash
composer require pfinalclub/asyncio-redis:^3.0
```

## 🚀 集成示例

### 示例 1: HTTP + Database

构建一个异步 Web API，从数据库获取数据并返回 JSON：

```php
<?php
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioDatabase\Pool as DbPool;
use function PfinalClub\Asyncio\{run, create_task, gather};

run(function() {
    // 初始化 HTTP 客户端
    $httpClient = new Client();
    
    // 初始化数据库连接池
    $dbPool = new DbPool([
        'host' => 'localhost',
        'database' => 'myapp',
        'username' => 'root',
        'password' => 'secret',
        'pool_size' => 10,
    ]);
    
    // 并发执行：API 请求 + 数据库查询
    [$apiData, $dbData] = gather(
        create_task(fn() => $httpClient->get('https://api.example.com/config')),
        create_task(fn() => $dbPool->query('SELECT * FROM users LIMIT 10'))
    );
    
    echo "API Response: {$apiData->getStatusCode()}\n";
    echo "DB Records: " . count($dbData) . "\n";
});
```

### 示例 2: HTTP + Redis

使用 Redis 缓存 HTTP 请求结果：

```php
<?php
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioRedis\Pool as RedisPool;
use function PfinalClub\Asyncio\run;

run(function() {
    $httpClient = new Client();
    $redisPool = new RedisPool([
        'host' => 'localhost',
        'port' => 6379,
        'pool_size' => 5,
    ]);
    
    $url = 'https://api.github.com/users/octocat';
    $cacheKey = 'user:octocat';
    
    // 尝试从 Redis 获取缓存
    $cached = $redisPool->get($cacheKey);
    
    if ($cached) {
        echo "✅ Cache hit!\n";
        $data = json_decode($cached, true);
    } else {
        echo "❌ Cache miss, fetching from API...\n";
        $response = $httpClient->get($url);
        $data = json_decode($response->getBody(), true);
        
        // 缓存 1 小时
        $redisPool->setex($cacheKey, 3600, json_encode($data));
    }
    
    echo "User: {$data['name']}\n";
});
```

### 示例 3: 完整的微服务示例

HTTP API + 数据库 + Redis + 并发处理：

```php
<?php
use PFinal\AsyncioHttp\{Client, Pool as HttpPool};
use PFinal\AsyncioDatabase\Pool as DbPool;
use PFinal\AsyncioRedis\Pool as RedisPool;
use function PfinalClub\Asyncio\{run, create_task, gather, semaphore};

run(function() {
    // 初始化所有服务
    $http = new Client(['timeout' => 5]);
    $db = new DbPool(['host' => 'localhost', 'database' => 'app']);
    $redis = new RedisPool(['host' => 'localhost']);
    
    // 获取用户 ID 列表
    $userIds = $db->query('SELECT id FROM users WHERE active = 1 LIMIT 100');
    
    // 限制并发数为 20
    $sem = semaphore(20);
    
    $tasks = [];
    foreach ($userIds as $row) {
        $userId = $row['id'];
        
        $tasks[] = create_task(async function() use ($userId, $http, $db, $redis, $sem) {
            async with ($sem) {  // 控制并发
                // 检查 Redis 缓存
                $cached = await $redis->get("user_profile:{$userId}");
                if ($cached) {
                    return json_decode($cached, true);
                }
                
                // 并发获取用户信息和订单
                [$profile, $orders] = gather(
                    create_task(fn() => $http->get("https://api.example.com/users/{$userId}")),
                    create_task(fn() => $db->query("SELECT * FROM orders WHERE user_id = ?", [$userId]))
                );
                
                $data = [
                    'profile' => json_decode($profile->getBody(), true),
                    'orders' => $orders,
                ];
                
                // 缓存结果
                $redis->setex("user_profile:{$userId}", 600, json_encode($data));
                
                return $data;
            }
        });
    }
    
    // 等待所有任务完成
    $results = gather(...$tasks);
    
    echo "✅ Processed " . count($results) . " users\n";
});
```

## 🔧 高级集成模式

### 模式 1: 服务类封装

将生态系统包封装为服务类：

```php
<?php

namespace App\Services;

use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioDatabase\Pool as DbPool;
use PFinal\AsyncioRedis\Pool as RedisPool;

class ServiceContainer
{
    private static ?self $instance = null;
    
    private Client $http;
    private DbPool $db;
    private RedisPool $redis;
    
    private function __construct()
    {
        $this->http = new Client([
            'timeout' => 10,
            'headers' => ['User-Agent' => 'MyApp/1.0'],
        ]);
        
        $this->db = new DbPool([
            'host' => getenv('DB_HOST'),
            'database' => getenv('DB_NAME'),
            'username' => getenv('DB_USER'),
            'password' => getenv('DB_PASS'),
            'pool_size' => 20,
        ]);
        
        $this->redis = new RedisPool([
            'host' => getenv('REDIS_HOST'),
            'port' => (int)getenv('REDIS_PORT'),
            'pool_size' => 10,
        ]);
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function http(): Client { return $this->http; }
    public function db(): DbPool { return $this->db; }
    public function redis(): RedisPool { return $this->redis; }
}

// 使用
use function PfinalClub\Asyncio\run;

run(function() {
    $services = ServiceContainer::getInstance();
    
    $response = $services->http()->get('https://api.example.com');
    $users = $services->db()->query('SELECT * FROM users');
    $cached = $services->redis()->get('config');
});
```

### 模式 2: 依赖注入

使用构造函数注入：

```php
<?php

namespace App\Repositories;

use PFinal\AsyncioDatabase\Pool as DbPool;

class UserRepository
{
    public function __construct(private DbPool $db) {}
    
    public function findById(int $id): ?array
    {
        $rows = $this->db->query('SELECT * FROM users WHERE id = ?', [$id]);
        return $rows[0] ?? null;
    }
    
    public function findAll(): array
    {
        return $this->db->query('SELECT * FROM users');
    }
}

namespace App\Services;

use PFinal\AsyncioHttp\Client;
use App\Repositories\UserRepository;

class UserService
{
    public function __construct(
        private Client $http,
        private UserRepository $userRepo
    ) {}
    
    public function syncUserFromApi(int $userId): array
    {
        // 从 API 获取用户信息
        $response = $this->http->get("https://api.example.com/users/{$userId}");
        $apiData = json_decode($response->getBody(), true);
        
        // 保存到数据库
        $this->userRepo->save($apiData);
        
        return $apiData;
    }
}
```

## 🎯 最佳实践

### 1. 连接池配置

根据负载调整连接池大小：

```php
// 低负载 (< 100 req/s)
$dbPool = new DbPool(['pool_size' => 5]);
$redisPool = new RedisPool(['pool_size' => 3]);

// 中负载 (100-1000 req/s)
$dbPool = new DbPool(['pool_size' => 20]);
$redisPool = new RedisPool(['pool_size' => 10]);

// 高负载 (> 1000 req/s)
$dbPool = new DbPool(['pool_size' => 50]);
$redisPool = new RedisPool(['pool_size' => 25]);
```

### 2. 并发控制

使用 Semaphore 限制并发：

```php
use function PfinalClub\Asyncio\{run, create_task, gather, semaphore};

run(function() {
    $sem = semaphore(10);  // 限制 10 个并发
    
    $tasks = [];
    for ($i = 0; $i < 100; $i++) {
        $tasks[] = create_task(async function() use ($sem, $i) {
            async with ($sem) {
                // 这里最多同时执行 10 个
                $response = await $client->get("https://api.example.com/item/{$i}");
                return $response;
            }
        });
    }
    
    gather(...$tasks);
});
```

### 3. 错误处理

统一的错误处理策略：

```php
use PFinal\AsyncioHttp\Exception\{RequestException, TimeoutException};
use function PfinalClub\Asyncio\{run, create_task, gather};

run(function() {
    $tasks = [];
    for ($i = 1; $i <= 10; $i++) {
        $tasks[] = create_task(async function() use ($i) {
            try {
                $response = await $client->get("https://api.example.com/item/{$i}");
                return ['success' => true, 'data' => $response];
            } catch (TimeoutException $e) {
                return ['success' => false, 'error' => 'timeout'];
            } catch (RequestException $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        });
    }
    
    $results = gather(...$tasks);
    
    $successCount = count(array_filter($results, fn($r) => $r['success']));
    echo "Success: {$successCount}/10\n";
});
```

## 📚 相关资源

- [pfinal-asyncio 主文档](https://github.com/pfinalclub/pfinal-asyncio#readme)
- [HTTP Core 文档](README.md)
- [Database Pool 文档](https://github.com/pfinalclub/asyncio-database#readme)
- [Redis Pool 文档](https://github.com/pfinalclub/asyncio-redis#readme)
- [示例代码库](examples/)

## 🤝 生态系统贡献

如果你想为 pfinal-asyncio 生态系统贡献新的扩展包，请参考：

- [生态系统贡献指南](https://github.com/pfinalclub/pfinal-asyncio/blob/master/ECOSYSTEM_CONTRIBUTION.md)
- [扩展包开发模板](https://github.com/pfinalclub/asyncio-package-template)

## 📞 支持

- **Issues**: [GitHub Issues](https://github.com/pfinalclub/asyncio-http-core/issues)
- **Discussions**: [GitHub Discussions](https://github.com/pfinalclub/pfinal-asyncio/discussions)
- **Parent Project**: [pfinal-asyncio](https://github.com/pfinalclub/pfinal-asyncio)

---

<div align="center">

**pfinal-asyncio 生态系统** - 为 PHP 带来真正的异步编程体验

</div>

