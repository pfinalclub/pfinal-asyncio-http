<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use PFinal\AsyncioHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;

/**
 * 认证中间件
 * 支持 Basic、Digest、Bearer、NTLM 等认证方式
 */
class AuthMiddleware
{
    private string $type;
    private string $username;
    private string $password;
    private array $options;

    public function __construct(string $username, string $password, string $type = 'basic', array $options = [])
    {
        $this->username = $username;
        $this->password = $password;
        $this->type = strtolower($type);
        $this->options = $options;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            // 从选项中获取认证信息
            $auth = $options[RequestOptions::AUTH] ?? null;

            if ($auth) {
                if (is_array($auth)) {
                    $username = $auth[0] ?? $this->username;
                    $password = $auth[1] ?? $this->password;
                    $type = $auth[2] ?? $this->type;
                } else {
                    $username = $this->username;
                    $password = $this->password;
                    $type = $this->type;
                }

                // 根据类型添加认证
                $request = $this->addAuth($request, $username, $password, $type);
            }

            return $handler($request, $options);
        };
    }

    /**
     * 根据类型添加认证信息
     */
    private function addAuth(RequestInterface $request, string $username, string $password, string $type): RequestInterface
    {
        return match ($type) {
            'basic' => $this->addBasicAuth($request, $username, $password),
            'digest' => $this->addDigestAuth($request, $username, $password),
            'bearer' => $this->addBearerAuth($request, $username),
            'ntlm' => $this->addNtlmAuth($request, $username, $password),
            default => $request,
        };
    }

    /**
     * 添加 Basic 认证
     */
    private function addBasicAuth(RequestInterface $request, string $username, string $password): RequestInterface
    {
        $credentials = base64_encode($username . ':' . $password);
        return $request->withHeader('Authorization', 'Basic ' . $credentials);
    }

    /**
     * 添加 Digest 认证
     * 
     * 注意：Digest 认证需要先发送一个请求获取 WWW-Authenticate 头，
     * 这里只是一个占位实现，完整的实现需要在请求失败后重试
     */
    private function addDigestAuth(RequestInterface $request, string $username, string $password): RequestInterface
    {
        // Digest 认证比较复杂，需要先获取 realm, nonce 等信息
        // 这里只添加基本的 Authorization 头
        // 完整实现需要在中间件中处理 401 响应
        
        if ($this->options['digest_cache'] ?? null) {
            $digest = $this->options['digest_cache'];
            return $request->withHeader('Authorization', 'Digest ' . $digest);
        }

        return $request;
    }

    /**
     * 添加 Bearer 认证（OAuth 2.0）
     */
    private function addBearerAuth(RequestInterface $request, string $token): RequestInterface
    {
        return $request->withHeader('Authorization', 'Bearer ' . $token);
    }

    /**
     * 添加 NTLM 认证
     * 
     * 注意：NTLM 认证需要多轮握手，这里只是占位实现
     */
    private function addNtlmAuth(RequestInterface $request, string $username, string $password): RequestInterface
    {
        // NTLM 认证需要多轮握手
        // 完整实现需要使用 NTLM 协议
        return $request;
    }

    /**
     * 创建 Basic 认证中间件
     */
    public static function basic(string $username, string $password): self
    {
        return new self($username, $password, 'basic');
    }

    /**
     * 创建 Bearer 认证中间件
     */
    public static function bearer(string $token): self
    {
        return new self($token, '', 'bearer');
    }

    /**
     * 创建 Digest 认证中间件
     */
    public static function digest(string $username, string $password, array $options = []): self
    {
        return new self($username, $password, 'digest', $options);
    }
}
