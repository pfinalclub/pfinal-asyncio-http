<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 中间件工厂类
 * 提供静态方法创建各种内置中间件
 */
class Middleware
{
    /**
     * 创建 HTTP 错误处理中间件
     * 
     * @param bool $enabled 是否启用（默认 true）
     */
    public static function httpErrors(bool $enabled = true): callable
    {
        return HttpErrorsMiddleware::create($enabled);
    }

    /**
     * 创建重定向中间件
     * 
     * @param array $config 配置选项
     *  - max: int 最大重定向次数（默认 5）
     *  - strict: bool 是否严格模式（默认 false）
     *  - referer: bool 是否添加 Referer 头（默认 true）
     *  - protocols: array 允许的协议（默认 ['http', 'https']）
     *  - track_redirects: bool 是否追踪重定向（默认 false）
     */
    public static function redirect(array $config = []): callable
    {
        return new RedirectMiddleware($config);
    }

    /**
     * 创建重试中间件
     * 
     * @param array $config 配置选项
     *  - max: int 最大重试次数（默认 3）
     *  - delay: int|callable 延迟（毫秒）或延迟函数（默认 0）
     *  - on_retry: callable 重试时的回调
     *  - decide: callable 决定是否重试的函数
     */
    public static function retry(array $config = []): callable
    {
        return new RetryMiddleware($config);
    }

    /**
     * 创建 Cookie 中间件
     * 
     * @param \PFinal\AsyncioHttp\Cookie\CookieJarInterface $cookieJar Cookie 容器
     */
    public static function cookies($cookieJar): callable
    {
        return new CookieMiddleware($cookieJar);
    }

    /**
     * 创建日志中间件
     * 
     * @param \Psr\Log\LoggerInterface $logger 日志记录器
     * @param string|\PFinal\AsyncioHttp\MessageFormatterInterface $formatter 日志格式
     */
    public static function log($logger, $formatter = null): callable
    {
        return new LogMiddleware($logger, $formatter);
    }

    /**
     * 创建历史中间件
     * 
     * @param array &$container 历史记录容器（引用）
     */
    public static function history(array &$container): callable
    {
        return new HistoryMiddleware($container);
    }

    /**
     * 创建请求映射中间件
     * 
     * @param callable $fn 映射函数 (RequestInterface $request): RequestInterface
     */
    public static function mapRequest(callable $fn): callable
    {
        return new MapRequestMiddleware($fn);
    }

    /**
     * 创建响应映射中间件
     * 
     * @param callable $fn 映射函数 (ResponseInterface $response): ResponseInterface
     */
    public static function mapResponse(callable $fn): callable
    {
        return new MapResponseMiddleware($fn);
    }

    /**
     * 创建进度监控中间件
     * 
     * @param callable $callback 进度回调函数
     */
    public static function progress(callable $callback): callable
    {
        return new ProgressMiddleware($callback);
    }

    /**
     * 创建代理中间件
     * 
     * @param array|string $proxy 代理配置
     */
    public static function proxy($proxy): callable
    {
        return new ProxyMiddleware($proxy);
    }

    /**
     * 创建认证中间件
     * 
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $type 认证类型（basic, digest, bearer）
     */
    public static function auth(string $username, string $password, string $type = 'basic'): callable
    {
        return new AuthMiddleware($username, $password, $type);
    }

    /**
     * 创建 Expect 100-continue 中间件
     */
    public static function expect(): callable
    {
        return new ExpectMiddleware();
    }

    /**
     * 创建内容解码中间件
     * 
     * @param bool $enabled 是否启用（默认 true）
     */
    public static function decodeContent(bool $enabled = true): callable
    {
        return new DecodeContentMiddleware($enabled);
    }

    /**
     * 创建准备请求体中间件
     */
    public static function prepareBody(): callable
    {
        return new PrepareBodyMiddleware();
    }
}

