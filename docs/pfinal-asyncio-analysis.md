# pfinal-asyncio 核心功能分析

**分析日期:** 2025-10-28  
**pfinal-asyncio 版本:** v2.0.3 (2025-01-21)  
**目的:** 深入了解 pfinalclub/asyncio 的核心功能，为 pfinal-asyncio-http 项目提供最佳实践指导

---

## 📊 项目概述

### 基本信息
- **项目名称:** pfinalclub/pfinal-asyncio
- **GitHub:** https://github.com/pfinalclub/pfinal-asyncio
- **许可证:** MIT
- **PHP 版本要求:** >= 8.1 (需要 Fiber 支持)
- **基础框架:** Workerman
- **核心技术:** PHP 8.1+ Fiber（真正的协程）

### 架构特点
- ✅ **完全事件驱动** - 移除所有轮询机制
- ✅ **零延迟恢复** - await/gather 直接恢复 Fiber
- ✅ **精确定时** - sleep() 直接使用 Timer
- ✅ **真正的协程** - 不是 Generator 模拟
- ✅ **性能优异** - 比 v1.x (Generator) 快 2-3 倍

---

## 🎯 核心 API（必须掌握）

### 1. 事件循环管理

#### `run(callable $callback): void`
**功能:** 启动事件循环并运行主函数  
**用途:** 所有异步代码的入口点

```php
use function PfinalClub\Asyncio\run;

function main(): void {
    // 你的异步代码
    echo "Hello Async World!\n";
}

run(main(...));  // 注意：v2.0 使用 callable，不是 Generator
```

**关键特性:**
- 自动启动 Workerman 事件循环
- 自动选择最优事件循环（Ev > Event > Select）
- 支持 Fiber 上下文
- 自动清理资源（每 100 个 Fiber 或 run() 结束时）

---

### 2. 任务创建和调度

#### `create_task(callable $callback): Task`
**功能:** 创建异步任务并立即调度  
**返回:** Task 对象

```php
use function PfinalClub\Asyncio\{run, create_task, sleep};

function main(): void {
    // 创建任务（立即开始执行）
    $task1 = create_task(function() {
        sleep(1);
        return "Task 1 完成";
    });
    
    $task2 = create_task(function() {
        sleep(2);
        return "Task 2 完成";
    });
    
    // 任务已在后台运行，可以继续其他工作
    echo "任务已启动，继续其他工作...\n";
}

run(main(...));
```

**Task 对象方法:**
- `getResult()` - 获取任务结果（阻塞直到完成）
- `isDone()` - 检查任务是否完成
- `isCancelled()` - 检查任务是否被取消

---

### 3. 并发执行（重要！）

#### `gather(...$tasks): array`
**功能:** 并发等待多个任务完成  
**返回:** 所有任务结果的数组（保持顺序）

```php
use function PfinalClub\Asyncio\{run, create_task, gather};

function main(): void {
    $task1 = create_task(fn() => fetch_user(1));
    $task2 = create_task(fn() => fetch_user(2));
    $task3 = create_task(fn() => fetch_user(3));
    
    // 并发等待所有任务完成
    $results = gather($task1, $task2, $task3);
    
    // $results = [user1, user2, user3]（按创建顺序）
}

run(main(...));
```

**性能特点:**
- 真正的并发执行（不是串行）
- 零延迟恢复（< 0.1ms）
- 适合批量 HTTP 请求

**⚠️ 重要:** 这是我们 HTTP 客户端并发请求的核心基础！

---

### 4. 异步等待

#### `await(Task $task): mixed`
**功能:** 等待单个任务完成并返回结果  
**用途:** 在需要结果时显式等待

```php
use function PfinalClub\Asyncio\{run, create_task, await};

function main(): void {
    $task = create_task(fn() => expensive_operation());
    
    // 做其他工作...
    
    // 现在需要结果了
    $result = await($task);
    echo "结果: $result\n";
}

run(main(...));
```

**延迟:** < 0.1ms（v2.0.1 优化）

---

### 5. 异步睡眠

#### `sleep(float $seconds): void`
**功能:** 异步睡眠（不阻塞事件循环）  
**精度:** ±0.1ms（v2.0.1 优化）

```php
use function PfinalClub\Asyncio\{run, sleep};

function main(): void {
    echo "开始\n";
    sleep(1.5);  // 睡眠 1.5 秒
    echo "1.5秒后\n";
}

run(main(...));
```

**用途:**
- 重试延迟
- 限流控制
- 超时处理

**⚠️ 注意:** 必须在 Fiber 上下文中调用（即 `run()` 或 `create_task()` 内部）

---

### 6. Future 对象

#### `create_future(): Future`
**功能:** 创建可手动设置结果的 Future 对象  
**用途:** 异步操作的结果占位符

```php
use function PfinalClub\Asyncio\{run, create_future, await_future};

function main(): void {
    $future = create_future();
    
    // 在某处设置结果
    create_task(function() use ($future) {
        sleep(1);
        $future->setResult("异步结果");
    });
    
    // 等待结果
    $result = await_future($future);
    echo $result; // "异步结果"
}

run(main(...));
```

**Future 方法:**
- `setResult($value)` - 设置成功结果
- `setException(\Throwable $e)` - 设置异常
- `isDone()` - 检查是否完成

---

## 🚦 并发控制（v2.0.3 新增 - 超级重要！）

### `semaphore(int $value): Semaphore`
**功能:** 创建信号量，限制并发任务数  
**用途:** 防止资源耗尽（如并发请求限制）

```php
use function PfinalClub\Asyncio\{run, create_task, gather, semaphore};

function main(): void {
    $sem = semaphore(5);  // 最多 5 个并发
    
    $tasks = [];
    for ($i = 0; $i < 100; $i++) {
        $tasks[] = create_task(function() use ($sem, $i) {
            $sem->acquire();  // 获取信号量（可能等待）
            try {
                // 执行任务（最多 5 个同时执行）
                echo "任务 $i 执行中\n";
                sleep(1);
            } finally {
                $sem->release();  // 释放信号量
            }
        });
    }
    
    gather(...$tasks);  // 等待所有任务完成
}

run(main(...));
```

**Semaphore 方法:**
- `acquire()` - 获取信号量（如果已满则等待）
- `release()` - 释放信号量
- `getValue()` - 获取当前可用数量

**🎯 我们的应用场景:**
- **并发请求池** - Pool 类中限制同时请求数
- **连接池管理** - 限制同时打开的连接数
- **限流控制** - 控制 API 调用速率

---

## 🌐 HTTP 客户端（AsyncHttpClient）

### 核心功能
pfinal-asyncio 已经提供了一个**完整的异步 HTTP 客户端**！

```php
use function PfinalClub\Asyncio\{run, create_task, gather};
use PfinalClub\Asyncio\Http\AsyncHttpClient;

function main(): void {
    $client = new AsyncHttpClient(['timeout' => 10]);
    
    // 单个请求
    $response = $client->get('https://api.example.com/users');
    echo "Status: {$response->getStatusCode()}\n";
    echo "Body: {$response->getBody()}\n";
    
    // 并发请求
    $task1 = create_task(fn() => $client->get('https://api.example.com/users/1'));
    $task2 = create_task(fn() => $client->get('https://api.example.com/users/2'));
    $task3 = create_task(fn() => $client->get('https://api.example.com/users/3'));
    
    $responses = gather($task1, $task2, $task3);
    
    foreach ($responses as $response) {
        echo "Status: {$response->getStatusCode()}\n";
    }
}

run(main(...));
```

### AsyncHttpClient 特性
- ✅ 支持 GET、POST、PUT、DELETE、PATCH、HEAD、OPTIONS
- ✅ 自动异步执行（基于 Workerman）
- ✅ 连接池管理（v2.0.2 新增）
- ✅ 连接统计和健康检查
- ✅ Keep-Alive 支持

### 构造函数选项
```php
$client = new AsyncHttpClient([
    'timeout' => 10,              // 超时时间（秒）
    'max_redirects' => 5,         // 最大重定向次数
    'verify_ssl' => true,         // SSL 验证
    // 更多选项...
]);
```

### 响应对象
```php
$response = $client->get('https://example.com');

// 方法
$response->getStatusCode();     // 200
$response->getHeaders();        // ['Content-Type' => ['text/html']]
$response->getBody();           // 响应体（字符串）
$response->getReasonPhrase();   // 'OK'
```

---

## 📊 性能监控（AsyncioMonitor）

### 功能
实时监控异步任务的性能指标

```php
use function PfinalClub\Asyncio\run;
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;

function main(): void {
    $monitor = AsyncioMonitor::getInstance();
    
    // 你的代码...
    
    // 显示监控报告
    echo $monitor->report();
    
    // 导出 JSON
    echo $monitor->toJson();
}

run(main(...));
```

### 监控指标
- 任务总数
- 任务完成数
- 任务失败数
- 平均执行时间
- 最慢任务
- 内存使用

### 高级功能（v2.0.2）
```php
use function PfinalClub\Asyncio\Monitor\{export_metrics, get_performance_snapshot, set_slow_task_threshold};

// 导出 JSON 格式指标
$json = export_metrics('json');

// 导出 Prometheus 格式指标
$prometheus = export_metrics('prometheus');

// 获取完整性能快照
$snapshot = get_performance_snapshot();

// 设置慢任务阈值（默认 1.0 秒）
set_slow_task_threshold(2.0);
```

---

## 🐛 调试器（AsyncioDebugger）

### 功能
调试异步任务的执行流程

```php
use function PfinalClub\Asyncio\run;
use PfinalClub\Asyncio\Debug\AsyncioDebugger;

function main(): void {
    $debugger = AsyncioDebugger::getInstance();
    $debugger->enable();
    
    // 你的代码...
    
    // 显示调用链
    echo $debugger->visualizeCallChain();
    
    // 显示报告
    echo $debugger->report();
}

run(main(...));
```

### 调试信息
- Fiber 创建和销毁
- 任务调度顺序
- 调用链可视化
- 异常追踪

---

## ⚡ 事件循环优化

### 自动选择最优事件循环
pfinal-asyncio 会自动选择最快的事件循环实现：

1. **Ev** - 最快（10.4x 基准）🚀
2. **Event** - 次快（4x 基准）
3. **Select** - 基准（默认，无需扩展）

```php
use PfinalClub\Asyncio\EventLoop;

$type = EventLoop::getEventLoopType();
echo "当前事件循环: $type\n";  // 'Ev', 'Event', 或 'Select'
```

### 性能对比（100 并发任务）
| 事件循环 | 性能 | 相对速度 |
|---------|------|---------|
| Select  | 80 tasks/s | 1x |
| Event   | 322 tasks/s | 4x ⚡ |
| Ev      | 833 tasks/s | 10.4x 🚀 |

**建议:** 生产环境安装 `ev` 或 `event` 扩展以获得最佳性能

---

## 🔥 多进程模式（v2.0.3 - 生产利器）

### 功能
充分利用多核 CPU，性能提升 N 倍（N = 核心数）

```php
use function PfinalClub\Asyncio\Production\run_multiprocess;

function worker_callback(): void {
    // 每个进程执行的代码
    echo "Worker 进程启动\n";
    
    // 处理请求...
}

// 启动 8 个 Worker 进程
run_multiprocess(worker_callback(...), [
    'worker_count' => 8,
]);
```

### 性能提升（8 核 CPU）
| 模式 | QPS | 提升 |
|------|-----|------|
| 单进程 | 1000 | 1x |
| 8 进程 | 8000 | 8x ⚡ |

**适用场景:**
- HTTP 服务器
- API 网关
- 高并发请求处理

---

## 🛠️ 生产工具（v2.0.3）

### 1. 健康检查
```php
use function PfinalClub\Asyncio\Production\health_check;

$health = health_check();
$status = $health->check();

if ($status['healthy']) {
    echo "服务健康\n";
} else {
    echo "服务异常: " . $status['message'] . "\n";
}
```

### 2. 优雅关闭
```php
use function PfinalClub\Asyncio\Production\graceful_shutdown;

$shutdown = graceful_shutdown(30);  // 30 秒超时
$shutdown->register();

// 收到 SIGTERM/SIGINT 时会优雅关闭
```

### 3. 资源限制
```php
use function PfinalClub\Asyncio\Production\resource_limits;

$limits = resource_limits([
    'max_memory_mb' => 512,      // 最大内存 512MB
    'max_tasks' => 10000,        // 最大任务数
    'max_connections' => 5000,   // 最大连接数
]);

$limits->enforce();
```

---

## 🎯 对 pfinal-asyncio-http 的意义

### 我们可以直接使用的功能

#### 1. 并发请求池（Pool）
**现状:** 我们已经实现了基础的 Pool 类  
**改进方向:** 利用 `semaphore()` 进行并发控制

```php
// 当前实现（需优化）
class Pool {
    private array $pending = [];
    private int $concurrency = 25;
    
    public function promise() {
        // 需要手动管理并发数
    }
}

// 优化后（使用 semaphore）
class Pool {
    private Semaphore $semaphore;
    
    public function __construct(array $config) {
        $this->concurrency = $config['concurrency'] ?? 25;
        $this->semaphore = semaphore($this->concurrency);  // 使用信号量！
    }
    
    public function promise() {
        return new TaskPromise(create_task(function() {
            $this->semaphore->acquire();  // 自动限制并发
            try {
                $response = $this->request();
                return $response;
            } finally {
                $this->semaphore->release();
            }
        }));
    }
}
```

#### 2. AsyncioHandler 优化
**现状:** 已使用 `AsyncHttpClient`  
**优势:** 已经有连接池、Keep-Alive、性能监控

```php
// 我们的 AsyncioHandler.php 已经正确使用了
use PfinalClub\Asyncio\Http\AsyncHttpClient;

class AsyncioHandler implements HandlerInterface {
    private AsyncHttpClient $client;
    
    public function __invoke(RequestInterface $request, array $options): PromiseInterface {
        return new TaskPromise(create_task(function() use ($request, $options) {
            // AsyncHttpClient 已经提供：
            // ✅ 连接池
            // ✅ Keep-Alive
            // ✅ 异步执行
            // ✅ 性能监控
            $response = $this->client->request(...);
            return $response;
        }));
    }
}
```

#### 3. 重试中间件
**使用 `sleep()` 实现延迟重试:**

```php
class RetryMiddleware {
    public function __invoke(callable $handler): callable {
        return function($request, $options) use ($handler) {
            $attempt = 0;
            $maxAttempts = $options['max_attempts'] ?? 3;
            
            while ($attempt < $maxAttempts) {
                try {
                    return $handler($request, $options);
                } catch (\Exception $e) {
                    $attempt++;
                    if ($attempt >= $maxAttempts) {
                        throw $e;
                    }
                    
                    // 使用 asyncio 的 sleep 实现延迟
                    $delay = $this->calculateDelay($attempt);
                    sleep($delay);  // 不阻塞事件循环！
                }
            }
        };
    }
}
```

#### 4. 性能监控集成
**在客户端中添加监控:**

```php
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;

class Client {
    public function request($method, $uri, array $options = []) {
        $monitor = AsyncioMonitor::getInstance();
        
        // 发送请求...
        
        // 监控自动记录性能指标
        return $response;
    }
    
    public function getPerformanceReport(): string {
        return AsyncioMonitor::getInstance()->report();
    }
}
```

---

## 📝 最佳实践建议

### 1. 并发请求（推荐模式）

```php
use function PfinalClub\Asyncio\{run, create_task, gather};
use PFinal\AsyncioHttp\Client;

function main(): void {
    $client = new Client(['timeout' => 10]);
    
    // ✅ 推荐：使用 create_task + gather
    $tasks = [
        create_task(fn() => $client->get('https://api.example.com/users/1')),
        create_task(fn() => $client->get('https://api.example.com/users/2')),
        create_task(fn() => $client->get('https://api.example.com/users/3')),
    ];
    
    $responses = gather(...$tasks);
    
    // ❌ 不推荐：串行执行
    // $response1 = $client->get('https://api.example.com/users/1');
    // $response2 = $client->get('https://api.example.com/users/2');
    // $response3 = $client->get('https://api.example.com/users/3');
}

run(main(...));
```

### 2. 并发控制（使用信号量）

```php
use function PfinalClub\Asyncio\{run, create_task, gather, semaphore};
use PFinal\AsyncioHttp\Client;

function main(): void {
    $client = new Client();
    $sem = semaphore(10);  // 限制 10 个并发
    
    $urls = [...]; // 1000 个 URL
    
    $tasks = [];
    foreach ($urls as $url) {
        $tasks[] = create_task(function() use ($client, $url, $sem) {
            $sem->acquire();
            try {
                return $client->get($url);
            } finally {
                $sem->release();
            }
        });
    }
    
    $responses = gather(...$tasks);
}

run(main(...));
```

### 3. 超时处理

```php
use function PfinalClub\Asyncio\{run, create_task, sleep};
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Exception\TimeoutException;

function main(): void {
    $client = new Client();
    
    $task = create_task(fn() => $client->get('https://slow-api.com'));
    
    // 等待最多 5 秒
    $timeout = 5;
    $start = microtime(true);
    
    while (!$task->isDone()) {
        if (microtime(true) - $start > $timeout) {
            throw new TimeoutException("请求超时");
        }
        sleep(0.1);  // 检查间隔
    }
    
    $response = $task->getResult();
}

run(main(...));
```

### 4. 错误处理

```php
use function PfinalClub\Asyncio\{run, create_task, gather};
use PFinal\AsyncioHttp\Client;

function main(): void {
    $client = new Client(['http_errors' => false]);  // 不自动抛异常
    
    $tasks = [
        create_task(fn() => $client->get('https://api.example.com/valid')),
        create_task(fn() => $client->get('https://api.example.com/404')),
    ];
    
    $responses = gather(...$tasks);
    
    foreach ($responses as $response) {
        if ($response->getStatusCode() >= 400) {
            echo "错误: {$response->getStatusCode()}\n";
        } else {
            echo "成功: {$response->getBody()}\n";
        }
    }
}

run(main(...));
```

---

## 🚀 性能优化建议

### 1. 事件循环优化
```bash
# 安装 Ev 扩展（推荐，性能最高）
pecl install ev

# 或安装 Event 扩展
pecl install event
```

### 2. 并发数调优
```php
// 根据场景调整并发数
$concurrency = match($scenario) {
    'api_calls' => 50,        // API 调用
    'web_scraping' => 100,    // 网页爬取
    'file_download' => 20,    // 文件下载
    default => 25,
};

$sem = semaphore($concurrency);
```

### 3. 连接复用
```php
// 使用同一个 Client 实例，复用连接池
$client = new Client(['timeout' => 10]);

// ✅ 复用连接
for ($i = 0; $i < 1000; $i++) {
    $client->get("https://api.example.com/item/$i");
}

// ❌ 不要每次创建新 Client
// for ($i = 0; $i < 1000; $i++) {
//     $client = new Client();
//     $client->get("https://api.example.com/item/$i");
// }
```

### 4. 内存管理
```php
// Fiber 自动清理（v2.0.2+）
// 每 100 个 Fiber 或 run() 结束时自动清理

// 手动清理（如果需要）
use function PfinalClub\Asyncio\Production\resource_limits;

$limits = resource_limits(['max_memory_mb' => 512]);
$limits->enforce();
```

---

## 📚 版本历史和重要变更

### v2.0.3 (2025-01-21) - 当前版本
**新增:**
- ✨ 信号量（Semaphore）- 并发控制
- ✨ 多进程模式 - 多核 CPU 利用
- ✨ 生产工具包 - HealthCheck, GracefulShutdown, ResourceLimits
- ✨ 事件循环优化 - 自动选择最优实现

**性能提升:**
- Ev 事件循环: 10.4x
- Event 事件循环: 4x
- 多进程: 8x（8核）

### v2.0.2 (2025-01-20)
**新增:**
- ✨ Fiber 自动清理 - 防止内存泄漏
- ✨ HTTP 连接池 - 连接统计和健康检查
- ✨ 性能监控系统 - Prometheus/JSON 导出

### v2.0.1 (2025-01-20)
**优化:**
- ⚡ 完全事件驱动 - 移除轮询
- ⚡ 零延迟恢复 - await/gather < 0.1ms
- ⚡ 精确定时 - sleep() ±0.1ms
- ⚡ CPU 效率 - 空闲时 < 1%

### v2.0.0 (2025-01-20)
**重大变更:**
- 完全基于 PHP Fiber 重写
- 移除所有 Generator 代码
- 性能提升 2-3 倍
- API 变更（不兼容 v1.x）

---

## 🎯 总结和行动计划

### pfinal-asyncio 已经提供的核心能力

1. ✅ **完整的事件循环** - 自动优化，生产就绪
2. ✅ **任务调度系统** - create_task, gather, await
3. ✅ **并发控制** - semaphore（v2.0.3）
4. ✅ **HTTP 客户端** - AsyncHttpClient（带连接池）
5. ✅ **性能监控** - AsyncioMonitor
6. ✅ **调试工具** - AsyncioDebugger
7. ✅ **生产工具** - 健康检查、优雅关闭、资源限制

### 我们需要在 pfinal-asyncio-http 中做的

#### 立即改进（基于现有功能）
1. **Pool 类优化** - 使用 `semaphore()` 替代手动并发控制
2. **性能监控集成** - 在 Client 中集成 AsyncioMonitor
3. **调试支持** - 提供 AsyncioDebugger 选项

#### 继续实现（计划中）
1. **中间件系统** - Redirect, Retry, Cookie, Auth 等
2. **Cookie 管理** - CookieJar, FileCookieJar
3. **认证系统** - Basic, Digest, Bearer, OAuth
4. **完整测试** - 单元测试、集成测试
5. **文档完善** - 功能文档、API 文档、示例

### 关键优势

通过深入了解 pfinal-asyncio，我们发现：

1. **无需重复造轮子** - HTTP 客户端、并发控制、性能监控都已有
2. **性能已优化** - 事件循环、Fiber 管理、连接池都是生产级
3. **可直接使用** - API 清晰、文档完善、版本稳定

### 下一步

1. ✅ 优化 Pool 类（使用 semaphore）
2. ✅ 集成性能监控
3. ⏳ 实现中间件系统
4. ⏳ 完善文档和示例

---

**分析完成！** 🎉

现在我们对 pfinal-asyncio 有了全面的了解，可以更好地利用它的强大功能来构建我们的 HTTP 客户端库。
