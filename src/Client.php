<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp;

use PFinal\AsyncioHttp\Handler\AsyncioHandler;
use PFinal\AsyncioHttp\Handler\HandlerStack;
use PFinal\AsyncioHttp\Psr7\HttpFactory;
use PFinal\AsyncioHttp\Psr7\Request;
use PFinal\AsyncioHttp\Psr7\Stream;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7/18 兼容的异步 HTTP 客户端
 * 
 * 基于 pfinalclub/asyncio，必须在 run() 函数内使用
 * 
 * 注意：所有方法在 Fiber 中调用时是非阻塞的，
 * 虽然看起来是"同步"调用，但实际是异步执行
 * 
 * @example
 * use function PfinalClub\Asyncio\{run, create_task, gather};
 * 
 * run(function() {
 *     $client = new Client();
 *     
 *     // 单个请求（在 Fiber 中自动异步）
 *     $response = $client->get('https://api.example.com/users');
 *     
 *     // 并发请求
 *     $tasks = [
 *         create_task(fn() => $client->get('https://api.example.com/users/1')),
 *         create_task(fn() => $client->get('https://api.example.com/users/2')),
 *     ];
 *     $responses = gather(...$tasks);
 * });
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

    /**
     * 发送 HTTP 请求
     * 
     * 注意：此方法必须在 Fiber 上下文中调用（即在 run() 函数内）
     * 虽然看起来是同步调用，但在 Fiber 中是非阻塞的
     */
    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        $request = $this->buildRequest($method, $uri, $options);
        return $this->handlerStack->__invoke($request, array_merge($this->config, $options));
    }

    public function get($uri, array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    public function head($uri, array $options = []): ResponseInterface
    {
        return $this->request('HEAD', $uri, $options);
    }

    public function put($uri, array $options = []): ResponseInterface
    {
        return $this->request('PUT', $uri, $options);
    }

    public function post($uri, array $options = []): ResponseInterface
    {
        return $this->request('POST', $uri, $options);
    }

    public function patch($uri, array $options = []): ResponseInterface
    {
        return $this->request('PATCH', $uri, $options);
    }

    public function delete($uri, array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $uri, $options);
    }

    public function options($uri, array $options = []): ResponseInterface
    {
        return $this->request('OPTIONS', $uri, $options);
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->handlerStack->__invoke($request, array_merge($this->config, $options));
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
        // 验证 URI 类型
        if (!is_string($uri) && !($uri instanceof UriInterface)) {
            throw new \PFinal\AsyncioHttp\Exception\InvalidArgumentException(
                'URI must be a string or UriInterface, ' . gettype($uri) . ' given'
            );
        }
        
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

        // 检查是否有多个 body 选项（互斥）
        $bodyOptions = array_filter([
            isset($options[RequestOptions::BODY]),
            isset($options[RequestOptions::JSON]),
            isset($options[RequestOptions::FORM_PARAMS]),
        ]);
        
        if (count($bodyOptions) > 1) {
            throw new \PFinal\AsyncioHttp\Exception\InvalidArgumentException(
                'Cannot use multiple body options (body, json, form_params)'
            );
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
            if ($json === false) {
                throw new \PFinal\AsyncioHttp\Exception\InvalidArgumentException(
                    'JSON encode error: ' . json_last_error_msg()
                );
            }
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
