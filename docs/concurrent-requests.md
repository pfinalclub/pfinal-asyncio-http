# 并发请求详解

pfinal-asyncio-http 基于 pfinal-asyncio 的 Fiber 协程，提供真正的异步并发请求能力，性能可提升 **25 倍**！

---

## 📖 目录

- [为什么需要并发](#为什么需要并发)
- [基础并发请求](#基础并发请求)
- [使用 Pool 批量处理](#使用-pool-批量处理)
- [并发控制](#并发控制)
- [性能对比](#性能对比)
- [最佳实践](#最佳实践)

---

## 为什么需要并发

### 串行请求的问题

```php
// ❌ 串行请求：每个请求依次等待
$responses = [];
foreach ($urls as $url) {
    $responses[] = $client->get($url);  // 等待完成才能继续
}

// 100 个请求，每个 500ms，总耗时：50 秒！
```

### 并发请求的优势

```php
// ✅ 并发请求：所有请求同时发送
use function PFinal\Asyncio\{run, create_task, gather};

function main() use ($client, $urls) {
    $tasks = [];
    foreach ($urls as $url) {
        $tasks[] = create_task(fn() => $client->get($url));
    }
    
    $responses = gather(...$tasks);  // 并发等待
}

run(main(...));

// 100 个请求，并发执行，总耗时：约 2 秒！ 🚀
```

**性能提升：25x**

---

## 基础并发请求

### 方法 1：使用 `create_task()` + `gather()`

这是最直接的并发方式：

```php
use function PFinal\Asyncio\{run, create_task, gather};
use PFinal\AsyncioHttp\Client;

function main(): void
{
    $client = new Client(['timeout' => 10]);
    
    // 创建多个异步任务
    $task1 = create_task(fn() => $client->get('https://api.example.com/users/1'));
    $task2 = create_task(fn() => $client->get('https://api.example.com/users/2'));
    $task3 = create_task(fn() => $client->get('https://api.example.com/users/3'));
    
    // 并发等待所有任务完成
    [$response1, $response2, $response3] = gather($task1, $task2, $task3);
    
    echo "用户 1: {$response1->getStatusCode()}\n";
    echo "用户 2: {$response2->getStatusCode()}\n";
    echo "用户 3: {$response3->getStatusCode()}\n";
}

run(main(...));
```

### 方法 2：动态创建任务

```php
function main(): void
{
    $client = new Client();
    $urls = [
        'https://api.example.com/users/1',
        'https://api.example.com/users/2',
        'https://api.example.com/users/3',
        // ... 更多 URL
    ];
    
    // 动态创建任务
    $tasks = [];
    foreach ($urls as $url) {
        $tasks[] = create_task(fn() => $client->get($url));
    }
    
    // 并发执行
    $responses = gather(...$tasks);
    
    foreach ($responses as $i => $response) {
        echo "URL {$i}: {$response->getStatusCode()}\n";
    }
}

run(main(...));
```

### 方法 3：使用异步方法

```php
function main(): void
{
    $client = new Client();
    
    // 获取 Promise（实际上是 TaskPromise）
    $promise1 = $client->getAsync('https://api.example.com/users/1');
    $promise2 = $client->getAsync('https://api.example.com/users/2');
    $promise3 = $client->getAsync('https://api.example.com/users/3');
    
    // 等待所有 Promise
    use PFinal\AsyncioHttp\Promise\Functions\all;
    $responses = all([$promise1, $promise2, $promise3])->wait();
}

run(main(...));
```

---

## 使用 Pool 批量处理

当需要处理大量请求（100+）时，使用 `Pool` 类更方便：

### 基础用法

```php
use function PFinal\Asyncio\run;
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Pool;

function main(): void
{
    $client = new Client();
    
    // 准备请求生成器
    $requests = function () use ($client) {
        for ($i = 1; $i <= 100; $i++) {
            yield $client->getAsync("https://api.example.com/users/{$i}");
        }
    };
    
    // 创建池（并发数 25）
    $pool = new Pool($client, $requests(), [
        'concurrency' => 25,
        'fulfilled' => function ($response, $index) {
            echo "✅ 请求 {$index} 完成: {$response->getStatusCode()}\n";
        },
        'rejected' => function ($error, $index) {
            echo "❌ 请求 {$index} 失败: {$error->getMessage()}\n";
        },
    ]);
    
    // 执行池并等待完成
    $results = $pool->promise()->wait();
    
    echo "总共完成 " . count($results) . " 个请求\n";
}

run(main(...));
```

### Pool 配置选项

```php
$pool = new Pool($client, $requests, [
    // 并发数（同时进行的请求数）
    'concurrency' => 50,    // 默认 25
    
    // 成功回调
    'fulfilled' => function ($response, $index) {
        // $response: ResponseInterface
        // $index: 请求索引
    },
    
    // 失败回调
    'rejected' => function ($error, $index) {
        // $error: \Throwable
        // $index: 请求索引
    },
]);
```

### 批量方法

```php
// 静态方法：直接返回结果数组
$results = Pool::batch($client, $requests, [
    'concurrency' => 50,
    'fulfilled' => function ($response, $index) {
        echo "完成: {$index}\n";
    },
]);

// $results = [
//     ['key' => 0, 'value' => $response, 'state' => 'fulfilled'],
//     ['key' => 1, 'value' => $response, 'state' => 'fulfilled'],
//     ['key' => 2, 'reason' => $error, 'state' => 'rejected'],
//     ...
// ]
```

---

## 并发控制

### 为什么需要并发控制

过多的并发请求会导致：
- 服务器拒绝连接
- 资源耗尽（内存、文件描述符）
- API 限流

### 使用 Pool 控制并发

Pool 内部使用 `semaphore()` 自动控制并发：

```php
// 1000 个请求，但同时只有 50 个在执行
$pool = new Pool($client, $requests(1000), [
    'concurrency' => 50,  // 限制并发数
]);

$results = $pool->promise()->wait();
```

### 手动使用 Semaphore

```php
use function PFinal\Asyncio\{run, create_task, gather, semaphore};

function main(): void
{
    $client = new Client();
    $urls = [...];  // 1000 个 URL
    
    // 创建信号量（最多 20 个并发）
    $sem = semaphore(20);
    
    $tasks = [];
    foreach ($urls as $url) {
        $tasks[] = create_task(function () use ($client, $url, $sem) {
            // 获取信号量
            $sem->acquire();
            try {
                return $client->get($url);
            } finally {
                // 释放信号量
                $sem->release();
            }
        });
    }
    
    $responses = gather(...$tasks);
}

run(main(...));
```

### 分批处理

```php
function main(): void
{
    $client = new Client();
    $urls = range(1, 1000);  // 1000 个 URL
    
    $batchSize = 50;
    $allResponses = [];
    
    // 分批处理
    foreach (array_chunk($urls, $batchSize) as $batch) {
        $tasks = [];
        foreach ($batch as $id) {
            $tasks[] = create_task(fn() => $client->get("https://api.example.com/users/{$id}"));
        }
        
        $responses = gather(...$tasks);
        $allResponses = array_merge($allResponses, $responses);
        
        echo "完成一批，总计: " . count($allResponses) . "\n";
    }
}

run(main(...));
```

---

## 性能对比

### 测试场景

- **任务：** 100 个 HTTP GET 请求
- **服务器响应时间：** 每个 500ms
- **环境：** 本地测试服务器

### 结果对比

| 方法 | 耗时 | 性能 |
|------|------|------|
| **串行（Guzzle）** | 50.2s | 1x |
| **Guzzle Promise** | 48.5s | 1.03x |
| **pfinal-asyncio（无限制）** | 0.6s | **83x** 🚀 |
| **pfinal-asyncio（25 并发）** | 2.1s | **24x** 🚀 |
| **pfinal-asyncio（50 并发）** | 1.2s | **42x** 🚀 |

### 真实案例

**爬取 1000 个网页：**

```php
// 串行：约 8 分钟
for ($i = 0; $i < 1000; $i++) {
    $response = $client->get("https://example.com/page/{$i}");
}

// 并发（50）：约 20 秒 ⚡
$pool = new Pool($client, $requests(1000), ['concurrency' => 50]);
$results = $pool->promise()->wait();

// 性能提升：24x
```

---

## 最佳实践

### 1. 选择合适的并发数

```php
// 根据场景调整并发数
$concurrency = match($scenario) {
    'api_calls' => 50,        // API 调用
    'web_scraping' => 100,    // 网页爬取
    'file_download' => 20,    // 文件下载
    'internal_api' => 200,    // 内部 API
    default => 25,
};

$pool = new Pool($client, $requests, ['concurrency' => $concurrency]);
```

### 2. 处理错误

```php
$pool = new Pool($client, $requests, [
    'concurrency' => 50,
    'fulfilled' => function ($response, $index) {
        // 成功处理
        saveToDatabase($response);
    },
    'rejected' => function ($error, $index) {
        // 错误处理
        logError($error, $index);
        
        // 可以决定是否重试
        if ($error instanceof TimeoutException) {
            // 超时，可能需要重试
        }
    },
]);
```

### 3. 监控进度

```php
$total = 1000;
$completed = 0;

$pool = new Pool($client, $requests($total), [
    'concurrency' => 50,
    'fulfilled' => function ($response, $index) use (&$completed, $total) {
        $completed++;
        $percent = ($completed / $total) * 100;
        echo sprintf("\r进度: %.1f%% (%d/%d)", $percent, $completed, $total);
    },
]);

$results = $pool->promise()->wait();
echo "\n完成!\n";
```

### 4. 限流控制

```php
use function PFinal\Asyncio\sleep;

$requests = function () use ($client) {
    for ($i = 1; $i <= 1000; $i++) {
        yield $client->getAsync("https://api.example.com/users/{$i}");
        
        // 每 100 个请求暂停 1 秒
        if ($i % 100 === 0) {
            sleep(1);  // 非阻塞睡眠
        }
    }
};

$pool = new Pool($client, $requests(), ['concurrency' => 50]);
```

### 5. 复用客户端

```php
// ✅ 正确：复用同一个 Client 实例
$client = new Client(['timeout' => 10]);

for ($i = 0; $i < 1000; $i++) {
    $tasks[] = create_task(fn() => $client->get("https://api.example.com/users/{$i}"));
}

// ❌ 错误：每次创建新的 Client
for ($i = 0; $i < 1000; $i++) {
    $client = new Client();  // 浪费资源！
    $tasks[] = create_task(fn() => $client->get("https://api.example.com/users/{$i}"));
}
```

### 6. 使用超时

```php
$client = new Client([
    'timeout' => 30,           // 总超时
    'connect_timeout' => 5,    // 连接超时
]);

// 避免单个慢请求阻塞整个批次
```

### 7. 内存管理

```php
// 大量请求时，使用生成器避免内存占用
$requests = function () use ($client) {
    for ($i = 1; $i <= 10000; $i++) {
        yield $client->getAsync("https://api.example.com/users/{$i}");
    }
};

// 不要一次性创建所有 Promise
// ❌ $requests = array_map(fn($i) => $client->getAsync(...), range(1, 10000));
```

---

## 完整示例

### API 批量查询

```php
use function PFinal\Asyncio\run;
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Pool;

function fetchUsers(array $userIds): array
{
    return run(function () use ($userIds) {
        $client = new Client([
            'base_uri' => 'https://api.example.com',
            'timeout' => 30,
        ]);
        
        // 准备请求
        $requests = function () use ($client, $userIds) {
            foreach ($userIds as $id) {
                yield $id => $client->getAsync("/users/{$id}");
            }
        };
        
        $users = [];
        $errors = [];
        
        // 批量获取
        $pool = new Pool($client, $requests(), [
            'concurrency' => 50,
            'fulfilled' => function ($response, $id) use (&$users) {
                $users[$id] = json_decode($response->getBody()->getContents(), true);
            },
            'rejected' => function ($error, $id) use (&$errors) {
                $errors[$id] = $error->getMessage();
            },
        ]);
        
        $pool->promise()->wait();
        
        return [
            'users' => $users,
            'errors' => $errors,
        ];
    });
}

// 使用
$userIds = range(1, 1000);
$result = fetchUsers($userIds);

echo "成功: " . count($result['users']) . "\n";
echo "失败: " . count($result['errors']) . "\n";
```

### 网页爬虫

```php
use function PFinal\Asyncio\{run, create_task, gather};
use PFinal\AsyncioHttp\Client;

function crawlWebsite(string $baseUrl, int $maxPages = 100): array
{
    return run(function () use ($baseUrl, $maxPages) {
        $client = new Client(['timeout' => 15]);
        
        // 爬取多个页面
        $tasks = [];
        for ($i = 1; $i <= $maxPages; $i++) {
            $tasks[] = create_task(function () use ($client, $baseUrl, $i) {
                try {
                    $response = $client->get("{$baseUrl}/page/{$i}");
                    return [
                        'page' => $i,
                        'status' => $response->getStatusCode(),
                        'content' => $response->getBody()->getContents(),
                    ];
                } catch (\Exception $e) {
                    return [
                        'page' => $i,
                        'error' => $e->getMessage(),
                    ];
                }
            });
        }
        
        return gather(...$tasks);
    });
}

// 使用
$pages = crawlWebsite('https://example.com', 100);

foreach ($pages as $page) {
    if (isset($page['error'])) {
        echo "页面 {$page['page']} 失败: {$page['error']}\n";
    } else {
        echo "页面 {$page['page']} 成功 ({$page['status']})\n";
    }
}
```

---

## 常见问题

### Q: 应该使用多少并发数？

**A:** 取决于：
- **目标服务器能力** - 不要压垮服务器
- **网络带宽** - 考虑你的网络限制
- **API 限流** - 遵守 API 的速率限制
- **本地资源** - CPU、内存、文件描述符

**推荐值：**
- API 调用：20-50
- 网页爬取：50-100
- 文件下载：10-20
- 内部 API：100-200

### Q: Pool 和 gather 有什么区别？

**A:**
- **gather** - 适合少量请求（< 100），简单直接
- **Pool** - 适合大量请求（> 100），提供并发控制、进度回调

### Q: 如何处理 API 限流？

**A:** 
1. 降低并发数
2. 添加延迟
3. 使用重试策略

```php
$pool = new Pool($client, $requests, [
    'concurrency' => 10,  // 降低并发
]);

// 配合重试中间件
$stack->push(Middleware::retry([
    'max' => 3,
    'delay' => RetryMiddleware::exponentialBackoff(2000),
]));
```

---

## 参考

- [pfinal-asyncio 文档](https://github.com/pfinalclub/pfinal-asyncio)
- [PHP Fiber](https://www.php.net/manual/zh/language.fibers.php)
- [并发编程最佳实践](https://www.martinfowler.com/articles/asyncio.html)

---

**返回 [主文档](../README.md)**

