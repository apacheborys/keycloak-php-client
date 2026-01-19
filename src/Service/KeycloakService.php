<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use LogicException;

final readonly class KeycloakService implements KeycloakServiceInterface
{
    public function __construct(
        private KeycloakHttpClientInterface $httpClient,
        /**
         * @var LocalKeycloakUserBridgeMapperInterface[] $mappers
         */
        private array $mappers,
    ) {
    }

    #[\Override]
    public function createUser(KeycloakUserInterface $localUser): array
    {
        $mapper = $this->getMapperForLocalUser(localUser: $localUser);

        $this->httpClient->createUser(
            dto: $mapper->prepareLocalUserForKeycloakUserCreation(localUser: $localUser)
        );

        return [];
    }

    #[\Override]
    public function updateUser(string $userId, array $payload): array
    {
        return $this->httpClient->updateUser($userId, $payload);
    }

    #[\Override]
    public function deleteUser(string $userId): void
    {
        $this->httpClient->deleteUser($userId);
    }

    #[\Override]
    public function authenticateJwt(string $jwt, string $realm): bool
    {
        $this->httpClient->getJwks(realm: $realm);

        throw new LogicException('JWT authentication is not implemented yet.');
    }

    private function getMapperForLocalUser(KeycloakUserInterface $localUser): LocalKeycloakUserBridgeMapperInterface
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->support($localUser)) {
                return $mapper;
            }
        }

        throw new LogicException(message: "Can't find proper mapper for " . $localUser::class);
    }
}
