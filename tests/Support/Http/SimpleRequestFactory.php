<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Support\Http;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

final class SimpleRequestFactory implements RequestFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new SimpleRequest(
            method: strtoupper($method),
            uri: new SimpleUri((string) $uri),
        );
    }
}
