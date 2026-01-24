<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;
use Apacheborys\KeycloakPhpClient\ValueObject\HashAlgorithm;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;
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
    public function createUser(
        KeycloakUserInterface $localUser,
        PasswordDto $passwordDto,
    ): KeycloakUser {
        if (is_null($passwordDto->getPlainPassword())) {
            $credentials = [
                new KeycloakCredential(
                    type: KeycloakCredentialType::password(),
                    credentialData: $this->buildCredentialData(passwordDto: $passwordDto),
                    secretData: $this->buildSecretData(passwordDto: $passwordDto)
                )
            ];
        } else {
            $credentials = [];
        }

        $mapper = $this->getMapperForLocalUser(localUser: $localUser);
        $createUserDto = $mapper->prepareLocalUserForKeycloakUserCreation(
            localUser: $localUser,
            credentials: $credentials,
        );

        $this->httpClient->createUser(dto: $createUserDto);

        $searchDto = new SearchUsersDto(
            realm: $createUserDto->getRealm(),
            email: $createUserDto->getEmail(),
        );

        $result = $this->httpClient->getUsers(dto: $searchDto);

        if (count(value: $result) !== 1) {
            throw new LogicException(message: "Can't find just created user with email " . $createUserDto->getEmail());
        }

        if (is_string(value: $passwordDto->getPlainPassword())) {
            $resetUserPasswordDto = new ResetUserPasswordDto(
                realm: $createUserDto->getRealm(),
                user: $result[0],
                type: KeycloakCredentialType::password(),
                value: $passwordDto->getPlainPassword(),
                temporary: false
            );

            $this->httpClient->resetPassword(dto: $resetUserPasswordDto);
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

    #[\Override()]
    public function getAvailableRealms(): array
    {
        return $this->httpClient->getAvailableRealms();
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

    private function buildCredentialData(PasswordDto $passwordDto): string
    {
        $result = [];

        $result = match ($passwordDto->getHashAlgorithm()) {
            HashAlgorithm::ARGON, HashAlgorithm::BCRYPT => [
                'algorithm' => $passwordDto->getHashAlgorithm()->value,
                'hashIterations' => $passwordDto->getHashIterations(),
            ],
            HashAlgorithm::MD5 => [
                'algorithm' => $passwordDto->getHashAlgorithm()->value,
                'hashIterations' => 1,
            ],
            default => throw new LogicException("Can't find proper algorithm to construct credentials data"),
        };

        /** @var string $credentialsData */
        $credentialsData = json_encode(value: $result, flags: JSON_THROW_ON_ERROR);

        return $credentialsData;
    }

    private function buildSecretData(PasswordDto $passwordDto): string
    {
        $result = [];

        $result = match ($passwordDto->getHashAlgorithm()) {
            HashAlgorithm::ARGON, HashAlgorithm::BCRYPT => [
                'value' => $passwordDto->getHashedPassword(),
                'salt' => $passwordDto->getHashSalt(),
            ],
            HashAlgorithm::MD5 => [
                'value' => $passwordDto->getHashedPassword(),
                'salt' => '',
            ],
            default => throw new LogicException("Can't find proper algorithm to construct secret data"),
        };

        /** @var string $secretData */
        $secretData = json_encode(value: $result, depth: JSON_THROW_ON_ERROR);

        return $secretData;
    }
}
