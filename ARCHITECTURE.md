# 架构说明

## 核心设计原则

本项目基于 **pfinalclub/asyncio v2.1**，遵循其 Fiber-based 异步模型。

### 1. 无 Promise 抽象

**为什么移除 Promise？**

- `pfinalclub/asyncio` 的 `Task` 类已经实现了 Promise 的所有功能
- 在 Fiber 中，所有操作**看起来是同步的，实际是异步的**
- `AsyncHttpClient->request()` 直接返回 `HttpResponse`，不返回 Task 或 Promise
- 额外的 Promise 层是**不必要的抽象**，会增加复杂度和性能开销

```php
// ❌ 错误的设计（过度抽象）
$task = create_task(fn() => $client->get('https://api.com'));
$promise = new TaskPromise($task);  // 多余的包装
$response = $promise->wait();

// ✅ 正确的设计（简洁直接）
$response = $client->get('https://api.com');  // 在 Fiber 中自动异步
```

### 2. Client 只提供同步方法

所有方法在 Fiber 中调用时**自动是异步的**，无需 `Async` 后缀。

```php
// ❌ 传统异步库（需要区分同步/异步）
$promise = $client->getAsync('https://api.com');
$response = $promise->wait();

// ✅ pfinalclub/asyncio 方式（统一接口）
$response = $client->get('https://api.com');  // Fiber 中自动异步
```

### 3. 中间件直接处理 Request/Response

在 Fiber 中，中间件的 `$handler()` 调用会暂停当前 Fiber，等待响应完成，然后恢复执行。无需检查 Promise 或 Response 类型。

```php
// ✅ 简化后的中间件
public function __invoke(callable $handler): callable
{
    return function (RequestInterface $request, array $options) use ($handler) {
        $response = $handler($request, $options);  // 直接返回 ResponseInterface
        // ... 处理响应
        return $response;
    };
}
```

### 4. Pool 使用 gather + semaphore

直接利用 `pfinalclub/asyncio` 的 `gather` 和 `semaphore`，无需手动管理 Promise。

```php
$tasks = [];
$sem = semaphore($concurrency);

foreach ($requests as $request) {
    $tasks[] = create_task(function () use ($request, $sem) {
        $sem->acquire();
        try {
            return $request();
        } finally {
            $sem->release();
        }
    });
}

return gather(...$tasks);
```

## 核心组件

### Client

- 提供 PSR-7/18 兼容的 HTTP 客户端接口
- 在 Fiber 中自动异步
- 支持 base_uri, headers, timeout 等配置

### HandlerStack

- 洋葱模型中间件系统
- 支持 push, unshift, before, after, remove
- 直接返回 `ResponseInterface`

### AsyncioHandler

- 底层 HTTP 处理器
- 委托给 `pfinalclub/asyncio` 的 `AsyncHttpClient`
- 负责 PSR-7 和 AsyncHttpClient 之间的转换

### Pool

- 批量并发请求
- 使用 `semaphore` 控制并发数
- 支持 fulfilled/rejected 回调

### 中间件

- `RetryMiddleware` - 自动重试
- `RedirectMiddleware` - 重定向处理
- `HttpErrorsMiddleware` - HTTP 错误异常化
- `CookieMiddleware` - Cookie 管理
- `AuthMiddleware` - 认证
- `LogMiddleware` - 日志
- `HistoryMiddleware` - 历史记录

## 使用模式

### 必须在 run() 中使用

```php
use function PfinalClub\Asyncio\run;

run(function() {
    $client = new Client();
    $response = $client->get('https://api.example.com');
    // ...
});
```

### 并发请求

```php
use function PfinalClub\Asyncio\{create_task, gather};

run(function() {
    $client = new Client();
    
    $tasks = [
        create_task(fn() => $client->get('https://api1.com')),
        create_task(fn() => $client->get('https://api2.com')),
    ];
    
    $responses = gather(...$tasks);
});
```

### 批量请求

```php
use PFinal\AsyncioHttp\Pool;

run(function() {
    $client = new Client();
    
    $requests = [];
    for ($i = 0; $i < 100; $i++) {
        $requests[] = fn() => $client->get("https://api.com/{$i}");
    }
    
    $results = Pool::batch($client, $requests, [
        'concurrency' => 25,
    ]);
});
```

## 性能考虑

1. **零 Promise 开销** - 直接使用 Fiber，无额外抽象
2. **连接复用** - 底层 AsyncHttpClient 支持连接池
3. **并发控制** - semaphore 高效管理并发
4. **事件驱动** - 基于 Workerman 的高性能事件循环

## 与传统库对比

| 特性 | 本项目 | Guzzle | ReactPHP |
|------|--------|--------|----------|
| 异步模型 | Fiber (原生) | 同步 | Promise + Callback |
| 代码风格 | 同步风格（实际异步） | 同步 | 回调地狱 |
| Promise | ❌ 不需要 | ❌ 无 | ✅ 需要 |
| 学习曲线 | 低 | 低 | 高 |
| 性能 | 高 | 中 | 高 |
| 并发控制 | 内置 (semaphore) | 手动 | 复杂 |

## 总结

本项目的核心理念是：**在 Fiber 中，一切都是自然的**。

- 不需要 Promise
- 不需要 `Async` 后缀
- 不需要复杂的回调
- 只需要在 `run()` 中编写看似同步的代码，底层自动异步执行

这使得异步编程像写同步代码一样简单。

