# 下一步开发计划

## 🎯 立即可做的事情

### 1. 测试当前功能
运行提供的示例来测试现有功能：

```bash
# 安装依赖
composer install

# 测试基础 GET 请求
php examples/basic/simple-get.php

# 测试 POST 请求
php examples/basic/simple-post.php

# 测试并发请求
php examples/async/concurrent-requests.php

# 测试请求池
php examples/async/pool.php
```

### 2. 实现基础中间件系统

创建以下文件来实现基础中间件：

#### src/Middleware/MiddlewareInterface.php
```php
interface MiddlewareInterface
{
    public function process(
        RequestInterface $request,
        callable $next,
        array $options
    ): ResponseInterface;
}
```

#### src/Middleware/HttpErrorsMiddleware.php
处理 HTTP 错误（4xx, 5xx），默认抛出异常。

#### src/Middleware/PrepareBodyMiddleware.php
准备请求体（JSON, form_params, multipart）。

### 3. 实现 Cookie 管理

创建基础的 Cookie 支持：

#### src/Cookie/CookieJar.php
#### src/Cookie/SetCookie.php
#### src/Cookie/CookieMiddleware.php

### 4. 添加单元测试

创建基础测试：

```bash
# 创建测试目录
mkdir -p tests/Unit/Psr7
mkdir -p tests/Unit/Exception
mkdir -p tests/Integration

# 运行测试
./vendor/bin/phpunit
```

## 📋 优先级列表

### 🔴 高优先级

1. **HTTP 错误中间件** - 让错误处理更符合 Guzzle 行为
2. **重试中间件** - 实现自动重试失败的请求
3. **Cookie 管理** - 支持自动 Cookie 处理
4. **单元测试** - 为核心组件添加测试
5. **集成测试** - 测试完整的请求流程

### 🟡 中优先级

6. **重定向中间件** - 自动跟随 3xx 重定向
7. **认证支持** - Basic, Bearer Token 认证
8. **代理支持** - HTTP/HTTPS 代理
9. **SSL 配置** - 证书验证、客户端证书
10. **功能文档** - 详细的使用文档

### 🟢 低优先级

11. **高级认证** - Digest, OAuth, NTLM
12. **流式传输** - 大文件上传/下载
13. **进度回调** - 上传/下载进度监控
14. **性能测试** - 基准测试和性能对比
15. **高级示例** - 实战应用示例

## 🔧 快速实现指南

### 添加 HTTP 错误处理

在 `Client::buildRequest()` 中默认添加：

```php
// 在 HandlerStack 创建时添加
$this->handlerStack->push(
    new HttpErrorsMiddleware(),
    'http_errors'
);
```

### 添加重试支持

```php
use PFinal\AsyncioHttp\Middleware\RetryMiddleware;

$client = new Client();
$client->getHandlerStack()->push(
    new RetryMiddleware(3), // 最多重试 3 次
    'retry'
);
```

### 添加 Cookie 支持

```php
use PFinal\AsyncioHttp\Cookie\CookieJar;
use PFinal\AsyncioHttp\Middleware\CookieMiddleware;

$cookieJar = new CookieJar();
$client = new Client();
$client->getHandlerStack()->push(
    new CookieMiddleware($cookieJar),
    'cookies'
);
```

## 📚 推荐阅读

在继续开发前，建议阅读：

1. **Guzzle 源代码** - 了解 Guzzle 的实现细节
   - https://github.com/guzzle/guzzle

2. **pfinal-asyncio 文档** - 深入理解异步模型
   - https://github.com/pfinalclub/pfinal-asyncio

3. **PSR 规范** - 确保标准兼容性
   - PSR-7: https://www.php-fig.org/psr/psr-7/
   - PSR-18: https://www.php-fig.org/psr/psr-18/

4. **Workerman 文档** - 理解底层异步机制
   - http://doc.workerman.net/

## 🐛 已知问题需要修复

1. **AsyncioHandler 响应解析** - 需要更完善的 HTTP 响应解析
   - 处理 chunked 编码
   - 处理多个 Set-Cookie 头
   - 更好的错误处理

2. **超时处理** - 完善超时机制
   - connect_timeout
   - read_timeout
   - 总体 timeout

3. **连接复用** - 实现 Keep-Alive 支持
   - 连接池管理
   - 自动关闭空闲连接

## 💡 实现建议

### 模块化开发

每个功能模块独立开发和测试：

```
1. 设计接口
2. 实现核心逻辑
3. 编写单元测试
4. 创建示例代码
5. 编写文档
```

### 测试驱动开发

先写测试再写实现：

```php
// tests/Unit/Middleware/RetryMiddlewareTest.php
public function testRetryOnServerError()
{
    // 创建模拟处理器
    $mock = new MockHandler([
        new Response(500),
        new Response(500),
        new Response(200),
    ]);
    
    // 测试重试逻辑
    $middleware = new RetryMiddleware(3);
    // ...
}
```

### 渐进式完善

不求一次完美，逐步改进：

```
v0.1 - 核心功能（当前状态）
v0.2 - 基础中间件
v0.3 - Cookie 和认证
v0.4 - 完整测试
v1.0 - 生产就绪
```

## 🎉 完成当前版本的下一步

要让当前版本达到可用状态（v0.2），需要：

1. ✅ 修复 AsyncioHandler 的响应解析
2. ✅ 实现 HttpErrorsMiddleware
3. ✅ 实现 RetryMiddleware
4. ✅ 添加基础测试（至少10个）
5. ✅ 更新 README 添加更多示例

预计工作量：**10-15 小时**

---

**开始行动！**🚀 选择一个优先级高的任务开始实现吧！

