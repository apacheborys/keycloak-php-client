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
        $plainPassword = $passwordDto->getPlainPassword();

        if ($plainPassword === null) {
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

        if ($plainPassword !== null) {
            $resetUserPasswordDto = new ResetUserPasswordDto(
                realm: $createUserDto->getRealm(),
                user: $result[0],
                type: KeycloakCredentialType::password(),
                value: $plainPassword,
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

    #[\Override]
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
        $hashContext = $this->buildHashContext(passwordDto: $passwordDto);

        /** @var string $credentialsData */
        $credentialsData = json_encode(
            value: [
                'algorithm' => $hashContext['algorithm'],
                'hashIterations' => $hashContext['hashIterations'],
            ],
            flags: JSON_THROW_ON_ERROR,
        );

        return $credentialsData;
    }

    private function buildSecretData(PasswordDto $passwordDto): string
    {
        $hashContext = $this->buildHashContext(passwordDto: $passwordDto);

        /** @var string $secretData */
        $secretData = json_encode(
            value: [
                'value' => $this->requireHashedPassword(passwordDto: $passwordDto),
                'salt' => $hashContext['salt'],
            ],
            flags: JSON_THROW_ON_ERROR,
        );

        return $secretData;
    }

    /**
     * @return array{algorithm: string, hashIterations: int, salt: string}
     */
    private function buildHashContext(PasswordDto $passwordDto): array
    {
        $hashAlgorithm = $passwordDto->getHashAlgorithm();
        if ($hashAlgorithm === null) {
            throw new LogicException("Hash algorithm is required to build credentials data");
        }

        return match ($hashAlgorithm) {
            HashAlgorithm::ARGON, HashAlgorithm::BCRYPT => [
                'algorithm' => $hashAlgorithm->value,
                'hashIterations' => $this->requireHashIterations(passwordDto: $passwordDto),
                'salt' => $this->requireHashSalt(passwordDto: $passwordDto),
            ],
            HashAlgorithm::MD5 => [
                'algorithm' => $hashAlgorithm->value,
                'hashIterations' => 1,
                'salt' => '',
            ],
        };
    }

    private function requireHashedPassword(PasswordDto $passwordDto): string
    {
        $hashedPassword = $passwordDto->getHashedPassword();
        if ($hashedPassword === null) {
            throw new LogicException("Hashed password is required to build credentials data");
        }

        return $hashedPassword;
    }

    private function requireHashIterations(PasswordDto $passwordDto): int
    {
        $hashIterations = $passwordDto->getHashIterations();
        if ($hashIterations === null) {
            throw new LogicException("Hash iterations are required to build credentials data");
        }

        return $hashIterations;
    }

    private function requireHashSalt(PasswordDto $passwordDto): string
    {
        $hashSalt = $passwordDto->getHashSalt();
        if ($hashSalt === null) {
            throw new LogicException("Hash salt is required to build credentials data");
        }

        return $hashSalt;
    }
}
