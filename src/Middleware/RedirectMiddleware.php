<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use PFinal\AsyncioHttp\Exception\TooManyRedirectsException;
use PFinal\AsyncioHttp\Psr7\Uri;

/**
 * 重定向中间件
 * 处理 HTTP 重定向（301, 302, 303, 307, 308）
 */
class RedirectMiddleware
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max' => 5,
            'strict' => false,
            'referer' => true,
            'protocols' => ['http', 'https'],
            'track_redirects' => false,
        ], $config);
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $options['__redirect_count'] = $options['__redirect_count'] ?? 0;
            $options['__redirect_history'] = $options['__redirect_history'] ?? [];

            $maxRedirects = $options['allow_redirects']['max'] ?? $this->config['max'];
            $strict = $options['allow_redirects']['strict'] ?? $this->config['strict'];
            $referer = $options['allow_redirects']['referer'] ?? $this->config['referer'];
            $protocols = $options['allow_redirects']['protocols'] ?? $this->config['protocols'];
            $trackRedirects = $options['allow_redirects']['track_redirects'] ?? $this->config['track_redirects'];

            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) use ($request, $options, $handler, $maxRedirects, $strict, $referer, $protocols, $trackRedirects) {
                    $statusCode = $response->getStatusCode();

                    // 不是重定向响应，直接返回
                    if (!$this->isRedirect($statusCode)) {
                        return $response;
                    }

                    // 检查是否超过最大重定向次数
                    if ($options['__redirect_count'] >= $maxRedirects) {
                        throw new TooManyRedirectsException(
                            sprintf('Too many redirects (max: %d)', $maxRedirects),
                            $request,
                            $response
                        );
                    }

                    // 获取 Location 头
                    $location = $response->getHeaderLine('Location');
                    if ($location === '') {
                        return $response;
                    }

                    // 解析新的 URI
                    $uri = $this->resolveRedirectUri($request->getUri(), $location);

                    // 检查协议
                    if (!in_array($uri->getScheme(), $protocols, true)) {
                        return $response;
                    }

                    // 创建新请求
                    $newRequest = $this->modifyRequest($request, $response, $uri, $strict);

                    // 检查是否跨域，如果是则移除敏感头
                    $oldHost = $request->getUri()->getHost();
                    $newHost = $uri->getHost();
                    if ($oldHost !== $newHost) {
                        // 跨域重定向，移除敏感头
                        $newRequest = $newRequest
                            ->withoutHeader('Authorization')
                            ->withoutHeader('Cookie');
                    }

                    // 添加 Referer 头
                    if ($referer) {
                        $refererUri = (string)$request->getUri()->withUserInfo('');
                        $newRequest = $newRequest->withHeader('Referer', $refererUri);
                    }

                    // 更新选项
                    $newOptions = $options;
                    $newOptions['__redirect_count']++;

                    if ($trackRedirects) {
                        $newOptions['__redirect_history'][] = [
                            'status' => $statusCode,
                            'uri' => (string)$uri,
                            'headers' => $response->getHeaders(),
                        ];
                    }

                    // 递归调用处理器（返回响应而不是 wait）
                    return $handler($newRequest, $newOptions);
                }
            );
        };
    }

    /**
     * 判断是否为重定向状态码
     */
    private function isRedirect(int $statusCode): bool
    {
        return in_array($statusCode, [301, 302, 303, 307, 308], true);
    }

    /**
     * 解析重定向 URI
     */
    private function resolveRedirectUri($baseUri, string $location)
    {
        // 绝对 URI
        if (preg_match('/^https?:\/\//i', $location)) {
            return new Uri($location);
        }

        // 相对 URI
        $uri = new Uri($location);

        // 如果没有 scheme，使用原始请求的 scheme 和 host
        if ($uri->getScheme() === '') {
            $uri = $uri->withScheme($baseUri->getScheme())
                ->withHost($baseUri->getHost())
                ->withPort($baseUri->getPort());

            // 如果路径是相对的（不以 / 开头），需要合并路径
            if ($location !== '' && $location[0] !== '/') {
                $basePath = $baseUri->getPath();
                $basePath = substr($basePath, 0, strrpos($basePath, '/') + 1);
                $uri = $uri->withPath($basePath . $uri->getPath());
            }
        }

        return $uri;
    }

    /**
     * 根据重定向状态码修改请求
     */
    private function modifyRequest(RequestInterface $request, ResponseInterface $response, $uri, bool $strict): RequestInterface
    {
        $statusCode = $response->getStatusCode();
        $newRequest = $request->withUri($uri);

        // 303 总是使用 GET
        if ($statusCode === 303) {
            $newRequest = $newRequest->withMethod('GET');
            $newRequest = $newRequest->withBody(
                (new \PFinal\AsyncioHttp\Psr7\Stream(fopen('php://temp', 'r+')))
            );
        }

        // 301, 302 在非严格模式下改为 GET（除了 GET 和 HEAD）
        if (in_array($statusCode, [301, 302], true) && !$strict) {
            $method = $request->getMethod();
            if (!in_array($method, ['GET', 'HEAD'], true)) {
                $newRequest = $newRequest->withMethod('GET');
                $newRequest = $newRequest->withBody(
                    (new \PFinal\AsyncioHttp\Psr7\Stream(fopen('php://temp', 'r+')))
                );
            }
        }

        // 307, 308 保持原始方法和请求体

        return $newRequest;
    }
}

