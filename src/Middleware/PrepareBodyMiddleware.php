<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;
use PFinal\AsyncioHttp\Psr7\Stream;

/**
 * 准备请求体中间件
 * 根据请求选项准备请求体
 */
class PrepareBodyMiddleware
{
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            // 如果已经有请求体，直接传递
            if ($request->getBody()->getSize() !== null && $request->getBody()->getSize() > 0) {
                return $handler($request, $options);
            }

            // 处理 json 选项
            if (isset($options['json'])) {
                $json = json_encode($options['json']);
                if ($json === false) {
                    throw new \InvalidArgumentException('JSON encode error: ' . json_last_error_msg());
                }

                $body = new Stream(fopen('php://temp', 'r+'));
                $body->write($json);
                $body->rewind();

                $request = $request
                    ->withBody($body)
                    ->withHeader('Content-Type', 'application/json');

                unset($options['json']);
            }

            // 处理 form_params 选项
            if (isset($options['form_params']) && is_array($options['form_params'])) {
                $formData = http_build_query($options['form_params'], '', '&', PHP_QUERY_RFC3986);

                $body = new Stream(fopen('php://temp', 'r+'));
                $body->write($formData);
                $body->rewind();

                $request = $request
                    ->withBody($body)
                    ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

                unset($options['form_params']);
            }

            // 处理 multipart 选项
            if (isset($options['multipart']) && is_array($options['multipart'])) {
                $boundary = uniqid('', true);
                $multipartStream = new \PFinal\AsyncioHttp\Psr7\Stream\MultipartStream($options['multipart'], $boundary);

                $request = $request
                    ->withBody($multipartStream)
                    ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary);

                unset($options['multipart']);
            }

            // 处理 body 选项
            if (isset($options['body'])) {
                if (is_string($options['body'])) {
                    $body = new Stream(fopen('php://temp', 'r+'));
                    $body->write($options['body']);
                    $body->rewind();
                    $request = $request->withBody($body);
                } elseif (is_resource($options['body'])) {
                    $request = $request->withBody(new Stream($options['body']));
                } elseif ($options['body'] instanceof \Psr\Http\Message\StreamInterface) {
                    $request = $request->withBody($options['body']);
                }

                unset($options['body']);
            }

            return $handler($request, $options);
        };
    }
}

