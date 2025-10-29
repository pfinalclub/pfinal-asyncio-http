<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Exception;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * 连接异常
 * 当无法建立连接时抛出
 */
class ConnectException extends TransferException implements NetworkExceptionInterface
{
    private RequestInterface $request;
    private array $handlerContext;

    public function __construct(
        string $message,
        RequestInterface $request,
        ?\Throwable $previous = null,
        array $handlerContext = []
    ) {
        parent::__construct($message, 0, $previous);
        $this->request = $request;
        $this->handlerContext = $handlerContext;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getHandlerContext(): array
    {
        return $this->handlerContext;
    }
}

