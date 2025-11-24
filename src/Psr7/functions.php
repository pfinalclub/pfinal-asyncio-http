<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Psr7;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7 辅助函数
 */

if (!function_exists('PFinal\AsyncioHttp\Psr7\parse_header')) {
    /**
     * 解析 HTTP 头部
     */
    function parse_header(string $header): array
    {
        $parts = explode(';', $header);
        $result = ['value' => trim(array_shift($parts))];

        foreach ($parts as $part) {
            $part = trim($part);
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $result[trim($key)] = trim($value, ' "');
            } else {
                $result[] = $part;
            }
        }

        return $result;
    }
}

if (!function_exists('PFinal\AsyncioHttp\Psr7\parse_request')) {
    /**
     * 从字符串解析 HTTP 请求
     */
    function parse_request(string $message): RequestInterface
    {
        $data = parse_message($message);

        $matches = [];
        if (!preg_match('/^([A-Z]+)\s+([^\s]+)\s+HTTP\/([\d.]+)$/', $data['start-line'], $matches)) {
            throw new \InvalidArgumentException('Invalid request string');
        }

        $request = new Request(
            $matches[1],
            $matches[2],
            $data['headers'],
            $data['body'],
            $matches[3]
        );

        return $request;
    }
}

if (!function_exists('PFinal\AsyncioHttp\Psr7\parse_response')) {
    /**
     * 从字符串解析 HTTP 响应
     */
    function parse_response(string $message): ResponseInterface
    {
        $data = parse_message($message);
        $matches = [];

        if (!preg_match('/^HTTP\/([\d.]+)\s+(\d{3})\s*(.*)$/', $data['start-line'], $matches)) {
            throw new \InvalidArgumentException('Invalid response string');
        }

        $response = new Response(
            (int) $matches[2],
            $data['headers'],
            $data['body'],
            $matches[1],
            $matches[3]
        );

        return $response;
    }
}

if (!function_exists('PFinal\AsyncioHttp\Psr7\parse_message')) {
    /**
     * 解析 HTTP 消息（内部使用）
     */
    function parse_message(string $message): array
    {
        if (!$message) {
            throw new \InvalidArgumentException('Invalid message');
        }

        $message = ltrim($message, "\r\n");
        $messageParts = preg_split("/\r?\n\r?\n/", $message, 2);

        if ($messageParts === false || count($messageParts) !== 2) {
            throw new \InvalidArgumentException('Invalid message: Missing header/body separator');
        }

        [$rawHeaders, $body] = $messageParts;
        $rawHeaders = preg_split("/\r?\n/", $rawHeaders);
        $startLine = array_shift($rawHeaders);

        $headers = [];
        foreach ($rawHeaders as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $headers[$name][] = trim($parts[1]);
            }
        }

        return [
            'start-line' => $startLine,
            'headers' => $headers,
            'body' => $body,
        ];
    }
}

if (!function_exists('PFinal\AsyncioHttp\Psr7\str')) {
    /**
     * 将消息转换为字符串
     */
    function str($message): string
    {
        if ($message instanceof RequestInterface) {
            $msg = trim($message->getMethod() . ' '
                    . $message->getRequestTarget())
                . ' HTTP/' . $message->getProtocolVersion();

            if (!$message->hasHeader('host')) {
                $msg .= "\r\nHost: " . $message->getUri()->getHost();
            }
        } elseif ($message instanceof ResponseInterface) {
            $msg = 'HTTP/' . $message->getProtocolVersion() . ' '
                . $message->getStatusCode() . ' '
                . $message->getReasonPhrase();
        } else {
            throw new \InvalidArgumentException('Invalid message type');
        }

        foreach ($message->getHeaders() as $name => $values) {
            $msg .= "\r\n{$name}: " . implode(', ', $values);
        }

        $msg .= "\r\n\r\n" . $message->getBody();

        return $msg;
    }
}

if (!function_exists('PFinal\AsyncioHttp\Psr7\copy_to_string')) {
    /**
     * 将流内容复制到字符串
     */
    function copy_to_string(StreamInterface $stream, int $maxLen = -1): string
    {
        $buffer = '';

        if ($maxLen === -1) {
            while (!$stream->eof()) {
                $buf = $stream->read(1048576);
                if ($buf === '') {
                    break;
                }
                $buffer .= $buf;
            }

            return $buffer;
        }

        $len = 0;
        while (!$stream->eof() && $len < $maxLen) {
            $buf = $stream->read($maxLen - $len);
            if ($buf === '') {
                break;
            }
            $buffer .= $buf;
            $len = strlen($buffer);
        }

        return $buffer;
    }
}

if (!function_exists('PFinal\AsyncioHttp\Psr7\copy_to_stream')) {
    /**
     * 将流内容复制到另一个流
     */
    function copy_to_stream(
        StreamInterface $source,
        StreamInterface $dest,
        int $maxLen = -1
    ): void {
        if ($maxLen === -1) {
            while (!$source->eof()) {
                if (!$dest->write($source->read(1048576))) {
                    break;
                }
            }
        } else {
            $remaining = $maxLen;
            while ($remaining > 0 && !$source->eof()) {
                $buf = $source->read(min(8192, $remaining));
                $len = strlen($buf);
                if (!$len) {
                    break;
                }
                $remaining -= $len;
                $dest->write($buf);
            }
        }
    }
}

if (!function_exists('PFinal\AsyncioHttp\Psr7\try_fopen')) {
    /**
     * 安全地打开文件流
     */
    function try_fopen(string $filename, string $mode)
    {
        $ex = null;
        set_error_handler(function ($errno, $errstr) use ($filename, $mode, &$ex) {
            $ex = new \RuntimeException(sprintf(
                'Unable to open %s using mode %s: %s',
                $filename,
                $mode,
                $errstr
            ));
        });

        $handle = fopen($filename, $mode);
        restore_error_handler();

        if ($ex) {
            throw $ex;
        }

        return $handle;
    }
}

if (!function_exists('PFinal\AsyncioHttp\Psr7\rewind_body')) {
    /**
     * 尝试倒回请求/响应体
     */
    function rewind_body($message): void
    {
        $body = $message->getBody();

        if ($body->tell()) {
            $body->rewind();
        }
    }
}

if (!function_exists('PFinal\AsyncioHttp\Psr7\modify_request')) {
    /**
     * 修改请求
     */
    function modify_request(RequestInterface $request, array $changes): RequestInterface
    {
        if (!$changes) {
            return $request;
        }

        $headers = $request->getHeaders();

        if (isset($changes['set_headers'])) {
            $headers = $changes['set_headers'] + $headers;
        }

        if (isset($changes['remove_headers'])) {
            $headers = array_diff_key($headers, array_flip($changes['remove_headers']));
        }

        return new Request(
            $changes['method'] ?? $request->getMethod(),
            $changes['uri'] ?? $request->getUri(),
            $headers,
            $changes['body'] ?? $request->getBody(),
            $changes['version'] ?? $request->getProtocolVersion()
        );
    }
}

if (!function_exists('PFinal\AsyncioHttp\Psr7\uri_for')) {
    /**
     * 创建 URI 对象
     *
     * @param string|UriInterface $uri
     * @return UriInterface
     */
    function uri_for($uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }

        if (is_string($uri)) {
            return new Uri($uri);
        }

        throw new \InvalidArgumentException('URI must be a string or UriInterface');
    }
}

if (!function_exists('PFinal\AsyncioHttp\Psr7\stream_for')) {
    /**
     * 创建 Stream 对象
     *
     * @param string|resource|StreamInterface $resource
     * @return StreamInterface
     */
    function stream_for($resource = ''): StreamInterface
    {
        if ($resource instanceof StreamInterface) {
            return $resource;
        }

        if (is_string($resource)) {
            $stream = new Stream('php://temp', 'rw+');
            if ($resource !== '') {
                $stream->write($resource);
                $stream->rewind();
            }
            return $stream;
        }

        if (is_resource($resource)) {
            return new Stream($resource);
        }

        throw new \InvalidArgumentException('Invalid resource type');
    }
}

