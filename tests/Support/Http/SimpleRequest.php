<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Support\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class SimpleRequest implements RequestInterface
{
    /**
     * @var array<string, list<string>>
     */
    private array $headers;

    private string $protocolVersion = '1.1';
    private StreamInterface $body;
    private string $requestTarget = '';

    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        private string $method,
        private UriInterface $uri,
        array $headers = [],
        ?StreamInterface $body = null,
    ) {
        $this->headers = $headers;
        $this->body = $body ?? new SimpleStream();
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== '') {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }

        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;

        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): RequestInterface
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);

        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $clone = clone $this;
        $clone->uri = $uri;

        if (!$preserveHost && $uri->getHost() !== '') {
            $clone = $clone->withHeader('Host', $uri->getHost());
        }

        return $clone;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): RequestInterface
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

    public function withHeader(string $name, $value): RequestInterface
    {
        $clone = clone $this;
        $clone->removeHeader($name);
        $clone->headers[$name] = $this->normalizeHeaderValues($value);

        return $clone;
    }

    public function withAddedHeader(string $name, $value): RequestInterface
    {
        $clone = clone $this;
        $existing = $clone->getHeader($name);
        $clone->removeHeader($name);
        $clone->headers[$name] = array_merge($existing, $this->normalizeHeaderValues($value));

        return $clone;
    }

    public function withoutHeader(string $name): RequestInterface
    {
        $clone = clone $this;
        $clone->removeHeader($name);

        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): RequestInterface
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
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
