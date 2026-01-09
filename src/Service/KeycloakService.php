<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use LogicException;

final class KeycloakService implements KeycloakServiceInterface
{
    public function __construct(private readonly KeycloakHttpClientInterface $httpClient)
    {
    }

    public function createUser(array $payload): array
    {
        return $this->httpClient->createUser($payload);
    }

    public function updateUser(string $userId, array $payload): array
    {
        return $this->httpClient->updateUser($userId, $payload);
    }

    public function deleteUser(string $userId): void
    {
        $this->httpClient->deleteUser($userId);
    }

    public function authenticateJwt(string $jwt, string $realm): bool
    {
        $this->httpClient->getJwks($realm);

        throw new LogicException('JWT authentication is not implemented yet.');
    }
}
