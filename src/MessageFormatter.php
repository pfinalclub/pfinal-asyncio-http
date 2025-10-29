<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP 消息格式化器
 * 用于日志记录
 */
class MessageFormatter
{
    /**
     * Apache 格式
     */
    public const CLF = "{hostname} {req_header_User-Agent} - [{date_common_log}] \"{method} {target} HTTP/{version}\" {code} {res_header_Content-Length}";

    /**
     * 调试格式
     */
    public const DEBUG = ">>>>>>>>\n{request}\n<<<<<<<<\n{response}\n--------\n{error}";

    /**
     * 简短格式
     */
    public const SHORT = '[{ts}] "{method} {target} HTTP/{version}" {code}';

    private string $template;

    public function __construct(string $template = self::CLF)
    {
        $this->template = $template;
    }

    /**
     * 格式化消息
     */
    public function format(
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?\Throwable $error = null
    ): string {
        $message = $this->template;

        // 基础变量
        $message = $this->replace($message, [
            'request' => $this->formatRequest($request),
            'response' => $response ? $this->formatResponse($response) : 'NULL',
            'error' => $error ? $error->getMessage() : 'NULL',
            'method' => $request->getMethod(),
            'target' => $request->getRequestTarget(),
            'uri' => (string)$request->getUri(),
            'url' => (string)$request->getUri(),
            'host' => $request->getUri()->getHost(),
            'hostname' => gethostname(),
            'version' => $request->getProtocolVersion(),
            'port' => $request->getUri()->getPort(),
            'code' => $response ? $response->getStatusCode() : 'NULL',
            'phrase' => $response ? $response->getReasonPhrase() : 'NULL',
            'ts' => gmdate('c'),
            'date_iso_8601' => gmdate('c'),
            'date_common_log' => gmdate('d/M/Y:H:i:s O'),
        ]);

        // 请求头
        foreach ($request->getHeaders() as $name => $values) {
            $placeholder = "req_header_{$name}";
            $message = $this->replace($message, [
                $placeholder => implode(', ', $values),
            ]);
        }

        // 响应头
        if ($response) {
            foreach ($response->getHeaders() as $name => $values) {
                $placeholder = "res_header_{$name}";
                $message = $this->replace($message, [
                    $placeholder => implode(', ', $values),
                ]);
            }
        }

        // 请求体
        $message = str_replace('{req_body}', $this->formatBody($request->getBody()), $message);

        // 响应体
        if ($response) {
            $message = str_replace('{res_body}', $this->formatBody($response->getBody()), $message);
        }

        return $message;
    }

    /**
     * 替换占位符
     */
    private function replace(string $message, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $message = str_replace('{' . $key . '}', (string)$value, $message);
        }

        return $message;
    }

    /**
     * 格式化请求
     */
    private function formatRequest(RequestInterface $request): string
    {
        $lines = [];
        $lines[] = sprintf(
            '%s %s HTTP/%s',
            $request->getMethod(),
            $request->getRequestTarget(),
            $request->getProtocolVersion()
        );

        foreach ($request->getHeaders() as $name => $values) {
            $lines[] = "{$name}: " . implode(', ', $values);
        }

        $body = $this->formatBody($request->getBody(), 1000);
        if ($body) {
            $lines[] = '';
            $lines[] = $body;
        }

        return implode("\n", $lines);
    }

    /**
     * 格式化响应
     */
    private function formatResponse(ResponseInterface $response): string
    {
        $lines = [];
        $lines[] = sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );

        foreach ($response->getHeaders() as $name => $values) {
            $lines[] = "{$name}: " . implode(', ', $values);
        }

        $body = $this->formatBody($response->getBody(), 1000);
        if ($body) {
            $lines[] = '';
            $lines[] = $body;
        }

        return implode("\n", $lines);
    }

    /**
     * 格式化消息体
     */
    private function formatBody($body, int $maxLength = 0): string
    {
        if (!$body) {
            return '';
        }

        // 获取当前位置
        $pos = $body->tell();

        // 如果流不可读，返回占位符
        if (!$body->isReadable() || !$body->isSeekable()) {
            return '<body is not readable>';
        }

        // 读取内容
        $body->rewind();
        $content = $body->getContents();

        // 恢复位置
        $body->seek($pos);

        // 限制长度
        if ($maxLength > 0 && strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength) . ' ...';
        }

        return $content;
    }
}

