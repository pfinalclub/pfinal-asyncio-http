<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

/**
 * PSR-7 Message 通用实现
 */
trait MessageTrait
{
    private array $headers = [];
    private array $headerNames = [];
    private string $protocol = '1.1';
    private ?StreamInterface $stream = null;

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    public function withProtocolVersion(string $version): self
    {
        if ($this->protocol === $version) {
            return $this;
        }

        $new = clone $this;
        $new->protocol = $version;

        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        $name = strtolower($name);

        if (!isset($this->headerNames[$name])) {
            return [];
        }

        return $this->headers[$this->headerNames[$name]];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): self
    {
        $this->validateHeaderName($name);
        $value = $this->normalizeHeaderValue($value);
        $normalized = strtolower($name);

        $new = clone $this;
        if (isset($new->headerNames[$normalized])) {
            unset($new->headers[$new->headerNames[$normalized]]);
        }
        $new->headerNames[$normalized] = $name;
        $new->headers[$name] = $value;

        return $new;
    }

    public function withAddedHeader(string $name, $value): self
    {
        $this->validateHeaderName($name);
        $value = $this->normalizeHeaderValue($value);
        $normalized = strtolower($name);

        $new = clone $this;
        if (isset($new->headerNames[$normalized])) {
            $name = $this->headerNames[$normalized];
            $new->headers[$name] = array_merge($this->headers[$name], $value);
        } else {
            $new->headerNames[$normalized] = $name;
            $new->headers[$name] = $value;
        }

        return $new;
    }

    public function withoutHeader(string $name): self
    {
        $normalized = strtolower($name);

        if (!isset($this->headerNames[$normalized])) {
            return $this;
        }

        $header = $this->headerNames[$normalized];

        $new = clone $this;
        unset($new->headers[$header], $new->headerNames[$normalized]);

        return $new;
    }

    public function getBody(): StreamInterface
    {
        if (!$this->stream) {
            $this->stream = new Stream();
        }

        return $this->stream;
    }

    public function withBody(StreamInterface $body): self
    {
        if ($body === $this->stream) {
            return $this;
        }

        $new = clone $this;
        $new->stream = $body;

        return $new;
    }

    private function setHeaders(array $headers): void
    {
        $this->headerNames = [];
        $this->headers = [];

        foreach ($headers as $name => $value) {
            $this->validateHeaderName($name);
            $value = $this->normalizeHeaderValue($value);
            $normalized = strtolower($name);

            if (isset($this->headerNames[$normalized])) {
                $name = $this->headerNames[$normalized];
                $this->headers[$name] = array_merge($this->headers[$name], $value);
            } else {
                $this->headerNames[$normalized] = $name;
                $this->headers[$name] = $value;
            }
        }
    }

    private function validateHeaderName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $name)) {
            throw new InvalidArgumentException("Invalid header name: $name");
        }
    }

    private function normalizeHeaderValue($value): array
    {
        if (!is_array($value)) {
            return $this->trimHeaderValues([$value]);
        }

        if (count($value) === 0) {
            throw new InvalidArgumentException('Header value cannot be an empty array');
        }

        return $this->trimHeaderValues($value);
    }

    private function trimHeaderValues(array $values): array
    {
        return array_map(function ($value) {
            if (!is_string($value) && !is_numeric($value)) {
                throw new InvalidArgumentException(
                    'Header value must be a string or number'
                );
            }

            return trim((string) $value, " \t");
        }, $values);
    }
}

