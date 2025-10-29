<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Exception;

/**
 * 无效参数异常
 */
class InvalidArgumentException extends \InvalidArgumentException implements GuzzleException
{
}

