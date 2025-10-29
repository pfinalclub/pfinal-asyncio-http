<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Psr7\Stream;

use PFinal\AsyncioHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * 延迟打开的流
 * 只在第一次需要时才打开文件
 */
class LazyOpenStream implements StreamInterface
{
    private ?string $filename;
    private string $mode;
    private ?StreamInterface $stream = null;

    public function __construct(string $filename, string $mode)
    {
        $this->filename = $filename;
        $this->mode = $mode;
    }

    public function __toString(): string
    {
        try {
            return (string) $this->getStream();
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function close(): void
    {
        if ($this->stream) {
            $this->stream->close();
        }

        $this->stream = null;
    }

    public function detach()
    {
        $this->filename = null;

        return $this->stream ? $this->stream->detach() : null;
    }

    public function getSize(): ?int
    {
        return $this->getStream()->getSize();
    }

    public function tell(): int
    {
        return $this->getStream()->tell();
    }

    public function eof(): bool
    {
        return $this->stream ? $this->stream->eof() : false;
    }

    public function isSeekable(): bool
    {
        return $this->stream ? $this->stream->isSeekable() : true;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->getStream()->seek($offset, $whence);
    }

    public function rewind(): void
    {
        $this->getStream()->rewind();
    }

    public function isWritable(): bool
    {
        return $this->stream ? $this->stream->isWritable() : true;
    }

    public function write(string $string): int
    {
        return $this->getStream()->write($string);
    }

    public function isReadable(): bool
    {
        return $this->stream ? $this->stream->isReadable() : true;
    }

    public function read(int $length): string
    {
        return $this->getStream()->read($length);
    }

    public function getContents(): string
    {
        return $this->getStream()->getContents();
    }

    public function getMetadata(?string $key = null)
    {
        return $this->stream ? $this->stream->getMetadata($key) : null;
    }

    private function getStream(): StreamInterface
    {
        if (!$this->stream) {
            if (!$this->filename) {
                throw new RuntimeException('Stream is detached');
            }

            $this->stream = new Stream($this->filename, $this->mode);
        }

        return $this->stream;
    }
}

