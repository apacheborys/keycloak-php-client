<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Support\Http;

use Psr\Http\Message\UriInterface;

final class SimpleUri implements UriInterface
{
    /**
     * @var array{
     *     scheme: string,
     *     user: string,
     *     pass: string,
     *     host: string,
     *     port: int|null,
     *     path: string,
     *     query: string,
     *     fragment: string
     * }
     */
    private array $parts;

    public function __construct(string $uri)
    {
        $parsed = parse_url($uri) ?: [];
        $this->parts = [
            'scheme' => (string) ($parsed['scheme'] ?? ''),
            'user' => (string) ($parsed['user'] ?? ''),
            'pass' => (string) ($parsed['pass'] ?? ''),
            'host' => (string) ($parsed['host'] ?? ''),
            'port' => isset($parsed['port']) ? (int) $parsed['port'] : null,
            'path' => (string) ($parsed['path'] ?? ''),
            'query' => (string) ($parsed['query'] ?? ''),
            'fragment' => (string) ($parsed['fragment'] ?? ''),
        ];
    }

    public function getScheme(): string
    {
        return $this->parts['scheme'];
    }

    public function getAuthority(): string
    {
        $authority = $this->parts['host'];

        if ($this->parts['user'] !== '') {
            $authority = $this->getUserInfo() . '@' . $authority;
        }

        if ($this->parts['port'] !== null) {
            $authority .= ':' . $this->parts['port'];
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        if ($this->parts['user'] === '') {
            return '';
        }

        if ($this->parts['pass'] === '') {
            return $this->parts['user'];
        }

        return $this->parts['user'] . ':' . $this->parts['pass'];
    }

    public function getHost(): string
    {
        return $this->parts['host'];
    }

    public function getPort(): ?int
    {
        return $this->parts['port'];
    }

    public function getPath(): string
    {
        return $this->parts['path'];
    }

    public function getQuery(): string
    {
        return $this->parts['query'];
    }

    public function getFragment(): string
    {
        return $this->parts['fragment'];
    }

    public function withScheme(string $scheme): UriInterface
    {
        return $this->cloneWith('scheme', strtolower($scheme));
    }

    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        $clone = clone $this;
        $clone->parts['user'] = $user;
        $clone->parts['pass'] = $password ?? '';

        return $clone;
    }

    public function withHost(string $host): UriInterface
    {
        return $this->cloneWith('host', strtolower($host));
    }

    public function withPort(?int $port): UriInterface
    {
        return $this->cloneWith('port', $port);
    }

    public function withPath(string $path): UriInterface
    {
        return $this->cloneWith('path', $path);
    }

    public function withQuery(string $query): UriInterface
    {
        return $this->cloneWith('query', ltrim($query, '?'));
    }

    public function withFragment(string $fragment): UriInterface
    {
        return $this->cloneWith('fragment', ltrim($fragment, '#'));
    }

    public function __toString(): string
    {
        $uri = '';
        if ($this->parts['scheme'] !== '') {
            $uri .= $this->parts['scheme'] . '://';
        }

        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= $authority;
        }

        $uri .= $this->parts['path'];

        if ($this->parts['query'] !== '') {
            $uri .= '?' . $this->parts['query'];
        }

        if ($this->parts['fragment'] !== '') {
            $uri .= '#' . $this->parts['fragment'];
        }

        return $uri;
    }

    private function cloneWith(string $key, mixed $value): UriInterface
    {
        $clone = clone $this;
        $clone->parts[$key] = $value;

        return $clone;
    }
}
