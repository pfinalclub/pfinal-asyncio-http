# 示例

## 运行示例

所有示例都基于 `pfinalclub/asyncio`，必须在 `run()` 函数内执行。

```bash
php examples/01_basic_request.php
```

## 示例列表

| 文件 | 说明 |
|------|------|
| `01_basic_request.php` | 基础 HTTP 请求（GET/POST/Headers） |
| `02_concurrent_requests.php` | 并发请求（`create_task` + `gather`） |
| `03_pool_example.php` | Pool 批量请求（并发控制） |
| `04_middleware_auth.php` | 中间件使用（认证、重试、重定向） |
| `05_retry_middleware.php` | 重试策略（指数退避、线性退避） |

## 核心要点

### 1. 所有代码必须在 `run()` 内

```php
use function PfinalClub\Asyncio\run;

// ✅ 正确
run(function() {
    $client = new Client();
    $response = $client->get('https://api.example.com');
});

// ❌ 错误
$client = new Client();
$response = $client->get('https://api.example.com');  // 会报错
```

### 2. 并发使用 `create_task` + `gather`

```php
use function PfinalClub\Asyncio\{run, create_task, gather};

run(function() {
    $client = new Client();
    
    // 创建任务
    $tasks = [
        create_task(fn() => $client->get('https://api.example.com/1')),
        create_task(fn() => $client->get('https://api.example.com/2')),
    ];
    
    // 等待所有完成
    $responses = gather(...$tasks);
});
```

### 3. 批量请求使用 `Pool`

```php
use PFinal\AsyncioHttp\Pool;

run(function() {
    $client = new Client();
    
    $requests = [];
    for ($i = 0; $i < 100; $i++) {
        $requests[] = fn() => $client->get("https://api.example.com/{$i}");
    }
    
    // 限制 25 个并发
    $results = Pool::batch($client, $requests, [
        'concurrency' => 25,
    ]);
});
```

## 常见问题

### Q: 为什么没有 `getAsync()` 方法？

A: 在 Fiber 中，所有操作**看起来是同步的，实际是异步的**。直接调用 `get()` 就是异步的，不需要 `Async` 后缀。

### Q: 如何实现并发？

A: 使用 `create_task()` 创建任务，然后用 `gather()` 等待。

### Q: 如何控制并发数？

A: 使用 `Pool` 类，或者手动使用 `semaphore()`。

### Q: 能在普通代码中使用吗？

A: 不能，必须在 `run()` 函数内。这是 `pfinalclub/asyncio` 的设计。
