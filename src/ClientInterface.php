<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 客户端接口
 * 
 * 所有方法在 Fiber 中调用时自动异步
 */
interface ClientInterface
{
    /**
     * 发送请求
     */
    public function request(string $method, $uri = '', array $options = []): ResponseInterface;

    /**
     * GET 请求
     */
    public function get($uri, array $options = []): ResponseInterface;

    /**
     * HEAD 请求
     */
    public function head($uri, array $options = []): ResponseInterface;

    /**
     * PUT 请求
     */
    public function put($uri, array $options = []): ResponseInterface;

    /**
     * POST 请求
     */
    public function post($uri, array $options = []): ResponseInterface;

    /**
     * PATCH 请求
     */
    public function patch($uri, array $options = []): ResponseInterface;

    /**
     * DELETE 请求
     */
    public function delete($uri, array $options = []): ResponseInterface;

    /**
     * OPTIONS 请求
     */
    public function options($uri, array $options = []): ResponseInterface;

    /**
     * 发送 PSR-7 请求
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface;

    /**
     * 获取配置
     */
    public function getConfig(?string $option = null);
}
