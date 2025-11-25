# AsyncIO HTTP Core

<div align="center">

🚀 **PHP 生产级异步 HTTP 客户端**

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://www.php.net/)
[![Asyncio Version](https://img.shields.io/badge/asyncio-%5E3.0-purple)](https://github.com/pfinalclub/pfinal-asyncio)
[![PSR-7](https://img.shields.io/badge/PSR--7-compatible-orange)](https://www.php-fig.org/psr/psr-7/)
[![PSR-18](https://img.shields.io/badge/PSR--18-compatible-orange)](https://www.php-fig.org/psr/psr-18/)

[English](README.md) | **中文文档**

---

*[pfinal-asyncio](https://github.com/pfinalclub/pfinal-asyncio) 生态系统的一部分*

</div>

## 📖 简介

**AsyncIO HTTP Core** 是基于 [pfinal-asyncio](https://github.com/pfinalclub/pfinal-asyncio) 框架构建的生产级、高性能异步 HTTP 客户端。它利用 PHP 8.1+ Fiber 技术，提供真正的异步 I/O，同时保持简洁、类似同步的 API。

### 🎯 核心特性

- **🚀 真正的异步 I/O** - 原生 PHP 8.1+ Fiber，零阻塞
- **⚡ 零配置并发** - 内置 `gather()` 和 `Semaphore` 支持
- **📦 PSR 标准** - 完全符合 PSR-7（HTTP 消息）& PSR-18（HTTP 客户端）
- **🔧 中间件系统** - 灵活的洋葱模型中间件架构
- **🎨 优雅 API** - 直观的、类似 `requests` 的接口
- **🔄 连接复用** - 自动 HTTP Keep-Alive 和连接池
- **🛡️ 生产就绪** - 久经考验的错误处理和重试策略
- **📊 监控** - 内置性能指标和追踪
- **🌐 HTTP/1.1 & HTTP/2** - 协议版本协商支持

## 📋 要求

| 要求 | 版本 | 说明 |
|------|------|------|
| **PHP** | >= 8.1 | 需要 Fiber 支持 |
| **pfinalclub/asyncio** | ^3.0 | 核心异步运行时 |
| **Workerman** | >= 4.1 | 事件循环（自动安装） |
| **ext-ev**（可选） | * | 10-20 倍性能提升 🚀 |
| **ext-event**（可选） | * | 3-5 倍性能提升 ⚡ |

## 📦 安装

```bash
composer require pfinalclub/asyncio-http-core
```

### 🔥 性能提升（推荐）

对于生产环境，安装 `ev` 扩展以获得最大性能：

```bash
# macOS
brew install libev
pecl install ev

# Ubuntu/Debian
sudo apt-get install libev-dev
pecl install ev

# CentOS/RHEL
sudo yum install libev-devel
pecl install ev
```

**性能对比：**

| 事件循环 | 吞吐量 | 速度 |
|---------|-------|------|
| Select（默认） | 80 req/s | 1x 基准 |
| Event | 322 req/s | 4x 更快 ⚡ |
| Ev | 833 req/s | **10.4x 更快** 🚀 |

## 🚀 快速开始

完整示例请查看 [examples/](examples/) 目录和现有的 README.md 文件。

## 📚 文档

### 核心文档

- [API 参考](docs/api-reference.md)
- [中间件指南](docs/middleware.md)
- [并发请求](docs/concurrent-requests.md)
- [错误处理](docs/error-handling.md)
- [性能调优](docs/performance.md)

### 生态系统文档

- [生态系统集成指南](ECOSYSTEM.md) - 如何与其他 pfinal-asyncio 扩展包集成
- [升级指南](UPGRADE.md) - 版本升级说明
- [变更日志](CHANGELOG.md) - 完整变更历史

### 生态系统扩展包

**pfinal-asyncio** 生态系统的一部分：

- [**pfinalclub/asyncio**](https://github.com/pfinalclub/pfinal-asyncio) - 核心异步运行时
- [**pfinalclub/asyncio-database**](https://github.com/pfinalclub/asyncio-database) - 异步数据库连接池
- [**pfinalclub/asyncio-redis**](https://github.com/pfinalclub/asyncio-redis) - 异步 Redis 客户端

## 🧪 测试

```bash
# 运行所有测试
composer test

# 运行特定测试套件
composer test:unit
composer test:integration

# 生成覆盖率报告
composer test:coverage

# 运行静态分析
composer phpstan
composer psalm
composer analyse

# 修复代码风格
composer cs-fix

# 运行完整 QA 套件
composer qa
```

## 📊 性能基准测试

运行基准测试查看性能指标：

```bash
composer benchmark
```

示例结果（100 个并发请求）：

```
事件循环      | 时间(秒) | 吞吐量     | 速度
-------------+----------+-----------+-------
Select       |   1.25   |  80 req/s | 1x
Event        |   0.31   | 322 req/s | 4x ⚡
Ev           |   0.12   | 833 req/s | 10.4x 🚀
```

## 🤝 贡献

欢迎贡献！请阅读我们的 [贡献指南](CONTRIBUTING.md) 了解详情。

### 开发环境设置

```bash
git clone https://github.com/pfinalclub/asyncio-http-core.git
cd asyncio-http-core
composer install
composer test
```

## 📄 许可证

本项目采用 MIT 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

## 🙏 致谢

- [**pfinalclub/asyncio**](https://github.com/pfinalclub/pfinal-asyncio) - 核心异步框架
- [**Workerman**](https://www.workerman.net/) - 高性能事件循环
- [**Python asyncio**](https://docs.python.org/3/library/asyncio.html) - API 设计灵感
- [**Guzzle**](https://github.com/guzzle/guzzle) - PSR 标准参考

## 📞 支持

- **文档**: [GitHub Wiki](https://github.com/pfinalclub/asyncio-http-core/wiki)
- **问题**: [GitHub Issues](https://github.com/pfinalclub/asyncio-http-core/issues)
- **讨论**: [GitHub Discussions](https://github.com/pfinalclub/asyncio-http-core/discussions)
- **主项目**: [pfinal-asyncio](https://github.com/pfinalclub/pfinal-asyncio)

## 🌟 Star 历史

如果你觉得这个项目有用，请考虑给它一个 Star！⭐

---

<div align="center">

**版本**: 1.0.0  
**发布日期**: 2025-01-24  
**状态**: 稳定版本

🚀 **PHP 生产级异步 HTTP 客户端！**

*由 pfinal-asyncio 团队用 ❤️ 构建*

</div>

