<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Psr7\Stream;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * 追加流 - 将多个流合并为一个
 */
class AppendStream implements StreamInterface
{
    private array $streams = [];
    private bool $seekable = true;
    private int $current = 0;
    private int $pos = 0;

    public function __construct(array $streams = [])
    {
        foreach ($streams as $stream) {
            $this->addStream($stream);
        }
    }

    public function __toString(): string
    {
        try {
            $this->rewind();

            return $this->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function addStream(StreamInterface $stream): void
    {
        if (!$stream->isReadable()) {
            throw new \InvalidArgumentException('Each stream must be readable');
        }

        $this->streams[] = $stream;
        $this->seekable = $this->seekable && $stream->isSeekable();
    }

    public function close(): void
    {
        $this->pos = $this->current = 0;
        $this->seekable = true;

        foreach ($this->streams as $stream) {
            $stream->close();
        }

        $this->streams = [];
    }

    public function detach()
    {
        $this->pos = $this->current = 0;
        $this->seekable = true;

        foreach ($this->streams as $stream) {
            $stream->detach();
        }

        $this->streams = [];

        return null;
    }

    public function getSize(): ?int
    {
        $size = 0;

        foreach ($this->streams as $stream) {
            $s = $stream->getSize();
            if ($s === null) {
                return null;
            }
            $size += $s;
        }

        return $size;
    }

    public function tell(): int
    {
        return $this->pos;
    }

    public function eof(): bool
    {
        return $this->current >= count($this->streams);
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->seekable) {
            throw new RuntimeException('Stream is not seekable');
        }

        if ($whence !== SEEK_SET) {
            throw new RuntimeException('Can only seek to absolute positions');
        }

        $this->pos = $offset;
        $this->current = 0;

        foreach ($this->streams as $i => $stream) {
            try {
                $stream->rewind();
            } catch (\Throwable $e) {
                throw new RuntimeException('Unable to seek stream ' . $i);
            }
        }

        while ($this->pos > 0 && $this->current < count($this->streams)) {
            $stream = $this->streams[$this->current];
            $size = $stream->getSize();

            if ($size === null || $this->pos < $size) {
                $stream->seek($this->pos);
                break;
            }

            $this->pos -= $size;
            $this->current++;
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
        throw new RuntimeException('Cannot write to an AppendStream');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        $buffer = '';
        $remaining = $length;

        while ($remaining > 0 && $this->current < count($this->streams)) {
            $stream = $this->streams[$this->current];
            $result = $stream->read($remaining);

            if ($result === '') {
                $this->current++;
                continue;
            }

            $buffer .= $result;
            $remaining = $length - strlen($buffer);
        }

        $this->pos += strlen($buffer);

        return $buffer;
    }

    public function getContents(): string
    {
        $buffer = '';

        while (!$this->eof()) {
            $buf = $this->read(1048576);
            if ($buf === '') {
                break;
            }
            $buffer .= $buf;
        }

        return $buffer;
    }

    public function getMetadata(?string $key = null)
    {
        return $key ? null : [];
    }
}

