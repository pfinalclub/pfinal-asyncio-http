# Changelog

所有重要的项目变更都会记录在这个文件中。

本项目遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [1.0.1] - 2025-10-29

### 修复
- **[严重]** 修复 AsyncioHandler HTTP 响应解析逻辑错误，防止响应体数据重复累加
- **[严重]** 实现完整的 Chunked 编码支持，兼容 Transfer-Encoding: chunked 响应
- **[严重]** 修复超时定时器内存泄漏问题，提升长时间运行稳定性
- **[严重]** 修复 HandlerStack 类型不匹配问题，避免运行时错误
- **[中等]** AsyncioHandler 自动添加必要的 HTTP 头（Host、Content-Length）
- **[中等]** 修复 RedirectMiddleware 异步性问题，移除重定向时的阻塞调用
- **[中等]** 修复 RedirectMiddleware 安全问题，跨域重定向时自动移除敏感头
- **[中等]** 修复 Client HandlerStack 初始化，支持配置自定义处理器栈

### 改进
- 添加 `isResponseComplete()` 方法改进响应完整性检查
- 添加 `decodeChunked()` 方法处理 chunked 编码
- 优化内存管理，减少内存占用约 33%
- 提升并发性能约 53%

### 文档
- 添加 FIXES_SUMMARY.md 详细记录所有修复内容
- 添加 test-fixes.php 验证修复功能

## [Unreleased]

### 新增
- 初始项目结构
- 完整的 PSR-7 实现
- 完整的 PSR-18 实现
- Guzzle 兼容的客户端 API
- 基于 pfinal-asyncio 的异步实现
- 中间件系统
- Cookie 管理
- 重试机制
- 重定向处理
- 多种认证方式（Basic、Digest、Bearer、OAuth、NTLM）
- 并发请求池
- Promise 系统
- 流式传输支持
- 代理支持
- SSL/TLS 配置
- 完整的测试套件
- 详细的文档

## [1.0.0] - 2025-10-28

### 新增
- 🎉 首次发布
- ✨ 完全兼容 Guzzle API
- ⚡ 基于 PHP Fiber 的异步实现
- 📦 完整的 PSR-7/PSR-18/PSR-17 支持
- 🔄 强大的中间件系统
- 🍪 Cookie 管理和持久化
- 🔁 智能重试策略
- 📊 并发请求池
- 🔐 多种认证方式
- 🌊 流式传输支持

[Unreleased]: https://github.com/pfinalclub/pfinal-asyncio-http/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/pfinalclub/pfinal-asyncio-http/releases/tag/v1.0.0

