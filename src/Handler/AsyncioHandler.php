<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Handler;

use PFinal\AsyncioHttp\Exception\ConnectException;
use PFinal\AsyncioHttp\Exception\RequestException;
use PFinal\AsyncioHttp\Exception\TimeoutException;
use PFinal\AsyncioHttp\Psr7\Response;
use PFinal\AsyncioHttp\Psr7\Stream;
use PFinal\AsyncioHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Workerman\Connection\AsyncTcpConnection;

use function PFinal\Asyncio\{create_future, await_future};

/**
 * pfinal-asyncio 处理器
 * 使用 Workerman AsyncTcpConnection 实现异步 HTTP 请求
 */
class AsyncioHandler implements HandlerInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => true,
        ], $config);
    }

    public function handle(RequestInterface $request, array $options = []): ResponseInterface
    {
        $options = array_merge($this->config, $options);
        $uri = $request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort() ?? ($scheme === 'https' ? 443 : 80);

        // 构建连接地址
        $address = ($scheme === 'https' ? 'ssl://' : 'tcp://') . $host . ':' . $port;

        // 创建异步连接
        $connection = new AsyncTcpConnection($address);

        // SSL 配置
        if ($scheme === 'https') {
            $connection->transport = 'ssl';
            if (!empty($options[RequestOptions::VERIFY])) {
                $connection->context = [
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'allow_self_signed' => false,
                    ],
                ];
            } else {
                $connection->context = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ];
            }
        }

        $future = create_future();
        $responseData = '';
        $headersParsed = false;
        $statusCode = 200;
        $reasonPhrase = 'OK';
        $headers = [];
        $bodyData = '';

        // 连接成功
        $connection->onConnect = function ($connection) use ($request, $options) {
            // 发送 HTTP 请求
            $connection->send($this->buildHttpRequest($request));

            // 设置超时
            if (isset($options[RequestOptions::TIMEOUT])) {
                $timeout = $options[RequestOptions::TIMEOUT];
                if ($timeout > 0) {
                    \Workerman\Timer::add($timeout, function () use ($connection) {
                        $connection->close();
                    }, [], false);
                }
            }
        };

        // 接收数据
        $connection->onMessage = function ($connection, $data) use (
            &$responseData,
            &$headersParsed,
            &$statusCode,
            &$reasonPhrase,
            &$headers,
            &$bodyData
        ) {
            $responseData .= $data;

            if (!$headersParsed) {
                // 检查是否接收完整个头部
                if (strpos($responseData, "\r\n\r\n") !== false) {
                    [$headerSection, $bodyData] = explode("\r\n\r\n", $responseData, 2);
                    $headerLines = explode("\r\n", $headerSection);
                    $statusLine = array_shift($headerLines);

                    // 解析状态行
                    if (preg_match('#^HTTP/\d\.\d (\d{3})\s*(.*)$#', $statusLine, $matches)) {
                        $statusCode = (int) $matches[1];
                        $reasonPhrase = $matches[2];
                    }

                    // 解析头部
                    foreach ($headerLines as $line) {
                        if (strpos($line, ':') !== false) {
                            [$name, $value] = explode(':', $line, 2);
                            $headers[trim($name)][] = trim($value);
                        }
                    }

                    $headersParsed = true;

                    // 检查 Content-Length
                    if (isset($headers['Content-Length'])) {
                        $contentLength = (int) $headers['Content-Length'][0];
                        if (strlen($bodyData) >= $contentLength) {
                            $connection->close();
                        }
                    } elseif (isset($headers['Transfer-Encoding']) && 
                              in_array('chunked', $headers['Transfer-Encoding'])) {
                        // TODO: 处理 chunked 编码
                    }
                }
            } else {
                $bodyData .= $data;

                // 检查是否接收完整个响应体
                if (isset($headers['Content-Length'])) {
                    $contentLength = (int) $headers['Content-Length'][0];
                    if (strlen($bodyData) >= $contentLength) {
                        $connection->close();
                    }
                }
            }
        };

        // 连接关闭
        $connection->onClose = function () use (
            $future,
            &$statusCode,
            &$reasonPhrase,
            &$headers,
            &$bodyData
        ) {
            try {
                // 创建响应
                $stream = new Stream('php://temp', 'rw+');
                $stream->write($bodyData);
                $stream->rewind();

                $response = new Response(
                    $statusCode,
                    $headers,
                    $stream,
                    '1.1',
                    $reasonPhrase
                );

                $future->setResult($response);
            } catch (\Throwable $e) {
                $future->setException($e);
            }
        };

        // 连接错误
        $connection->onError = function ($connection, $code, $msg) use ($future, $request) {
            $future->setException(
                new ConnectException(
                    sprintf('Connection failed: [%d] %s', $code, $msg),
                    $request
                )
            );
        };

        // 启动连接
        $connection->connect();

        try {
            return await_future($future);
        } catch (\Throwable $e) {
            if ($e instanceof ConnectException) {
                throw $e;
            }

            throw new RequestException(
                $e->getMessage(),
                $request,
                null,
                $e
            );
        }
    }

    private function buildHttpRequest(RequestInterface $request): string
    {
        $uri = $request->getUri();
        $path = $uri->getPath() ?: '/';
        if ($query = $uri->getQuery()) {
            $path .= '?' . $query;
        }

        $message = sprintf(
            "%s %s HTTP/%s\r\n",
            $request->getMethod(),
            $path,
            $request->getProtocolVersion()
        );

        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $message .= sprintf("%s: %s\r\n", $name, $value);
            }
        }

        $message .= "\r\n";
        $message .= (string) $request->getBody();

        return $message;
    }
}

