<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;

/**
 * 认证中间件
 * 添加认证信息到请求
 */
class AuthMiddleware
{
    private string $username;
    private string $password;
    private string $type;

    public function __construct(string $username, string $password, string $type = 'basic')
    {
        $this->username = $username;
        $this->password = $password;
        $this->type = strtolower($type);
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $request = $this->addAuth($request);
            return $handler($request, $options);
        };
    }

    private function addAuth(RequestInterface $request): RequestInterface
    {
        switch ($this->type) {
            case 'basic':
                $credentials = base64_encode($this->username . ':' . $this->password);
                return $request->withHeader('Authorization', 'Basic ' . $credentials);

            case 'bearer':
                // Bearer token 模式，username 作为 token
                return $request->withHeader('Authorization', 'Bearer ' . $this->username);

            case 'digest':
                // Digest 认证需要服务器的 challenge，这里只是占位
                // 实际实现需要处理 401 响应和 WWW-Authenticate 头
                return $request;

            default:
                return $request;
        }
    }
}

