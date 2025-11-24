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
use PfinalClub\Asyncio\Http\AsyncHttpClient;

/**
 * pfinal-asyncio 处理器
 * 复用 pfinalclub/asyncio 的 AsyncHttpClient
 * 只负责 PSR-7 适配层
 */
class AsyncioHandler implements HandlerInterface
{
    private AsyncHttpClient $client;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'timeout' => 30,
            'verify' => true,
            'follow_redirects' => false, // 由中间件处理
        ], $config);
        
        // 初始化 pfinalclub/asyncio 的 AsyncHttpClient
        // 注意：AsyncHttpClient 不直接支持 verify 选项，需要通过其他方式处理 SSL
        $this->client = new AsyncHttpClient([
            'timeout' => $this->config['timeout'],
            'follow_redirects' => $this->config['follow_redirects'],
            'use_connection_manager' => true,
        ]);
    }

    public function handle(RequestInterface $request, array $options = []): ResponseInterface
    {
        try {
            // 构建完整 URL
            $url = (string)$request->getUri();
            
            // 准备请求方法
            $method = $request->getMethod();
            
            // 转换头部为简单数组格式
            $headers = [];
            foreach ($request->getHeaders() as $name => $values) {
                $headers[$name] = implode(', ', $values);
            }
            
            // 获取请求体
            $body = (string)$request->getBody();
            
            // 调用 pfinalclub/asyncio 的 AsyncHttpClient
            $asyncioResponse = $this->client->request($method, $url, $body, $headers);
            
            // 转换为 PSR-7 Response
            return $this->convertToPsr7Response($asyncioResponse);
            
        } catch (\RuntimeException $e) {
            // 连接错误
            throw new ConnectException(
                sprintf('Connection to %s failed: %s', $url, $e->getMessage()),
                $request,
                null,
                $e
            );
        } catch (\Throwable $e) {
            // 其他错误
            throw new RequestException(
                $e->getMessage(),
                $request,
                null,
                $e
            );
        }
    }
    
    /**
     * 转换 AsyncHttpClient 的响应为 PSR-7 Response
     */
    private function convertToPsr7Response($asyncioResponse): ResponseInterface
    {
        // 创建 PSR-7 Stream
        $stream = new Stream('php://temp', 'rw+');
        $stream->write($asyncioResponse->getBody());
        $stream->rewind();
        
        // 转换头部格式
        $headers = [];
        foreach ($asyncioResponse->getHeaders() as $name => $value) {
            $headers[$name] = is_array($value) ? $value : [$value];
        }
        
        // 创建 PSR-7 Response
        return new Response(
            $asyncioResponse->getStatusCode(),
            $headers,
            $stream,
            '1.1',
            '' // reason phrase 可以为空
        );
    }
    
    /**
     * 获取底层 AsyncHttpClient
     */
    public function getAsyncHttpClient(): AsyncHttpClient
    {
        return $this->client;
    }

}

