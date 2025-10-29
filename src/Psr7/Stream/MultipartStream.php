<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Psr7\Stream;

use InvalidArgumentException;
use PFinal\AsyncioHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * 多部分流（用于文件上传）
 */
class MultipartStream implements StreamInterface
{
    private StreamInterface $stream;
    private string $boundary;

    public function __construct(array $elements = [], ?string $boundary = null)
    {
        $this->boundary = $boundary ?: uniqid('', true);
        $this->stream = $this->createStream($elements);
    }

    public function getBoundary(): string
    {
        return $this->boundary;
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

    public function close(): void
    {
        $this->stream->close();
    }

    public function detach()
    {
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
        return $this->stream->eof();
    }

    public function isSeekable(): bool
    {
        return $this->stream->isSeekable();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->stream->seek($offset, $whence);
    }

    public function rewind(): void
    {
        $this->stream->rewind();
    }

    public function isWritable(): bool
    {
        return $this->stream->isWritable();
    }

    public function write(string $string): int
    {
        return $this->stream->write($string);
    }

    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }

    public function read(int $length): string
    {
        return $this->stream->read($length);
    }

    public function getContents(): string
    {
        return $this->stream->getContents();
    }

    public function getMetadata(?string $key = null)
    {
        return $this->stream->getMetadata($key);
    }

    private function createStream(array $elements): StreamInterface
    {
        $stream = new Stream('php://temp', 'rw+');

        foreach ($elements as $element) {
            $this->addElement($stream, $element);
        }

        $stream->write("--{$this->boundary}--\r\n");
        $stream->rewind();

        return $stream;
    }

    private function addElement(StreamInterface $stream, array $element): void
    {
        if (!isset($element['name'])) {
            throw new InvalidArgumentException('Multipart element must have a "name"');
        }

        $stream->write("--{$this->boundary}\r\n");

        if (isset($element['filename'])) {
            $stream->write(sprintf(
                'Content-Disposition: form-data; name="%s"; filename="%s"' . "\r\n",
                $element['name'],
                basename($element['filename'])
            ));
        } else {
            $stream->write(sprintf(
                'Content-Disposition: form-data; name="%s"' . "\r\n",
                $element['name']
            ));
        }

        if (isset($element['headers'])) {
            foreach ($element['headers'] as $key => $value) {
                $stream->write("{$key}: {$value}\r\n");
            }
        }

        $stream->write("\r\n");

        if (isset($element['contents'])) {
            $contents = $element['contents'];
            if ($contents instanceof StreamInterface) {
                $contents->rewind();
                while (!$contents->eof()) {
                    $stream->write($contents->read(8192));
                }
            } else {
                $stream->write((string) $contents);
            }
        }

        $stream->write("\r\n");
    }
}

