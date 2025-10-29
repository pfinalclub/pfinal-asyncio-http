<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Middleware;

use Psr\Http\Message\RequestInterface;

/**
 * Expect 100-continue 中间件
 * 处理 Expect: 100-continue 头
 */
class ExpectMiddleware
{
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            // 如果请求体较大（>1MB），添加 Expect: 100-continue 头
            $size = $request->getBody()->getSize();

            if ($size !== null && $size > 1048576 && !$request->hasHeader('Expect')) {
                $request = $request->withHeader('Expect', '100-continue');
            }

            // 如果设置了 expect 选项
            if (isset($options['expect'])) {
                if ($options['expect'] === true) {
                    $request = $request->withHeader('Expect', '100-continue');
                } elseif ($options['expect'] === false) {
                    $request = $request->withoutHeader('Expect');
                }
            }

            return $handler($request, $options);
        };
    }
}

