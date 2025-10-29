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

use function PfinalClub\Asyncio\{create_future, await_future};

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
        $timerId = null;
        $isChunked = false;
        $expectedLength = null;

        // 连接成功
        $connection->onConnect = function ($connection) use ($request, $options, &$timerId) {
            // 发送 HTTP 请求
            $connection->send($this->buildHttpRequest($request));

            // 设置超时
            if (isset($options[RequestOptions::TIMEOUT])) {
                $timeout = $options[RequestOptions::TIMEOUT];
                if ($timeout > 0) {
                    $timerId = \Workerman\Timer::add($timeout, function () use ($connection, &$timerId) {
                        if ($timerId) {
                            \Workerman\Timer::del($timerId);
                            $timerId = null;
                        }
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
            &$bodyData,
            &$isChunked,
            &$expectedLength
        ) {
            if (!$headersParsed) {
                // 头部未解析，继续累积数据
                $responseData .= $data;
                
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

                    // 检查编码方式
                    if (isset($headers['Transfer-Encoding']) && 
                        in_array('chunked', $headers['Transfer-Encoding'])) {
                        $isChunked = true;
                    } elseif (isset($headers['Content-Length'])) {
                        $expectedLength = (int) $headers['Content-Length'][0];
                    }

                    // 检查是否已接收完整
                    if ($this->isResponseComplete($bodyData, $isChunked, $expectedLength)) {
                        $connection->close();
                    }
                }
            } else {
                // 头部已解析，继续接收响应体
                $bodyData .= $data;

                // 检查是否接收完整个响应体
                if ($this->isResponseComplete($bodyData, $isChunked, $expectedLength)) {
                    $connection->close();
                }
            }
        };

        // 连接关闭
        $connection->onClose = function () use (
            $future,
            &$statusCode,
            &$reasonPhrase,
            &$headers,
            &$bodyData,
            &$timerId,
            &$isChunked
        ) {
            // 清理定时器
            if ($timerId) {
                \Workerman\Timer::del($timerId);
                $timerId = null;
            }

            try {
                // 如果是 chunked 编码，解码响应体
                if ($isChunked) {
                    $bodyData = $this->decodeChunked($bodyData);
                }

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

        // 自动添加 Host 头
        if (!$request->hasHeader('Host')) {
            $host = $uri->getHost();
            if ($port = $uri->getPort()) {
                $defaultPort = ($uri->getScheme() === 'https') ? 443 : 80;
                if ($port !== $defaultPort) {
                    $host .= ':' . $port;
                }
            }
            $request = $request->withHeader('Host', $host);
        }

        // 自动添加 Content-Length 头
        $bodySize = $request->getBody()->getSize();
        if ($bodySize !== null && $bodySize > 0 && !$request->hasHeader('Content-Length') && !$request->hasHeader('Transfer-Encoding')) {
            $request = $request->withHeader('Content-Length', (string)$bodySize);
        }

        // 构建请求行
        $message = sprintf(
            "%s %s HTTP/%s\r\n",
            $request->getMethod(),
            $path,
            $request->getProtocolVersion()
        );

        // 添加请求头
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $message .= sprintf("%s: %s\r\n", $name, $value);
            }
        }

        $message .= "\r\n";
        $message .= (string) $request->getBody();

        return $message;
    }

    /**
     * 检查响应是否接收完整
     */
    private function isResponseComplete(string $bodyData, bool $isChunked, ?int $expectedLength): bool
    {
        if ($isChunked) {
            // 检查 chunked 编码是否结束（以 "0\r\n\r\n" 结尾）
            return str_ends_with($bodyData, "0\r\n\r\n") || str_ends_with($bodyData, "0\r\n");
        }

        if ($expectedLength !== null) {
            return strlen($bodyData) >= $expectedLength;
        }

        // 没有 Content-Length 也没有 chunked，依赖连接关闭
        return false;
    }

    /**
     * 解码 chunked 编码的响应体
     */
    private function decodeChunked(string $data): string
    {
        $decoded = '';
        $offset = 0;

        while ($offset < strlen($data)) {
            // 查找 chunk 大小行的结束位置
            $crlfPos = strpos($data, "\r\n", $offset);
            if ($crlfPos === false) {
                break;
            }

            // 读取 chunk 大小
            $chunkSizeLine = substr($data, $offset, $crlfPos - $offset);
            
            // 移除可能的 chunk extension（在分号之后）
            $semicolonPos = strpos($chunkSizeLine, ';');
            if ($semicolonPos !== false) {
                $chunkSizeLine = substr($chunkSizeLine, 0, $semicolonPos);
            }

            $chunkSize = hexdec(trim($chunkSizeLine));
            
            // 如果 chunk 大小为 0，表示结束
            if ($chunkSize === 0) {
                break;
            }

            // 移动到 chunk 数据的开始位置
            $offset = $crlfPos + 2;

            // 读取 chunk 数据
            if ($offset + $chunkSize <= strlen($data)) {
                $decoded .= substr($data, $offset, $chunkSize);
                $offset += $chunkSize + 2; // +2 跳过 chunk 后的 \r\n
            } else {
                // 数据不完整
                break;
            }
        }

        return $decoded;
    }
}

