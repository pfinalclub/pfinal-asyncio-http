<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Tests\Unit\Psr7;

use PHPUnit\Framework\TestCase;
use PFinal\AsyncioHttp\Psr7\Stream;

class StreamTest extends TestCase
{
    public function testConstructorWithResource(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);

        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isSeekable());
    }

    public function testToString(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Hello, World!');
        rewind($resource);

        $stream = new Stream($resource);

        $this->assertSame('Hello, World!', (string)$stream);
    }

    public function testGetContents(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Hello, World!');
        rewind($resource);

        $stream = new Stream($resource);

        $this->assertSame('Hello, World!', $stream->getContents());
    }

    public function testGetSize(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Hello, World!');

        $stream = new Stream($resource);

        $this->assertSame(13, $stream->getSize());
    }

    public function testTell(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Hello, World!');

        $stream = new Stream($resource);

        $this->assertSame(13, $stream->tell());
    }

    public function testEof(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Hello');
        rewind($resource);

        $stream = new Stream($resource);

        $this->assertFalse($stream->eof());

        $stream->getContents();

        $this->assertTrue($stream->eof());
    }

    public function testSeek(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Hello, World!');

        $stream = new Stream($resource);
        $stream->seek(7);

        $this->assertSame(7, $stream->tell());
        $this->assertSame('World!', $stream->getContents());
    }

    public function testRewind(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Hello, World!');

        $stream = new Stream($resource);
        $stream->rewind();

        $this->assertSame(0, $stream->tell());
        $this->assertSame('Hello, World!', $stream->getContents());
    }

    public function testRead(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Hello, World!');
        rewind($resource);

        $stream = new Stream($resource);

        $this->assertSame('Hello', $stream->read(5));
        $this->assertSame(', Wor', $stream->read(5));
    }

    public function testWrite(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);

        $written = $stream->write('Hello, World!');

        $this->assertSame(13, $written);

        $stream->rewind();
        $this->assertSame('Hello, World!', $stream->getContents());
    }

    public function testClose(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);

        $stream->close();

        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertFalse($stream->isSeekable());
    }

    public function testDetach(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);

        $detached = $stream->detach();

        $this->assertSame($resource, $detached);
        $this->assertNull($stream->detach());
        $this->assertFalse($stream->isReadable());
    }

    public function testGetMetadata(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);

        $metadata = $stream->getMetadata();

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('stream_type', $metadata);
        $this->assertSame('MEMORY', $metadata['stream_type']);
    }

    public function testGetMetadataKey(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);

        $this->assertSame('MEMORY', $stream->getMetadata('stream_type'));
        $this->assertNull($stream->getMetadata('invalid_key'));
    }

    public function testIsReadable(): void
    {
        $readable = fopen('php://memory', 'r');
        $stream = new Stream($readable);
        $this->assertTrue($stream->isReadable());

        $writable = fopen('php://memory', 'w');
        $stream = new Stream($writable);
        $this->assertFalse($stream->isReadable());
    }

    public function testIsWritable(): void
    {
        $writable = fopen('php://memory', 'w');
        $stream = new Stream($writable);
        $this->assertTrue($stream->isWritable());

        $readable = fopen('php://memory', 'r');
        $stream = new Stream($readable);
        $this->assertFalse($stream->isWritable());
    }

    public function testCreateFromString(): void
    {
        $stream = Stream::create('Hello, World!');

        $this->assertInstanceOf(Stream::class, $stream);
        $this->assertSame('Hello, World!', (string)$stream);
    }
}

