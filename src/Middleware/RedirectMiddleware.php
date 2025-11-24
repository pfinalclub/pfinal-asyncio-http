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

        $path = $rel->getPath();
        
        // 如果路径以 / 开头，使用绝对路径
        if ($path !== '' && $path[0] === '/') {
            return $rel
                ->withScheme($scheme)
                ->withHost($host)
                ->withPort($port);
        }

        // 处理相对路径
        $basePath = $base->getPath();
        if ($path === '') {
            $path = $basePath;
        } else {
            // 移除最后一个路径段
            $basePath = substr($basePath, 0, (int)strrpos($basePath, '/') + 1);
            $path = $basePath . $path;
        }

        // 规范化路径
        $path = $this->normalizePath($path);

        return $rel
            ->withScheme($scheme)
            ->withHost($host)
            ->withPort($port)
            ->withPath($path)
            ->withQuery($rel->getQuery() ?: $base->getQuery());
    }

    /**
     * 规范化路径（移除 ./ 和 ../）
     */
    private function normalizePath(string $path): string
    {
        $parts = explode('/', $path);
        $result = [];

        foreach ($parts as $part) {
            if ($part === '..') {
                array_pop($result);
            } elseif ($part !== '.' && $part !== '') {
                $result[] = $part;
            }
        }

        $normalized = implode('/', $result);
        
        // 保持开头的斜杠
        if ($path[0] === '/') {
            $normalized = '/' . $normalized;
        }

        return $normalized;
    }

    /**
     * 根据重定向状态码修改请求
     */
    private function modifyRequest(
        RequestInterface $request,
        UriInterface $targetUri,
        int $statusCode,
        bool $strict,
        bool $referer
    ): RequestInterface {
        // 更新 URI
        $request = $request->withUri($targetUri);

        // 添加 Referer 头
        if ($referer && !$request->hasHeader('Referer')) {
            $refererUri = $request->getUri()
                ->withUserInfo('')
                ->withFragment('');
            $request = $request->withHeader('Referer', (string)$refererUri);
        }

        // 根据状态码调整请求方法
        if ($statusCode === 303) {
            // 303 总是改为 GET
            $request = $request->withMethod('GET');
            $request = $request->withBody(\PFinal\AsyncioHttp\Psr7\stream_for(''));
        } elseif (!$strict && in_array($statusCode, [301, 302])) {
            // 301/302 在非严格模式下，POST 改为 GET
            if ($request->getMethod() === 'POST') {
                $request = $request->withMethod('GET');
                $request = $request->withBody(\PFinal\AsyncioHttp\Psr7\stream_for(''));
            }
        }
        // 307/308 保持原方法和请求体

        // 移除 Content-Length 头（如果请求体为空）
        if ($request->getBody()->getSize() === 0) {
            $request = $request->withoutHeader('Content-Length');
            $request = $request->withoutHeader('Transfer-Encoding');
        }

        return $request;
    }
}

