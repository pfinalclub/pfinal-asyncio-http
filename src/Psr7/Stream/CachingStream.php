<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Psr7\Stream;

use PFinal\AsyncioHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * 缓存流 - 缓存从不可seek流读取的数据
 */
class CachingStream implements StreamInterface
{
    private StreamInterface $remoteStream;
    private StreamInterface $stream;

    public function __construct(StreamInterface $stream, ?StreamInterface $target = null)
    {
        $this->remoteStream = $stream;
        $this->stream = $target ?: new Stream('php://temp', 'r+');
    }

    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->seek(0);
            }

            return $this->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function close(): void
    {
        $this->remoteStream->close();
        $this->stream->close();
    }

    public function detach()
    {
        $this->remoteStream->detach();

        return $this->stream->detach();
    }

    public function getSize(): ?int
    {
        return $this->stream->getSize();
    }

    public function tell(): int
    {
        return $this->stream->tell();
    }

    public function eof(): bool
    {
        return $this->stream->eof() && $this->remoteStream->eof();
    }

    public function isSeekable(): bool
    {
        return $this->stream->isSeekable();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($whence === SEEK_SET) {
            $byte = $offset;
        } elseif ($whence === SEEK_CUR) {
            $byte = $offset + $this->tell();
        } else {
            $size = $this->remoteStream->getSize();
            if ($size === null) {
                throw new RuntimeException('Cannot seek to end of remote stream');
            }
            $byte = $size + $offset;
        }

        $diff = $byte - $this->stream->getSize();

        if ($diff > 0) {
            while ($diff > 0 && !$this->remoteStream->eof()) {
                $this->read($diff);
                $diff = $byte - $this->stream->getSize();
            }
        } else {
            $this->stream->seek($byte);
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('Cannot write to a CachingStream');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        $data = $this->stream->read($length);
        $remaining = $length - strlen($data);

        if ($remaining && !$this->remoteStream->eof()) {
            $remoteData = $this->remoteStream->read($remaining);
            if ($remoteData !== '') {
                $this->stream->write($remoteData);
                $data .= $remoteData;
            }
        }

        return $data;
    }

    public function getContents(): string
    {
        return $this->stream->getContents();
    }

    public function getMetadata(?string $key = null)
    {
        return $this->stream->getMetadata($key);
    }
}

