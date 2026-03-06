<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

use Assert\Assert;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class KeycloakHttpCore
{
    private const string CLIENT_NAME = 'Keycloak PHP Client';

    public function __construct(
        private string $baseUrl,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private ?CacheItemPoolInterface $cache = null,
    ) {
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getCache(): ?CacheItemPoolInterface
    {
        return $this->cache;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->httpClient->sendRequest(request: $request);
    }

    public function buildEndpoint(string $path, string $query = ''): string
    {
        $endpoint = rtrim(string: $this->baseUrl, characters: '/') . '/' . ltrim($path, '/');

        if ($query === '') {
            return $endpoint;
        }

        return $endpoint . '?' . $query;
    }

    /**
     * @param array<string, string> $headers
     */
    public function createRequest(
        string $method,
        string $endpoint,
        array $headers = [],
        ?string $body = null,
    ): RequestInterface {
        $request = $this->requestFactory
            ->createRequest($method, $endpoint)
            ->withHeader('User-Agent', self::CLIENT_NAME);

        foreach ($headers as $headerName => $headerValue) {
            $request = $request->withHeader($headerName, $headerValue);
        }

        if ($body !== null) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        return $request;
    }

    /**
     * @return array<mixed>
     */
    public function decodeJson(string $body): array
    {
        $data = json_decode(json: $body, associative: true, flags: JSON_THROW_ON_ERROR);
        Assert::that($data)->isArray();

        /** @var array<mixed> $data */

        return $data;
    }
}
