<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Exception;

use RuntimeException;

/**
 * 传输异常基类
 */
class TransferException extends RuntimeException implements GuzzleException
{
}

