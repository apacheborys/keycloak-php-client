<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
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
    public function createUser(KeycloakUserInterface $localUser): KeycloakUser
    {
        $mapper = $this->getMapperForLocalUser(localUser: $localUser);
        $createUserDto = $mapper->prepareLocalUserForKeycloakUserCreation(localUser: $localUser);

        $this->httpClient->createUser(dto: $createUserDto);

        $searchDto = new SearchUsersDto(
            realm: $createUserDto->getRealm(),
            email: $createUserDto->getEmail(),
        );

        $result = $this->httpClient->getUsers(dto: $searchDto);

        if (count(value: $result) !== 1) {
            throw new LogicException(message: "Can't find just created user with email " . $createUserDto->getEmail());
        }

        return $result[0];
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
            if ($mapper->support(localUser: $localUser)) {
                return $mapper;
            }
        }

        throw new LogicException(message: "Can't find proper mapper for " . $localUser::class);
    }
}
