# 贡献指南

感谢你考虑为 PFinal AsyncIO HTTP 做出贡献！

## 如何贡献

### 报告 Bug

如果你发现了 bug，请创建一个 issue 并包含以下信息：

- 清晰的标题和描述
- 重现步骤
- 期望行为
- 实际行为
- PHP 版本和环境信息
- 相关的代码示例或错误日志

### 提交功能请求

我们欢迎新功能的建议！请创建一个 issue 并说明：

- 功能的用途和好处
- 可能的实现方式
- 是否愿意协助实现

### Pull Request 流程

1. **Fork 项目**
   ```bash
   git clone https://github.com/pfinalclub/pfinal-asyncio-http.git
   cd pfinal-asyncio-http
   ```

2. **创建分支**
   ```bash
   git checkout -b feature/your-feature-name
   # 或
   git checkout -b fix/your-bug-fix
   ```

3. **安装依赖**
   ```bash
   composer install
   ```

4. **编写代码**
   - 遵循 PSR-12 代码风格
   - 添加适当的注释和文档
   - 编写单元测试

5. **运行测试**
   ```bash
   composer test
   composer phpstan
   composer cs-check
   ```

6. **提交代码**
   ```bash
   git add .
   git commit -m "feat: 添加新功能描述"
   # 或
   git commit -m "fix: 修复 bug 描述"
   ```

7. **推送到 GitHub**
   ```bash
   git push origin feature/your-feature-name
   ```

8. **创建 Pull Request**
   - 在 GitHub 上创建 PR
   - 填写 PR 模板
   - 等待代码审查

## 代码风格

我们使用 PHP-CS-Fixer 来保持代码风格一致：

```bash
# 检查代码风格
composer cs-check

# 自动修复代码风格
composer cs-fix
```

## 测试

请确保所有测试都通过：

```bash
# 运行所有测试
composer test

# 运行特定测试
./vendor/bin/phpunit tests/Unit/Psr7/RequestTest.php

# 生成覆盖率报告
composer test-coverage
```

## 静态分析

使用 PHPStan 进行静态分析：

```bash
composer phpstan
```

## 提交信息规范

我们遵循 [Conventional Commits](https://www.conventionalcommits.org/) 规范：

- `feat:` 新功能
- `fix:` Bug 修复
- `docs:` 文档更新
- `style:` 代码格式（不影响代码运行）
- `refactor:` 重构
- `perf:` 性能优化
- `test:` 测试相关
- `chore:` 构建过程或辅助工具的变动

示例：
```
feat: 添加 OAuth2 认证支持
fix: 修复重试中间件的超时问题
docs: 更新快速开始文档
```

## 开发环境设置

### 必需软件

- PHP >= 8.1
- Composer
- Git

### 推荐工具

- PHPStorm 或 VS Code
- Xdebug（用于调试和覆盖率）

### 环境配置

```bash
# 克隆项目
git clone https://github.com/pfinalclub/pfinal-asyncio-http.git
cd pfinal-asyncio-http

# 安装依赖
composer install

# 运行测试
composer test
```

## 文档

如果你的贡献涉及新功能或 API 变更，请同时更新：

- README.md
- 相关的文档文件（docs/）
- 示例代码（examples/）
- PHPDoc 注释

## 行为准则

请阅读并遵守我们的 [行为准则](CODE_OF_CONDUCT.md)。

## 问题？

如果你有任何问题，可以：

- 创建一个 issue
- 发送邮件到 pfinal@pfinal.cn
- 在 Pull Request 中提问

## 感谢

感谢所有为这个项目做出贡献的开发者！

---

再次感谢你的贡献！ 🎉

