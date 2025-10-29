<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Handler;

use Psr\Http\Message\RequestInterface;

/**
 * 处理器栈
 * 管理中间件链
 */
class HandlerStack
{
    private HandlerInterface $handler;
    private array $middlewares = [];

    public function __construct(?HandlerInterface $handler = null)
    {
        $this->handler = $handler ?? new AsyncioHandler();
    }

    /**
     * 添加中间件（添加到栈顶，最后执行）
     */
    public function push(callable $middleware, string $name = ''): self
    {
        $this->middlewares[] = [
            'middleware' => $middleware,
            'name' => $name,
        ];

        return $this;
    }

    /**
     * 添加中间件到栈底（最先执行）
     */
    public function unshift(callable $middleware, string $name = ''): self
    {
        array_unshift($this->middlewares, [
            'middleware' => $middleware,
            'name' => $name,
        ]);

        return $this;
    }

    /**
     * 在指定中间件之前添加
     */
    public function before(string $findName, callable $middleware, string $name = ''): self
    {
        $index = $this->findMiddlewareIndex($findName);
        if ($index !== null) {
            array_splice($this->middlewares, $index, 0, [[
                'middleware' => $middleware,
                'name' => $name,
            ]]);
        }

        return $this;
    }

    /**
     * 在指定中间件之后添加
     */
    public function after(string $findName, callable $middleware, string $name = ''): self
    {
        $index = $this->findMiddlewareIndex($findName);
        if ($index !== null) {
            array_splice($this->middlewares, $index + 1, 0, [[
                'middleware' => $middleware,
                'name' => $name,
            ]]);
        }

        return $this;
    }

    /**
     * 移除中间件
     */
    public function remove(string $name): self
    {
        $this->middlewares = array_values(array_filter(
            $this->middlewares,
            fn($m) => $m['name'] !== $name
        ));

        return $this;
    }

    /**
     * 查找中间件索引
     */
    private function findMiddlewareIndex(string $name): ?int
    {
        foreach ($this->middlewares as $index => $middleware) {
            if ($middleware['name'] === $name) {
                return $index;
            }
        }

        return null;
    }

    /**
     * 创建默认栈
     */
    public static function create(?HandlerInterface $handler = null): self
    {
        $stack = new self($handler);

        // 添加默认中间件
        $stack->push(new \PFinal\AsyncioHttp\Middleware\PrepareBodyMiddleware(), 'prepare_body');
        $stack->push(\PFinal\AsyncioHttp\Middleware\Middleware::httpErrors(), 'http_errors');

        return $stack;
    }

    /**
     * 获取底层处理器
     */
    public function getHandler(): HandlerInterface
    {
        return $this->handler;
    }

    /**
     * 处理请求（返回响应）
     */
    public function __invoke(RequestInterface $request, array $options = [])
    {
        // 构建处理器链（从底层处理器开始）
        $handler = function ($request, $options) {
            $response = $this->handler->handle($request, $options);
            // 如果是同步响应，直接返回
            return $response;
        };

        // 反向应用中间件（从栈顶到栈底）
        foreach (array_reverse($this->middlewares) as $middleware) {
            $handler = $middleware['middleware']($handler);
        }

        // 调用最终的处理器
        return $handler($request, $options);
    }

    /**
     * 调试：打印中间件栈
     */
    public function debug(): string
    {
        $lines = ["Handler Stack:"];
        $lines[] = "  [底层] " . get_class($this->handler);

        foreach ($this->middlewares as $index => $middleware) {
            $name = $middleware['name'] ?: sprintf('unnamed_%d', $index);
            $lines[] = sprintf("  [%d] %s", $index + 1, $name);
        }

        return implode("\n", $lines);
    }
}

