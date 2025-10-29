<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Exception;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * 流 Seek 异常
 */
class SeekException extends RuntimeException implements GuzzleException
{
    private StreamInterface $stream;

    public function __construct(StreamInterface $stream, int $pos = 0, string $msg = '')
    {
        $this->stream = $stream;
        $msg = $msg ?: 'Could not seek the stream to position ' . $pos;
        parent::__construct($msg);
    }

    public function getStream(): StreamInterface
    {
        return $this->stream;
    }
}

