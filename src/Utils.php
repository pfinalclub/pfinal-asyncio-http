<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp;

use Psr\Http\Message\UriInterface;

/**
 * 通用工具函数类
 */
class Utils
{
    /**
     * 获取默认 User-Agent
     */
    public static function defaultUserAgent(): string
    {
        $version = '1.0.0'; // TODO: 从 composer.json 读取
        $phpVersion = PHP_VERSION;
        
        return "PFinal-AsyncIO-HTTP/{$version} PHP/{$phpVersion}";
    }

    /**
     * 标准化 HTTP 头名称
     * 
     * @param array $headers
     * @return array
     */
    public static function normalizeHeaderKeys(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            $normalized[self::normalizeHeaderKey($key)] = $value;
        }

        return $normalized;
    }

    /**
     * 标准化单个头名称
     */
    public static function normalizeHeaderKey(string $key): string
    {
        return implode('-', array_map('ucfirst', explode('-', strtolower($key))));
    }

    /**
     * 检查主机是否在 no_proxy 列表中
     */
    public static function isHostInNoProxy(string $host, array $noProxy): bool
    {
        if (empty($noProxy)) {
            return false;
        }

        $host = strtolower($host);

        foreach ($noProxy as $pattern) {
            $pattern = strtolower(trim($pattern));

            if ($pattern === '*') {
                return true;
            }

            if ($pattern === $host) {
                return true;
            }

            // 通配符匹配（*.example.com）
            if (str_starts_with($pattern, '*.')) {
                $domain = substr($pattern, 2);
                if (str_ends_with($host, '.' . $domain) || $host === $domain) {
                    return true;
                }
            }

            // 子域名匹配（.example.com）
            if (str_starts_with($pattern, '.')) {
                $domain = substr($pattern, 1);
                if (str_ends_with($host, '.' . $domain) || $host === $domain) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * JSON 编码（带错误处理）
     * 
     * @throws \InvalidArgumentException
     */
    public static function jsonEncode($value, int $options = 0, int $depth = 512): string
    {
        $json = json_encode($value, $options, $depth);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(
                'JSON encode error: ' . json_last_error_msg()
            );
        }

        return $json;
    }

    /**
     * JSON 解码（带错误处理）
     * 
     * @throws \InvalidArgumentException
     */
    public static function jsonDecode(string $json, bool $assoc = true, int $depth = 512, int $options = 0)
    {
        $data = json_decode($json, $assoc, $depth, $options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(
                'JSON decode error: ' . json_last_error_msg()
            );
        }

        return $data;
    }

    /**
     * 获取默认 CA 证书路径
     */
    public static function defaultCaBundle(): ?string
    {
        // 常见的 CA 证书位置
        $locations = [
            '/etc/ssl/certs/ca-certificates.crt',     // Debian/Ubuntu/Gentoo
            '/etc/pki/tls/certs/ca-bundle.crt',        // Fedora/RHEL/CentOS
            '/etc/ssl/ca-bundle.pem',                  // OpenSUSE
            '/etc/ssl/cert.pem',                       // OpenBSD
            '/usr/local/share/certs/ca-root-nss.crt',  // FreeBSD
            '/etc/ssl/certs',                          // SLES
            '/etc/pki/tls/cert.pem',                   // AIX
            '/System/Library/OpenSSL/certs/cert.pem',  // macOS (old)
        ];

        foreach ($locations as $location) {
            if (file_exists($location)) {
                return $location;
            }
        }

        return null;
    }

    /**
     * URI 模板扩展（RFC 6570 简化版）
     * 
     * @param string $template URI 模板
     * @param array $variables 变量
     * @return string
     */
    public static function uriTemplate(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{([a-zA-Z0-9_]+)\}/',
            function ($matches) use ($variables) {
                $key = $matches[1];
                return isset($variables[$key]) ? urlencode((string)$variables[$key]) : '';
            },
            $template
        );
    }

    /**
     * 描述变量类型（用于错误消息）
     */
    public static function describeType($input): string
    {
        switch (gettype($input)) {
            case 'object':
                return 'object(' . get_class($input) . ')';
            case 'array':
                return 'array(' . count($input) . ')';
            case 'NULL':
                return 'null';
            case 'boolean':
                return 'bool(' . ($input ? 'true' : 'false') . ')';
            case 'resource':
                return 'resource(' . get_resource_type($input) . ')';
            default:
                return gettype($input) . '(' . var_export($input, true) . ')';
        }
    }

    /**
     * 解析 Content-Type 头
     * 
     * @return array ['type' => 'text/html', 'charset' => 'utf-8', ...]
     */
    public static function parseContentType(string $contentType): array
    {
        $parts = array_map('trim', explode(';', $contentType));
        $result = ['type' => array_shift($parts)];

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $result[trim($key)] = trim($value, '" ');
            }
        }

        return $result;
    }

    /**
     * 格式化字节大小
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * 修剪字符串到指定长度
     */
    public static function truncate(string $str, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($str) <= $length) {
            return $str;
        }

        return mb_substr($str, 0, $length - mb_strlen($suffix)) . $suffix;
    }

    /**
     * 安全地打开文件
     * 
     * @throws \RuntimeException
     */
    public static function tryFopen(string $filename, string $mode)
    {
        $ex = null;
        set_error_handler(function ($errno, $errstr) use (&$ex) {
            $ex = new \RuntimeException($errstr, $errno);
        });

        $handle = fopen($filename, $mode);
        restore_error_handler();

        if ($ex) {
            throw $ex;
        }

        return $handle;
    }

    /**
     * 复制流到另一个流
     */
    public static function copyToStream($source, $dest, int $maxLen = -1): int
    {
        if (!is_resource($source)) {
            throw new \InvalidArgumentException('Source must be a stream resource');
        }

        if (!is_resource($dest)) {
            throw new \InvalidArgumentException('Destination must be a stream resource');
        }

        $bytes = 0;
        $bufferSize = 8192;

        while (!feof($source) && ($maxLen === -1 || $bytes < $maxLen)) {
            $readLen = $maxLen === -1 ? $bufferSize : min($bufferSize, $maxLen - $bytes);
            $buf = fread($source, $readLen);

            if ($buf === false) {
                break;
            }

            $written = fwrite($dest, $buf);
            if ($written === false) {
                break;
            }

            $bytes += $written;
        }

        return $bytes;
    }

    /**
     * 复制流到字符串
     */
    public static function copyToString($source, int $maxLen = -1): string
    {
        if (!is_resource($source)) {
            throw new \InvalidArgumentException('Source must be a stream resource');
        }

        $buffer = '';
        $bytes = 0;
        $bufferSize = 8192;

        while (!feof($source) && ($maxLen === -1 || $bytes < $maxLen)) {
            $readLen = $maxLen === -1 ? $bufferSize : min($bufferSize, $maxLen - $bytes);
            $buf = fread($source, $readLen);

            if ($buf === false) {
                break;
            }

            $buffer .= $buf;
            $bytes += strlen($buf);
        }

        return $buffer;
    }

    /**
     * 读取行（类似 fgets，但支持 StreamInterface）
     */
    public static function readLine($stream, int $maxLength = null): string
    {
        $buffer = '';
        $size = 0;

        while (!feof($stream)) {
            if ($maxLength && $size >= $maxLength) {
                break;
            }

            $byte = fread($stream, 1);
            if ($byte === false || $byte === '') {
                break;
            }

            $buffer .= $byte;
            $size++;

            if ($byte === "\n") {
                break;
            }
        }

        return $buffer;
    }

    /**
     * 修改查询字符串
     */
    public static function modifyQuery(UriInterface $uri, array $changes): UriInterface
    {
        parse_str($uri->getQuery(), $params);
        $params = array_merge($params, $changes);

        return $uri->withQuery(http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }

    /**
     * 获取 MIME 类型（从文件名）
     */
    public static function mimetype(string $filename): ?string
    {
        static $mimes = [
            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
        ];

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $mimes[$ext] ?? null;
    }
}

