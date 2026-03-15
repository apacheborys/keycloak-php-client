<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Support\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class SimpleResponse implements ResponseInterface
{
    private string $protocolVersion = '1.1';
    private StreamInterface $body;

    /**
     * @var array<string, list<string>>
     */
    private array $headers;

    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        private int $statusCode = 200,
        array $headers = [],
        ?StreamInterface $body = null,
        private string $reasonPhrase = '',
    ) {
        $this->headers = $headers;
        $this->body = $body ?? new SimpleStream();
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): ResponseInterface
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;

        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $headerName => $values) {
            if (strtolower($headerName) === $normalized) {
                return $values !== [];
            }
        }

        return false;
    }

    public function getHeader(string $name): array
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $headerName => $values) {
            if (strtolower($headerName) === $normalized) {
                return $values;
            }
        }

        return [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): ResponseInterface
    {
        $clone = clone $this;
        $clone->removeHeader($name);
        $clone->headers[$name] = $this->normalizeHeaderValues($value);

        return $clone;
    }

    public function withAddedHeader(string $name, $value): ResponseInterface
    {
        $clone = clone $this;
        $existing = $clone->getHeader($name);
        $clone->removeHeader($name);
        $clone->headers[$name] = array_merge($existing, $this->normalizeHeaderValues($value));

        return $clone;
    }

    public function withoutHeader(string $name): ResponseInterface
    {
        $clone = clone $this;
        $clone->removeHeader($name);

        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): ResponseInterface
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException('Invalid status code.');
        }

        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase;

        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    private function removeHeader(string $name): void
    {
        $normalized = strtolower($name);
        foreach (array_keys($this->headers) as $headerName) {
            if (strtolower($headerName) === $normalized) {
                unset($this->headers[$headerName]);
            }
        }
    }

    /**
     * @param string|array<string> $value
     * @return list<string>
     */
    private function normalizeHeaderValues(string|array $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        $values = [];
        foreach ($value as $item) {
            $values[] = (string) $item;
        }

        return $values;
    }
}
