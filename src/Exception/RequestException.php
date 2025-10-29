<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Exception;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 请求异常
 * 当 HTTP 错误发生时抛出
 */
class RequestException extends TransferException implements RequestExceptionInterface
{
    private RequestInterface $request;
    private ?ResponseInterface $response;
    private array $handlerContext;

    public function __construct(
        string $message,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?\Throwable $previous = null,
        array $handlerContext = []
    ) {
        parent::__construct($message, 0, $previous);

        $this->request = $request;
        $this->response = $response;
        $this->handlerContext = $handlerContext;
        $this->code = $response ? $response->getStatusCode() : 0;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    public function hasResponse(): bool
    {
        return $this->response !== null;
    }

    public function getHandlerContext(): array
    {
        return $this->handlerContext;
    }

    /**
     * 创建请求异常
     */
    public static function create(
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?\Throwable $previous = null,
        array $handlerContext = []
    ): self {
        if (!$response) {
            return new self(
                'Error completing request',
                $request,
                null,
                $previous,
                $handlerContext
            );
        }

        $level = (int) floor($response->getStatusCode() / 100);
        $message = sprintf(
            'HTTP %d %s returned for "%s %s"',
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $request->getMethod(),
            $request->getUri()
        );

        if ($level === 4) {
            return new ClientException($message, $request, $response, $previous, $handlerContext);
        }

        if ($level === 5) {
            return new ServerException($message, $request, $response, $previous, $handlerContext);
        }

        return new BadResponseException($message, $request, $response, $previous, $handlerContext);
    }
}

