<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Service;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceFactory;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures\ServiceTestMapper;
use Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures\ServiceTestUser;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use PHPUnit\Framework\TestCase;

final class KeycloakServiceFactoryTest extends TestCase
{
    public function testCreateReturnsKeycloakServiceInterface(): void
    {
        $factory = new KeycloakServiceFactory();

        $service = $factory->create(
            httpClient: new TestKeycloakHttpClient(),
            mappers: [new ServiceTestMapper($this->buildProfileDto(), $this->buildTokenRequestDto())],
        );

        self::assertInstanceOf(KeycloakServiceInterface::class, $service);
    }

    public function testCreateBuildsWorkingServiceGraphForLogin(): void
    {
        $factory = new KeycloakServiceFactory();
        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper($this->buildProfileDto(), $this->buildTokenRequestDto());

        $service = $factory->create(
            httpClient: $httpClient,
            mappers: [$mapper],
        );

        $httpClient->queueResult('requestTokenByPassword', $this->buildTokenResponseDto());

        $result = $service->loginUser(
            user: new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e'),
            plainPassword: 'SecretPassword!2026',
        );

        self::assertInstanceOf(OidcTokenResponseDto::class, $result);
        self::assertSame('requestTokenByPassword', $httpClient->getCalls()[0]['method']);
        self::assertSame('SecretPassword!2026', $mapper->getCapturedPlainPassword());
    }

    private function buildProfileDto(): CreateUserProfileDto
    {
        return new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: true,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
        );
    }

    private function buildTokenRequestDto(): OidcTokenRequestDto
    {
        return new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'user@example.com',
            password: 'secret',
            grantType: OidcGrantType::PASSWORD,
        );
    }

    private function buildTokenResponseDto(): OidcTokenResponseDto
    {
        return new OidcTokenResponseDto(
            accessToken: JsonWebToken::fromRawToken(JwtTestFactory::buildJwtToken()),
            expiresIn: 3600,
            refreshExpiresIn: 0,
            tokenType: 'Bearer',
            nonBeforePolicy: 0,
            scope: 'email profile',
        );
    }
}
