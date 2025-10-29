<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Exception;

use Psr\Http\Client\ClientExceptionInterface;

/**
 * 客户端错误异常（4xx 状态码）
 */
class ClientException extends BadResponseException implements ClientExceptionInterface
{
}

