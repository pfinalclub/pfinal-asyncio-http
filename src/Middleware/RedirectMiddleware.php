<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use PFinal\AsyncioHttp\Exception\TooManyRedirectsException;
use PFinal\AsyncioHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * 重定向中间件
 * 自动处理 HTTP 重定向（301, 302, 303, 307, 308）
 */
class RedirectMiddleware
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max' => 5,                          // 最大重定向次数
            'strict' => false,                   // 严格模式（POST 不改为 GET）
            'referer' => true,                   // 是否添加 Referer 头
            'protocols' => ['http', 'https'],    // 允许的协议
            'track_redirects' => false,          // 是否追踪重定向历史
        ], $config);
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $maxRedirects = $options[RequestOptions::ALLOW_REDIRECTS]['max'] 
                ?? $this->config['max'];
            $strict = $options[RequestOptions::ALLOW_REDIRECTS]['strict'] 
                ?? $this->config['strict'];
            $referer = $options[RequestOptions::ALLOW_REDIRECTS]['referer'] 
                ?? $this->config['referer'];
            $protocols = $options[RequestOptions::ALLOW_REDIRECTS]['protocols'] 
                ?? $this->config['protocols'];
            $trackRedirects = $options[RequestOptions::ALLOW_REDIRECTS]['track_redirects'] 
                ?? $this->config['track_redirects'];

            $redirectCount = 0;
            $redirectHistory = [];
            $currentRequest = $request;

            while ($redirectCount < $maxRedirects) {
                // 执行请求
                $response = $handler($currentRequest, $options);

                // 检查是否是重定向
                    $statusCode = $response->getStatusCode();
                    if (!$this->isRedirect($statusCode)) {
                    // 如果追踪重定向，添加历史记录
                    if ($trackRedirects && !empty($redirectHistory)) {
                        $response = $response->withHeader('X-Guzzle-Redirect-History', $redirectHistory);
                        $response = $response->withHeader('X-Guzzle-Redirect-Status-History', 
                            array_map(fn($h) => $h['status'], $redirectHistory));
                    }
                    return $response;
                    }

                    // 获取 Location 头
                    $location = $response->getHeaderLine('Location');
                if (!$location) {
                        return $response;
                    }

                // 解析目标 URI
                $targetUri = $this->resolveUri($currentRequest->getUri(), $location);

                // 检查协议是否允许
                if (!in_array($targetUri->getScheme(), $protocols)) {
                    throw new TooManyRedirectsException(
                        sprintf('Redirect to %s protocol is not allowed', $targetUri->getScheme())
                    );
                }

                // 记录重定向历史
                    if ($trackRedirects) {
                    $redirectHistory[] = [
                        'url' => (string)$currentRequest->getUri(),
                            'status' => $statusCode,
                    ];
                }

                // 构建下一个请求
                $currentRequest = $this->modifyRequest(
                    $currentRequest,
                    $targetUri,
                    $statusCode,
                    $strict,
                    $referer
                );

                $redirectCount++;
            }

            // 超过最大重定向次数
            throw new TooManyRedirectsException(
                sprintf('Maximum redirects (%d) exceeded', $maxRedirects)
            );
        };
    }

    /**
     * 检查状态码是否为重定向
     */
    private function isRedirect(int $statusCode): bool
    {
        return in_array($statusCode, [301, 302, 303, 307, 308]);
    }

    /**
     * 解析目标 URI（处理相对 URL）
     */
    private function resolveUri(UriInterface $baseUri, string $location): UriInterface
    {
        // 解析 Location
        $locationUri = \PFinal\AsyncioHttp\Psr7\uri_for($location);

        // 如果是绝对 URI，直接返回
        if ($locationUri->getScheme() !== '') {
            return $locationUri;
        }

        // 处理相对 URI
        return $this->resolveRelativeUri($baseUri, $locationUri);
    }

    /**
     * 解析相对 URI
     */
    private function resolveRelativeUri(UriInterface $base, UriInterface $rel): UriInterface
    {
        if ($rel->getScheme() !== '') {
            return $rel;
        }

        $scheme = $base->getScheme();
        $host = $base->getHost();
        $port = $base->getPort();

        // 如果 rel 有 host，使用 rel 的 host
        if ($rel->getHost() !== '') {
            return $rel
                ->withScheme($scheme)
                ->withPort($port);
        }

        // 解析路径
        $path = $rel->getPath();
        
        // 绝对路径
        if (!empty($path) && $path[0] === '/') {
            return $rel
                ->withScheme($scheme)
                ->withHost($host)
                ->withPort($port);
        }

        // 相对路径
        $basePath = $base->getPath();
        $targetPath = $this->mergePaths($basePath, $path);

        return $rel
            ->withScheme($scheme)
            ->withHost($host)
            ->withPort($port)
            ->withPath($targetPath)
            ->withQuery($rel->getQuery() ?: $base->getQuery());
    }

    /**
     * 合并路径
     */
    private function mergePaths(string $basePath, string $relPath): string
    {
        if ($relPath === '') {
            return $basePath;
        }

        $baseDir = preg_replace('/\/[^\/]*$/', '', $basePath);
        
        if ($baseDir === '') {
            return '/' . ltrim($relPath, '/');
        }

        return $baseDir . '/' . ltrim($relPath, '/');
    }

    /**
     * 修改请求以进行重定向
     */
    private function modifyRequest(
        RequestInterface $request,
        UriInterface $targetUri,
        int $statusCode,
        bool $strict,
        bool $referer
    ): RequestInterface {
        $newRequest = $request->withUri($targetUri);

        // 添加 Referer 头
        if ($referer) {
            $newRequest = $newRequest->withHeader('Referer', (string)$request->getUri());
        }

        // 根据状态码修改请求方法和体
        if ($statusCode === 303 || (!$strict && $statusCode === 302)) {
            // 303 和非严格模式的 302 应该变为 GET
            $newRequest = $newRequest
                ->withMethod('GET')
                ->withBody(\PFinal\AsyncioHttp\Psr7\stream_for(''));
        } elseif ($statusCode === 301 || $statusCode === 302) {
            // 严格模式下，保持原方法
            if ($request->getMethod() !== 'GET' && $request->getMethod() !== 'HEAD') {
                $newRequest = $newRequest->withBody(\PFinal\AsyncioHttp\Psr7\stream_for(''));
            }
        }

        return $newRequest;
    }
}
