<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Psr7\Stream;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * 限制流 - 限制从底层流读取的字节数
 */
class LimitStream implements StreamInterface
{
    private StreamInterface $stream;
    private int $limit;
    private int $offset;
    private int $pos = 0;

    public function __construct(StreamInterface $stream, int $limit = -1, int $offset = 0)
    {
        $this->stream = $stream;
        $this->limit = $limit;
        $this->offset = $offset;

        if ($offset !== 0) {
            $this->stream->seek($offset);
        }
    }

    public function __toString(): string
    {
        try {
            $this->seek(0);

            return $this->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function close(): void
    {
        $this->stream->close();
    }

    public function detach()
    {
        return $this->stream->detach();
    }

    public function eof(): bool
    {
        if ($this->stream->eof()) {
            return true;
        }

        if ($this->limit === -1) {
            return false;
        }

        return $this->pos >= $this->limit;
    }

    public function getSize(): ?int
    {
        if ($this->limit === -1) {
            return $this->stream->getSize();
        }

        $size = $this->stream->getSize();

        if ($size === null) {
            return null;
        }

        $size = min($this->limit, $size - $this->offset);

        return $size >= 0 ? $size : 0;
    }

    public function tell(): int
    {
        return $this->pos;
    }

    public function isSeekable(): bool
    {
        return $this->stream->isSeekable();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($whence !== SEEK_SET || $offset < 0) {
            throw new RuntimeException('Cannot seek to offset ' . $offset);
        }

        $offset += $this->offset;

        if ($this->limit !== -1) {
            if ($offset > $this->offset + $this->limit) {
                $offset = $this->offset + $this->limit;
            }
        }

        $this->pos = $offset - $this->offset;
        $this->stream->seek($offset);
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
        throw new RuntimeException('Cannot write to a LimitStream');
    }

    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }

    public function read(int $length): string
    {
        if ($this->limit !== -1) {
            $remaining = $this->limit - $this->pos;
            if ($remaining <= 0) {
                return '';
            }

            $length = min($length, $remaining);
        }

        $result = $this->stream->read($length);
        $this->pos += strlen($result);

        return $result;
    }

    public function getContents(): string
    {
        $contents = '';
        while (!$this->eof()) {
            $buf = $this->read(1048576);
            if ($buf === '') {
                break;
            }
            $contents .= $buf;
        }

        return $contents;
    }

    public function getMetadata(?string $key = null)
    {
        return $this->stream->getMetadata($key);
    }
}

