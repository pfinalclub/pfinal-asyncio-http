<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp;

use PFinal\AsyncioHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 客户端接口
 */
interface ClientInterface
{
    /**
     * 发送请求
     */
    public function request(string $method, $uri = '', array $options = []): ResponseInterface;

    /**
     * 异步发送请求
     */
    public function requestAsync(string $method, $uri = '', array $options = []): PromiseInterface;

    /**
     * GET 请求
     */
    public function get($uri, array $options = []): ResponseInterface;

    /**
     * 异步 GET 请求
     */
    public function getAsync($uri, array $options = []): PromiseInterface;

    /**
     * HEAD 请求
     */
    public function head($uri, array $options = []): ResponseInterface;

    /**
     * 异步 HEAD 请求
     */
    public function headAsync($uri, array $options = []): PromiseInterface;

    /**
     * PUT 请求
     */
    public function put($uri, array $options = []): ResponseInterface;

    /**
     * 异步 PUT 请求
     */
    public function putAsync($uri, array $options = []): PromiseInterface;

    /**
     * POST 请求
     */
    public function post($uri, array $options = []): ResponseInterface;

    /**
     * 异步 POST 请求
     */
    public function postAsync($uri, array $options = []): PromiseInterface;

    /**
     * PATCH 请求
     */
    public function patch($uri, array $options = []): ResponseInterface;

    /**
     * 异步 PATCH 请求
     */
    public function patchAsync($uri, array $options = []): PromiseInterface;

    /**
     * DELETE 请求
     */
    public function delete($uri, array $options = []): ResponseInterface;

    /**
     * 异步 DELETE 请求
     */
    public function deleteAsync($uri, array $options = []): PromiseInterface;

    /**
     * OPTIONS 请求
     */
    public function options($uri, array $options = []): ResponseInterface;

    /**
     * 异步 OPTIONS 请求
     */
    public function optionsAsync($uri, array $options = []): PromiseInterface;

    /**
     * 发送 PSR-7 请求
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface;

    /**
     * 异步发送 PSR-7 请求
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface;

    /**
     * 获取配置
     */
    public function getConfig(?string $option = null);
}

