<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Handler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 请求处理器接口
 */
interface HandlerInterface
{
    /**
     * 处理请求并返回响应
     *
     * @param RequestInterface $request
     * @param array $options
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, array $options = []): ResponseInterface;
}

