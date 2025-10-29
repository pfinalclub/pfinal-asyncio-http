# 安全政策

## 支持的版本

我们目前支持以下版本的安全更新：

| 版本 | 支持状态 |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## 报告漏洞

如果你发现了安全漏洞，请**不要**通过公开的 issue 报告。

请通过以下方式报告：

1. **邮件**: 发送详细信息到 pfinal@pfinal.cn
2. **主题**: 使用 "Security Vulnerability in pfinal-asyncio-http" 作为主题

### 报告应包含

- 漏洞的详细描述
- 重现步骤
- 潜在影响
- 可能的解决方案（如果有）

### 处理流程

1. 我们会在 48 小时内确认收到你的报告
2. 我们会评估漏洞并确定其严重性
3. 我们会制定修复计划并通知你
4. 修复完成后，我们会发布安全补丁
5. 在补丁发布后，我们会公开致谢（如果你同意）

## 安全最佳实践

使用本库时，请遵循以下最佳实践：

### 1. 证书验证

始终验证 SSL 证书（默认启用）：

```php
$client = new Client([
    'verify' => true, // 默认值
]);
```

### 2. 超时设置

设置合理的超时以防止资源耗尽：

```php
$client = new Client([
    'timeout' => 30,
    'connect_timeout' => 10,
]);
```

### 3. 敏感信息

不要在代码中硬编码敏感信息：

```php
// ❌ 不好
$client->get('https://api.example.com', [
    'headers' => ['Authorization' => 'Bearer secret-token'],
]);

// ✅ 好
$token = getenv('API_TOKEN');
$client->get('https://api.example.com', [
    'headers' => ['Authorization' => "Bearer $token"],
]);
```

### 4. 输入验证

始终验证和清理用户输入：

```php
// ❌ 不好
$url = $_GET['url'];
$client->get($url);

// ✅ 好
$url = filter_var($_GET['url'], FILTER_VALIDATE_URL);
if ($url) {
    $client->get($url);
}
```

### 5. 代理配置

小心使用代理，确保代理服务器可信：

```php
$client = new Client([
    'proxy' => [
        'http'  => 'http://trusted-proxy.com:8080',
        'https' => 'http://trusted-proxy.com:8080',
    ],
]);
```

## 已知问题

目前没有已知的安全问题。

## 更新

请定期更新到最新版本以获取安全修复：

```bash
composer update pfinal/asyncio-http
```

## 致谢

我们感谢负责任地披露安全问题的安全研究人员。

---

最后更新：2025-10-28

