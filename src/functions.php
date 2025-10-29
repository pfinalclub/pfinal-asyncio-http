<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp;

/**
 * 全局辅助函数
 */

if (!function_exists('PFinal\AsyncioHttp\describe_type')) {
    /**
     * 描述变量类型
     */
    function describe_type(mixed $input): string
    {
        return match (true) {
            is_object($input) => get_class($input),
            is_array($input) => 'array(' . count($input) . ')',
            is_resource($input) => get_resource_type($input) . ' resource',
            default => gettype($input),
        };
    }
}

if (!function_exists('PFinal\AsyncioHttp\headers_from_lines')) {
    /**
     * 从行数组解析头信息
     */
    function headers_from_lines(array $lines): array
    {
        $headers = [];

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[trim($parts[0])][] = trim($parts[1]);
            }
        }

        return $headers;
    }
}

if (!function_exists('PFinal\AsyncioHttp\normalize_header_keys')) {
    /**
     * 规范化头部键名
     */
    function normalize_header_keys(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            $result[strtolower($key)] = $value;
        }

        return $result;
    }
}

if (!function_exists('PFinal\AsyncioHttp\default_user_agent')) {
    /**
     * 获取默认 User-Agent
     */
    function default_user_agent(): string
    {
        return 'PFinal-AsyncioHttp/1.0 PHP/' . PHP_VERSION;
    }
}

