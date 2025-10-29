<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use PFinal\AsyncioHttp\Psr7\Stream;

/**
 * 内容解码中间件
 * 自动处理 gzip、deflate 等压缩编码
 */
class DecodeContentMiddleware
{
    private bool $enabled;

    public function __construct(bool $enabled = true)
    {
        $this->enabled = $enabled;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $enabled = $options['decode_content'] ?? $this->enabled;

            if (!$enabled) {
                return $handler($request, $options);
            }

            // 添加 Accept-Encoding 头
            if (!$request->hasHeader('Accept-Encoding')) {
                $request = $request->withHeader('Accept-Encoding', 'gzip, deflate');
            }

            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) {
                    return $this->decodeResponse($response);
                }
            );
        };
    }

    private function decodeResponse(ResponseInterface $response): ResponseInterface
    {
        $encoding = $response->getHeaderLine('Content-Encoding');

        if ($encoding === '' || $encoding === 'identity') {
            return $response;
        }

        $body = $response->getBody();
        $content = (string)$body;

        // 解码内容
        $decoded = null;
        switch (strtolower($encoding)) {
            case 'gzip':
                $decoded = gzdecode($content);
                break;

            case 'deflate':
                $decoded = gzinflate($content);
                break;

            default:
                // 不支持的编码，返回原样
                return $response;
        }

        if ($decoded === false) {
            // 解码失败，返回原样
            return $response;
        }

        // 创建新的响应体
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write($decoded);
        $stream->rewind();

        // 返回解码后的响应
        return $response
            ->withBody($stream)
            ->withoutHeader('Content-Encoding')
            ->withHeader('Content-Length', (string)strlen($decoded));
    }
}

