<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Exception;

/**
 * 错误响应异常基类
 * 当接收到错误响应（4xx 或 5xx）时抛出
 */
class BadResponseException extends RequestException
{
}

