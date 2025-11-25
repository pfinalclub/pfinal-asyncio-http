.PHONY: help install test coverage phpstan psalm cs-fix cs-check qa benchmark examples clean

help: ## 显示帮助信息
	@echo "可用命令："
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

install: ## 安装依赖
	composer install

update: ## 更新依赖
	composer update

test: ## 运行测试
	composer test

test-unit: ## 运行单元测试
	composer test:unit

test-integration: ## 运行集成测试
	composer test:integration

coverage: ## 生成测试覆盖率报告
	composer test:coverage
	@echo "覆盖率报告: coverage/index.html"

phpstan: ## 运行 PHPStan 静态分析
	composer phpstan

psalm: ## 运行 Psalm 静态分析
	composer psalm

cs-fix: ## 自动修复代码风格
	composer cs-fix

cs-check: ## 检查代码风格
	composer cs-check

qa: ## 运行完整的质量检查
	composer qa

benchmark: ## 运行性能测试
	composer benchmark

examples: ## 运行示例
	@echo "运行基础示例..."
	php examples/01_basic_request.php

clean: ## 清理临时文件
	rm -rf vendor
	rm -rf coverage
	rm -rf .phpunit.cache
	rm -f composer.lock
	@echo "清理完成！"

fresh: clean install ## 全新安装
	@echo "全新安装完成！"

