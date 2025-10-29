# PFinal AsyncIO HTTP 示例

本目录包含各种使用示例，展示如何使用 pfinal-asyncio-http 库。

## 运行示例

确保你已经安装了依赖：

```bash
cd /Users/pfinal/www/pfinal-asyncio-http
composer install
```

然后运行任何示例：

```bash
php examples/basic/simple-get.php
php examples/basic/simple-post.php
php examples/async/concurrent-requests.php
php examples/async/pool.php
```

## 基础示例 (basic/)

### simple-get.php
演示如何发送简单的 GET 请求，包括：
- 设置查询参数
- 读取响应状态码和头部
- 获取响应体

### simple-post.php
演示如何发送 POST 请求，包括：
- 发送 JSON 数据
- 发送表单数据

## 异步示例 (async/)

### concurrent-requests.php
演示如何使用 `create_task()` 和 `gather()` 进行并发请求：
- 创建多个并发任务
- 使用 gather 等待所有任务完成
- 对比并发和顺序执行的性能差异

### pool.php
演示如何使用 Pool 进行批量并发请求：
- 限制并发数量
- 使用回调处理成功和失败的请求
- 使用生成器创建大量请求

## 性能对比

### 顺序执行 vs 并发执行

**顺序执行（5个请求，每个延迟1秒）：**
```
总耗时: ~5 秒
```

**并发执行（5个请求，每个延迟1秒）：**
```
总耗时: ~1 秒（提升 5 倍！）
```

### 使用 Pool 控制并发数

**10个请求，并发限制为3：**
```
总耗时: ~4 秒
- 第1-3个请求: 0-1秒
- 第4-6个请求: 1-2秒
- 第7-9个请求: 2-3秒
- 第10个请求: 3-4秒
```

## 注意事项

1. 示例中使用 `verify => false` 仅用于演示，**生产环境应该验证 SSL 证书**
2. httpbin.org 是一个公共测试服务，请勿滥用
3. 所有示例都需要在 `run()` 函数中执行，这是 pfinal-asyncio 的要求

## 更多示例

查看其他目录了解更多高级用法：
- `advanced/` - 高级功能（中间件、认证、代理等）
- `real-world/` - 实战示例（API 客户端、爬虫等）

## 问题反馈

如果你遇到任何问题，请在 GitHub 上提交 Issue：
https://github.com/pfinalclub/pfinal-asyncio-http/issues

