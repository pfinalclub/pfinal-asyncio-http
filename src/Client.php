<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp;

use PFinal\AsyncioHttp\Handler\AsyncioHandler;
use PFinal\AsyncioHttp\Handler\HandlerStack;
use PFinal\AsyncioHttp\Promise\PromiseInterface;
use PFinal\AsyncioHttp\Promise\TaskPromise;
use PFinal\AsyncioHttp\Psr7\HttpFactory;
use PFinal\AsyncioHttp\Psr7\Request;
use PFinal\AsyncioHttp\Psr7\Stream;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

use function PfinalClub\Asyncio\create_task;

/**
 * Guzzle 兼容的 HTTP 客户端
 */
class Client implements ClientInterface
{
    private array $config;
    private HandlerStack $handlerStack;
    private HttpFactory $httpFactory;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->httpFactory = new HttpFactory();

        // 如果配置中提供了 handler，直接使用
        if (isset($config['handler']) && $config['handler'] instanceof HandlerStack) {
            $this->handlerStack = $config['handler'];
        } else {
            // 否则创建默认处理器栈
            $handler = new AsyncioHandler($config);
            $this->handlerStack = HandlerStack::create($handler);
        }
    }

    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        $request = $this->buildRequest($method, $uri, $options);

        return $this->handlerStack->__invoke($request, array_merge($this->config, $options));
    }

    public function requestAsync(string $method, $uri = '', array $options = []): PromiseInterface
    {
        $task = create_task(function () use ($method, $uri, $options) {
            return $this->request($method, $uri, $options);
        });

        return new TaskPromise($task);
    }

    public function get($uri, array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    public function getAsync($uri, array $options = []): PromiseInterface
    {
        return $this->requestAsync('GET', $uri, $options);
    }

    public function head($uri, array $options = []): ResponseInterface
    {
        return $this->request('HEAD', $uri, $options);
    }

    public function headAsync($uri, array $options = []): PromiseInterface
    {
        return $this->requestAsync('HEAD', $uri, $options);
    }

    public function put($uri, array $options = []): ResponseInterface
    {
        return $this->request('PUT', $uri, $options);
    }

    public function putAsync($uri, array $options = []): PromiseInterface
    {
        return $this->requestAsync('PUT', $uri, $options);
    }

    public function post($uri, array $options = []): ResponseInterface
    {
        return $this->request('POST', $uri, $options);
    }

    public function postAsync($uri, array $options = []): PromiseInterface
    {
        return $this->requestAsync('POST', $uri, $options);
    }

    public function patch($uri, array $options = []): ResponseInterface
    {
        return $this->request('PATCH', $uri, $options);
    }

    public function patchAsync($uri, array $options = []): PromiseInterface
    {
        return $this->requestAsync('PATCH', $uri, $options);
    }

    public function delete($uri, array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $uri, $options);
    }

    public function deleteAsync($uri, array $options = []): PromiseInterface
    {
        return $this->requestAsync('DELETE', $uri, $options);
    }

    public function options($uri, array $options = []): ResponseInterface
    {
        return $this->request('OPTIONS', $uri, $options);
    }

    public function optionsAsync($uri, array $options = []): PromiseInterface
    {
        return $this->requestAsync('OPTIONS', $uri, $options);
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->handlerStack->__invoke($request, array_merge($this->config, $options));
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        $task = create_task(function () use ($request, $options) {
            return $this->send($request, $options);
        });

        return new TaskPromise($task);
    }

    public function getConfig(?string $option = null)
    {
        if ($option === null) {
            return $this->config;
        }

        return $this->config[$option] ?? null;
    }

    public function getHandlerStack(): HandlerStack
    {
        return $this->handlerStack;
    }

    private function buildRequest(string $method, $uri, array $options): RequestInterface
    {
        // 处理 base_uri
        if (isset($this->config[RequestOptions::BASE_URI])) {
            $baseUri = $this->httpFactory->createUri($this->config[RequestOptions::BASE_URI]);
            $uri = $this->resolveUri($baseUri, $uri);
        }

        // 创建请求
        $request = new Request($method, $uri);

        // 处理 headers
        $headers = array_merge(
            $this->config[RequestOptions::HEADERS] ?? [],
            $options[RequestOptions::HEADERS] ?? []
        );

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // 处理 query
        if (isset($options[RequestOptions::QUERY])) {
            $request = $this->applyQuery($request, $options[RequestOptions::QUERY]);
        }

        // 处理 body
        if (isset($options[RequestOptions::BODY])) {
            $body = $options[RequestOptions::BODY];
            if (is_string($body)) {
                $stream = new Stream('php://temp', 'rw+');
                $stream->write($body);
                $stream->rewind();
                $request = $request->withBody($stream);
            } elseif ($body instanceof \Psr\Http\Message\StreamInterface) {
                $request = $request->withBody($body);
            }
        }

        // 处理 json
        if (isset($options[RequestOptions::JSON])) {
            $json = json_encode($options[RequestOptions::JSON]);
            $stream = new Stream('php://temp', 'rw+');
            $stream->write($json);
            $stream->rewind();
            $request = $request
                ->withBody($stream)
                ->withHeader('Content-Type', 'application/json');
        }

        // 处理 form_params
        if (isset($options[RequestOptions::FORM_PARAMS])) {
            $formData = http_build_query($options[RequestOptions::FORM_PARAMS]);
            $stream = new Stream('php://temp', 'rw+');
            $stream->write($formData);
            $stream->rewind();
            $request = $request
                ->withBody($stream)
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        // 确保有 User-Agent
        if (!$request->hasHeader('User-Agent')) {
            $request = $request->withHeader('User-Agent', \PFinal\AsyncioHttp\default_user_agent());
        }

        return $request;
    }

    private function resolveUri(UriInterface $baseUri, $uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }

        $uri = $this->httpFactory->createUri($uri);

        // 如果 uri 已经是绝对 URI，直接返回
        if ($uri->getScheme() !== '') {
            return $uri;
        }

        // 合并 base_uri 和相对路径
        return $uri
            ->withScheme($baseUri->getScheme())
            ->withHost($baseUri->getHost())
            ->withPort($baseUri->getPort())
            ->withPath($this->resolvePath($baseUri->getPath(), $uri->getPath()));
    }

    private function resolvePath(string $basePath, string $path): string
    {
        if ($path === '') {
            return $basePath;
        }

        if ($path[0] === '/') {
            return $path;
        }

        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }

    private function applyQuery(RequestInterface $request, $query): RequestInterface
    {
        $uri = $request->getUri();
        $existingQuery = $uri->getQuery();

        if (is_array($query)) {
            $query = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        if ($existingQuery !== '') {
            $query = $existingQuery . '&' . $query;
        }

        return $request->withUri($uri->withQuery($query));
    }
}

