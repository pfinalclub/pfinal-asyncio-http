# 中间件系统详解

pfinal-asyncio-http 提供了一个强大且灵活的中间件系统，允许你在请求发送前和响应返回后执行自定义逻辑。

---

## 📖 目录

- [什么是中间件](#什么是中间件)
- [内置中间件](#内置中间件)
- [使用中间件](#使用中间件)
- [自定义中间件](#自定义中间件)
- [中间件执行顺序](#中间件执行顺序)
- [最佳实践](#最佳实践)

---

## 什么是中间件

中间件是一个可调用对象（函数或类），它接收一个处理器（handler）并返回一个新的处理器。中间件可以：

- 修改请求
- 修改响应
- 记录日志
- 处理错误
- 添加认证
- 实现重试逻辑
- ...等等

### 中间件签名

```php
callable(callable $handler): callable
```

中间件接收一个 `$handler`（下一个处理器），返回一个新的处理器函数。

---

## 内置中间件

### 1. HTTP 错误处理 (HttpErrors)

自动将 4xx 和 5xx 响应转换为异常。

```php
use PFinal\AsyncioHttp\Middleware\Middleware;

$stack->push(Middleware::httpErrors());
```

**选项：**
- `enabled` (bool): 是否启用，默认 `true`

**示例：**
```php
// 禁用 HTTP 错误
$client = new Client(['http_errors' => false]);

// 现在 4xx/5xx 不会抛异常
$response = $client->get('https://example.com/404');
echo $response->getStatusCode(); // 404
```

---

### 2. 重定向 (Redirect)

自动处理 HTTP 重定向（301, 302, 303, 307, 308）。

```php
$stack->push(Middleware::redirect([
    'max' => 5,              // 最大重定向次数
    'strict' => false,       // 是否严格模式
    'referer' => true,       // 是否添加 Referer 头
    'protocols' => ['http', 'https'],
    'track_redirects' => false,  // 是否追踪重定向历史
]));
```

**重定向行为：**

| 状态码 | 非严格模式 | 严格模式 |
|--------|-----------|---------|
| 301, 302 | GET/HEAD 保持，其他改为 GET | 保持原方法 |
| 303 | 总是改为 GET | 总是改为 GET |
| 307, 308 | 保持原方法 | 保持原方法 |

**示例：**
```php
$response = $client->get('http://example.com/redirect');
// 自动跟随重定向，最多 5 次
```

---

### 3. 重试 (Retry)

请求失败时自动重试。

```php
use PFinal\AsyncioHttp\Middleware\RetryMiddleware;

$stack->push(Middleware::retry([
    'max' => 3,                  // 最大重试次数
    'delay' => RetryMiddleware::exponentialBackoff(1000),  // 延迟函数
    'on_retry' => function ($attempt, $request, $error, $response) {
        echo "重试第 {$attempt} 次\n";
    },
    'decide' => RetryMiddleware::statusCodeDecider([500, 502, 503]),
]));
```

**内置延迟策略：**

```php
// 指数退避：1s, 2s, 4s, 8s...
RetryMiddleware::exponentialBackoff(1000, $maxDelay = 60000);

// 线性退避：1s, 2s, 3s...
RetryMiddleware::linearBackoff(1000);

// 固定延迟：1s, 1s, 1s...
RetryMiddleware::constantBackoff(1000);
```

**内置决策器：**

```php
// 基于状态码重试
RetryMiddleware::statusCodeDecider([500, 502, 503, 504]);
```

**自定义决策器：**
```php
'decide' => function ($attempt, $request, $response, $error) {
    // 返回 true 表示应该重试
    if ($error instanceof ConnectException) {
        return true;  // 连接错误，重试
    }
    
    if ($response && $response->getStatusCode() === 503) {
        return true;  // 503 错误，重试
    }
    
    return false;  // 不重试
}
```

---

### 4. Cookie 管理 (Cookie)

自动管理 Cookie。

```php
use PFinal\AsyncioHttp\Cookie\FileCookieJar;

$jar = new FileCookieJar('/tmp/cookies.json');
$stack->push(Middleware::cookies($jar));

// 或在客户端配置
$client = new Client(['cookies' => $jar]);
```

**Cookie 持久化：**

```php
// 文件持久化
$jar = new FileCookieJar('/tmp/cookies.json');

// Session 持久化
$jar = new SessionCookieJar('my_cookies');

// 内存（不持久化）
$jar = new CookieJar();
```

---

### 5. 日志记录 (Log)

记录所有 HTTP 请求和响应。

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('http');
$logger->pushHandler(new StreamHandler('php://stdout'));

$stack->push(Middleware::log($logger, '{method} {uri} -> {code} {phrase}'));
```

**内置格式化器：**

```php
use PFinal\AsyncioHttp\MessageFormatter;

// Apache 格式
MessageFormatter::CLF

// 调试格式
MessageFormatter::DEBUG

// 简短格式
MessageFormatter::SHORT

// 自定义格式
'{method} {uri} -> {code} {phrase}'
```

**格式化占位符：**

| 占位符 | 说明 |
|-------|------|
| `{request}` | 完整请求 |
| `{response}` | 完整响应 |
| `{method}` | 请求方法 |
| `{uri}` | 请求 URI |
| `{code}` | 状态码 |
| `{phrase}` | 状态短语 |
| `{req_header_*}` | 请求头 |
| `{res_header_*}` | 响应头 |
| `{req_body}` | 请求体 |
| `{res_body}` | 响应体 |

---

### 6. 历史记录 (History)

追踪所有请求和响应。

```php
$history = [];
$stack->push(Middleware::history($history));

// 发送请求
$client->get('https://example.com');

// 查看历史
foreach ($history as $entry) {
    $request = $entry['request'];
    $response = $entry['response'];
    $error = $entry['error'];
}
```

---

### 7. 请求映射 (MapRequest)

在发送前修改请求。

```php
$stack->push(Middleware::mapRequest(function ($request) {
    // 添加自定义头
    return $request->withHeader('X-Custom-Header', 'value');
}));
```

---

### 8. 响应映射 (MapResponse)

在返回前修改响应。

```php
$stack->push(Middleware::mapResponse(function ($response) {
    // 添加自定义头
    return $response->withHeader('X-Processed', 'true');
}));
```

---

### 9. 进度监控 (Progress)

跟踪上传/下载进度。

```php
$stack->push(Middleware::progress(function ($downloadTotal, $downloadCurrent, $uploadTotal, $uploadCurrent) {
    $downloadPercent = $downloadTotal > 0 ? ($downloadCurrent / $downloadTotal) * 100 : 0;
    $uploadPercent = $uploadTotal > 0 ? ($uploadCurrent / $uploadTotal) * 100 : 0;
    
    echo sprintf(
        "下载: %.1f%% (%s/%s), 上传: %.1f%% (%s/%s)\n",
        $downloadPercent,
        Utils::formatBytes($downloadCurrent),
        Utils::formatBytes($downloadTotal),
        $uploadPercent,
        Utils::formatBytes($uploadCurrent),
        Utils::formatBytes($uploadTotal)
    );
}));
```

---

### 10. 代理 (Proxy)

配置 HTTP 代理。

```php
$stack->push(Middleware::proxy('http://proxy.example.com:8080'));

// 或在客户端配置
$client = new Client([
    'proxy' => 'http://proxy.example.com:8080',
]);
```

---

### 11. 认证 (Auth)

添加认证信息。

```php
// Basic 认证
$stack->push(Middleware::auth('username', 'password', 'basic'));

// Bearer Token
$stack->push(Middleware::auth('my-token', '', 'bearer'));

// 或在请求配置
$client->get('https://api.example.com', [
    'auth' => ['username', 'password'],
]);
```

---

### 12. Expect 100-continue

处理大请求的 Expect 头。

```php
$stack->push(Middleware::expect());
```

---

### 13. 内容解码 (DecodeContent)

自动解码 gzip、deflate 响应。

```php
$stack->push(Middleware::decodeContent());
```

---

### 14. 准备请求体 (PrepareBody)

处理 `json`、`form_params`、`multipart` 选项。

```php
$stack->push(Middleware::prepareBody());
```

这个中间件通常自动添加，无需手动配置。

---

## 使用中间件

### 方法 1：在 HandlerStack 中添加

```php
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Handler\HandlerStack;
use PFinal\AsyncioHttp\Middleware\Middleware;

// 创建自定义栈
$stack = HandlerStack::create();

// 添加中间件
$stack->push(Middleware::retry(['max' => 3]), 'retry');
$stack->push(Middleware::redirect(['max' => 5]), 'redirect');
$stack->push(Middleware::log($logger), 'log');

// 创建客户端
$client = new Client(['handler' => $stack]);
```

### 方法 2：在客户端配置中

某些中间件可以通过客户端配置启用：

```php
$client = new Client([
    'cookies' => $cookieJar,       // Cookie 中间件
    'http_errors' => true,         // HTTP 错误中间件
    'allow_redirects' => [         // 重定向中间件
        'max' => 5,
    ],
]);
```

### 方法 3：在请求选项中

某些选项可以在单个请求中配置：

```php
$client->get('https://example.com', [
    'http_errors' => false,        // 禁用 HTTP 错误
    'allow_redirects' => false,    // 禁用重定向
    'auth' => ['user', 'pass'],    // 认证
    'proxy' => 'http://proxy.com', // 代理
]);
```

---

## 自定义中间件

### 基础中间件

```php
$customMiddleware = function (callable $handler) {
    return function ($request, $options) use ($handler) {
        // 修改请求
        $request = $request->withHeader('X-Custom', 'value');
        
        // 调用下一个处理器
        $promise = $handler($request, $options);
        
        // 修改响应
        return $promise->then(function ($response) {
            return $response->withHeader('X-Processed', 'true');
        });
    };
};

$stack->push($customMiddleware, 'custom');
```

### 带配置的中间件

```php
class CustomMiddleware
{
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    public function __invoke(callable $handler): callable
    {
        return function ($request, $options) use ($handler) {
            // 使用 $this->config
            $request = $request->withHeader('X-Option', $this->config['option']);
            
            return $handler($request, $options);
        };
    }
}

$stack->push(new CustomMiddleware(['option' => 'value']), 'custom');
```

### 异常处理中间件

```php
$errorHandlerMiddleware = function (callable $handler) {
    return function ($request, $options) use ($handler) {
        $promise = $handler($request, $options);
        
        return $promise->then(
            function ($response) {
                // 成功
                return $response;
            },
            function ($error) {
                // 失败
                echo "请求失败: {$error->getMessage()}\n";
                throw $error;
            }
        );
    };
};
```

### 计时中间件

```php
$timingMiddleware = function (callable $handler) {
    return function ($request, $options) use ($handler) {
        $start = microtime(true);
        
        $promise = $handler($request, $options);
        
        return $promise->then(
            function ($response) use ($start) {
                $duration = (microtime(true) - $start) * 1000;
                echo sprintf("请求耗时: %.2f ms\n", $duration);
                return $response;
            }
        );
    };
};
```

---

## 中间件执行顺序

中间件按照**栈的顺序**执行：

```php
$stack = HandlerStack::create();
$stack->push($middleware1, 'first');   // 最后执行
$stack->push($middleware2, 'second');  // 中间执行
$stack->push($middleware3, 'third');   // 最先执行

// 执行顺序：
// 请求: third -> second -> first -> handler
// 响应: handler -> first -> second -> third
```

### 控制执行顺序

```php
// 添加到栈底（最先执行）
$stack->unshift($middleware, 'name');

// 在指定中间件之前
$stack->before('retry', $middleware, 'my_middleware');

// 在指定中间件之后
$stack->after('retry', $middleware, 'my_middleware');

// 移除中间件
$stack->remove('name');
```

### 调试中间件栈

```php
echo $stack->debug();

// 输出:
// Handler Stack:
//   [底层] PFinal\AsyncioHttp\Handler\AsyncioHandler
//   [1] prepare_body
//   [2] http_errors
//   [3] retry
//   [4] redirect
```

---

## 最佳实践

### 1. 合理的中间件顺序

推荐顺序（从底层到顶层）：

1. **PrepareBody** - 准备请求体
2. **HttpErrors** - HTTP 错误处理
3. **Redirect** - 重定向
4. **Retry** - 重试
5. **Auth** - 认证
6. **Cookie** - Cookie
7. **Log** - 日志
8. **Custom** - 自定义中间件

### 2. 避免修改不可变对象

PSR-7 对象是不可变的，务必使用 `with*()` 方法：

```php
// ✅ 正确
$request = $request->withHeader('X-Custom', 'value');

// ❌ 错误（无效）
$request->withHeader('X-Custom', 'value');
```

### 3. 正确处理 Promise

中间件必须返回 Promise：

```php
// ✅ 正确
return $promise->then(function ($response) {
    return $response->withHeader('X-Custom', 'value');
});

// ❌ 错误（破坏 Promise 链）
$promise->then(function ($response) {
    return $response->withHeader('X-Custom', 'value');
});
return null;
```

### 4. 使用命名中间件

便于调试和移除：

```php
$stack->push($middleware, 'my_middleware');  // ✅ 有名称
$stack->push($middleware);  // ❌ 无名称
```

### 5. 中间件应该快速执行

避免在中间件中执行耗时操作：

```php
// ❌ 错误：阻塞操作
$middleware = function ($handler) {
    return function ($request, $options) use ($handler) {
        sleep(5);  // 阻塞！
        return $handler($request, $options);
    };
};

// ✅ 正确：使用异步 sleep
use function PFinal\Asyncio\sleep;

$middleware = function ($handler) {
    return function ($request, $options) use ($handler) {
        sleep(5);  // 非阻塞
        return $handler($request, $options);
    };
};
```

---

## 完整示例

### API 客户端中间件栈

```php
use function PFinal\Asyncio\run;
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Handler\HandlerStack;
use PFinal\AsyncioHttp\Middleware\Middleware;
use PFinal\AsyncioHttp\Cookie\FileCookieJar;

function main(): void
{
    // 创建栈
    $stack = HandlerStack::create();
    
    // Cookie 管理
    $cookieJar = new FileCookieJar('/tmp/api_cookies.json');
    $stack->push(Middleware::cookies($cookieJar), 'cookies');
    
    // 重试策略
    $stack->push(Middleware::retry([
        'max' => 3,
        'delay' => Middleware\RetryMiddleware::exponentialBackoff(1000),
    ]), 'retry');
    
    // 重定向
    $stack->push(Middleware::redirect(['max' => 5]), 'redirect');
    
    // 日志
    if ($logger) {
        $stack->push(Middleware::log($logger), 'log');
    }
    
    // 自定义：添加 API Token
    $stack->push(function ($handler) {
        return function ($request, $options) use ($handler) {
            $request = $request->withHeader('Authorization', 'Bearer my-api-token');
            return $handler($request, $options);
        };
    }, 'auth');
    
    // 创建客户端
    $client = new Client([
        'handler' => $stack,
        'base_uri' => 'https://api.example.com',
        'timeout' => 30,
    ]);
    
    // 使用客户端
    $response = $client->get('/users');
    echo $response->getBody();
}

run(main(...));
```

---

## 参考

- [Guzzle 中间件文档](https://docs.guzzlephp.org/en/stable/handlers-and-middleware.html)
- [PSR-7 标准](https://www.php-fig.org/psr/psr-7/)
- [PSR-18 标准](https://www.php-fig.org/psr/psr-18/)

---

**返回 [主文档](../README.md)**

