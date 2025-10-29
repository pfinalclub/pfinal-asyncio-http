#!/usr/bin/env php
<?php

/**
 * pfinal-asyncio-http 测试运行脚本
 * 用于执行项目的所有测试并生成覆盖率报告
 */

declare(strict_types=1);

ini_set('display_errors', 'stderr');

// 检查是否安装了PHPUnit
if (!file_exists(__DIR__ . '/vendor/bin/phpunit')) {
    echo "错误: 未找到PHPUnit。请先运行 'composer install' 安装依赖。\n";
    exit(1);
}

// 检查命令行参数
$options = getopt('c::', ['coverage::']);
$generateCoverage = isset($options['c']) || isset($options['coverage']);

// 确定测试命令
$command = __DIR__ . '/vendor/bin/phpunit';

if ($generateCoverage) {
    $command .= ' --coverage-html ' . __DIR__ . '/coverage';
}

// 添加颜色输出
$command .= ' --colors=always';

// 执行测试
passthru($command, $exitCode);

// 输出结果
if ($exitCode === 0) {
    echo "\n✅ 所有测试通过！\n";
    if ($generateCoverage) {
        echo "📊 覆盖率报告已生成在 ./coverage 目录\n";
    }
} else {
    echo "\n❌ 测试失败！\n";
}

exit($exitCode);