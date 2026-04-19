<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Service;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Service\Internal\LocalUserMapperResolver;
use Apacheborys\KeycloakPhpClient\Service\KeycloakUserIdentifierAttributeService;
use Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures\ServiceTestMapper;
use Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures\ServiceTestUser;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use LogicException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class KeycloakUserIdentifierAttributeServiceTest extends TestCase
{
    public function testEnsureUserIdentifierAttributeThrowsWhenMissingAndAutoCreateDisabled(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper($this->buildProfileDto(), $this->buildTokenRequestDto());
        $service = $this->createService(httpClient: $httpClient, mapper: $mapper);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $httpClient->queueResult('getUserProfile', new UserProfileDto());

        try {
            $service->ensureUserIdentifierAttribute(
                localUser: $user,
                dto: new EnsureUserIdentifierAttributeDto(
                    attributeName: 'external-user-id',
                    displayName: 'External user id',
                    createIfMissing: false,
                    exposeInJwt: false,
                ),
            );
            self::fail('Expected LogicException was not thrown.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('external-user-id', $exception->getMessage());
        }

        self::assertSame(
            ['getUserProfile'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
    }

    public function testEnsureUserIdentifierAttributeCreatesMissingAttributeWhenAllowed(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper($this->buildProfileDto(), $this->buildTokenRequestDto());
        $service = $this->createService(httpClient: $httpClient, mapper: $mapper);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $httpClient->queueResult('getUserProfile', new UserProfileDto());
        $httpClient->queueResult(
            'createUserProfileAttribute',
            new UserProfileDto(
                attributes: [
                    new AttributeDto(
                        name: 'external-user-id',
                        displayName: 'External user id',
                    ),
                ],
            ),
        );

        $service->ensureUserIdentifierAttribute(
            localUser: $user,
            dto: new EnsureUserIdentifierAttributeDto(
                attributeName: 'external-user-id',
                displayName: 'External user id',
                createIfMissing: true,
                exposeInJwt: false,
            ),
        );

        self::assertSame(
            ['getUserProfile', 'createUserProfileAttribute'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );

        /** @var CreateUserProfileAttributeDto $createAttributeDto */
        $createAttributeDto = $httpClient->getCalls()[1]['args'][0];
        self::assertSame('master', $createAttributeDto->getRealm());
        self::assertSame('external-user-id', $createAttributeDto->getAttribute()->getName());
        self::assertSame('External user id', $createAttributeDto->getAttribute()->getDisplayName());
    }

    public function testEnsureUserIdentifierAttributeCreatesProtocolMapperWhenExposeEnabled(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper($this->buildProfileDto(), $this->buildTokenRequestDto());
        $service = $this->createService(httpClient: $httpClient, mapper: $mapper);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $clientScope = new ClientScopeDto(
            id: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
            name: 'profile',
            protocol: 'openid-connect',
            protocolMappers: [],
        );

        $httpClient->queueResult(
            'getUserProfile',
            new UserProfileDto(
                attributes: [
                    new AttributeDto(
                        name: 'external-user-id',
                        displayName: 'External user id',
                    ),
                ],
            ),
        );
        $httpClient->queueResult('getClientScopes', [$clientScope]);
        $httpClient->queueResult('getClientScopeProtocolMappers', []);
        $httpClient->queueResult('createClientScopeProtocolMapper', null);

        $service->ensureUserIdentifierAttribute(
            localUser: $user,
            dto: new EnsureUserIdentifierAttributeDto(
                attributeName: 'external-user-id',
                displayName: 'External user id',
                createIfMissing: false,
                exposeInJwt: true,
            ),
        );

        self::assertSame(
            ['getUserProfile', 'getClientScopes', 'getClientScopeProtocolMappers', 'createClientScopeProtocolMapper'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );

        /** @var CreateClientScopeProtocolMapperDto $createMapperDto */
        $createMapperDto = $httpClient->getCalls()[3]['args'][0];
        self::assertSame('master', $createMapperDto->getRealm());
        self::assertSame('external-user-id', $createMapperDto->getProtocolMapper()->getConfig()->get('user.attribute'));
        self::assertSame('external_user_id', $createMapperDto->getProtocolMapper()->getConfig()->get('claim.name'));
    }

    public function testEnsureUserIdentifierAttributeUpdatesProtocolMapperWhenAlreadyExists(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper($this->buildProfileDto(), $this->buildTokenRequestDto());
        $service = $this->createService(httpClient: $httpClient, mapper: $mapper);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $existingMapper = new ClientScopesProtocolMapperDto(
            id: Uuid::fromString('d4e57d40-32a6-4c24-9ae1-b704d5ed882f'),
            name: 'External user id attribute',
            protocol: 'openid-connect',
            protocolMapper: 'oidc-usermodel-attribute-mapper',
            consentRequired: false,
            config: [
                'user.attribute' => 'external-user-id',
                'claim.name' => 'external_user_id',
            ],
        );
        $clientScope = new ClientScopeDto(
            id: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
            name: 'profile',
            protocol: 'openid-connect',
            protocolMappers: [$existingMapper],
        );

        $httpClient->queueResult(
            'getUserProfile',
            new UserProfileDto(
                attributes: [
                    new AttributeDto(
                        name: 'external-user-id',
                        displayName: 'External user id',
                    ),
                ],
            ),
        );
        $httpClient->queueResult('getClientScopes', [$clientScope]);
        $httpClient->queueResult('getClientScopeProtocolMappers', [$existingMapper]);
        $httpClient->queueResult('updateClientScopeProtocolMapper', null);

        $service->ensureUserIdentifierAttribute(
            localUser: $user,
            dto: new EnsureUserIdentifierAttributeDto(
                attributeName: 'external-user-id',
                displayName: 'External user id',
                createIfMissing: false,
                exposeInJwt: true,
                jwtClaimName: 'external_user_id_custom',
            ),
        );

        self::assertSame(
            ['getUserProfile', 'getClientScopes', 'getClientScopeProtocolMappers', 'updateClientScopeProtocolMapper'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );

        /** @var UpdateClientScopeProtocolMapperDto $updateMapperDto */
        $updateMapperDto = $httpClient->getCalls()[3]['args'][0];
        self::assertSame(
            'd4e57d40-32a6-4c24-9ae1-b704d5ed882f',
            $updateMapperDto->getProtocolMapperId()->toString(),
        );
        self::assertSame(
            'external_user_id_custom',
            $updateMapperDto->getProtocolMapper()->getConfig()->get('claim.name'),
        );
    }

    public function testEnsureUserIdentifierAttributeUsesDedicatedProtocolMapperReadInsteadOfEmbeddedScopeMappers(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper($this->buildProfileDto(), $this->buildTokenRequestDto());
        $service = $this->createService(httpClient: $httpClient, mapper: $mapper);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $existingMapper = new ClientScopesProtocolMapperDto(
            id: Uuid::fromString('d4e57d40-32a6-4c24-9ae1-b704d5ed882f'),
            name: 'External user id attribute',
            protocol: 'openid-connect',
            protocolMapper: 'oidc-usermodel-attribute-mapper',
            consentRequired: false,
            config: [
                'user.attribute' => 'external-user-id',
                'claim.name' => 'external_user_id',
            ],
        );
        $clientScope = new ClientScopeDto(
            id: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
            name: 'profile',
            protocol: 'openid-connect',
            protocolMappers: [],
        );

        $httpClient->queueResult(
            'getUserProfile',
            new UserProfileDto(
                attributes: [
                    new AttributeDto(
                        name: 'external-user-id',
                        displayName: 'External user id',
                    ),
                ],
            ),
        );
        $httpClient->queueResult('getClientScopes', [$clientScope]);
        $httpClient->queueResult('getClientScopeProtocolMappers', [$existingMapper]);
        $httpClient->queueResult('updateClientScopeProtocolMapper', null);

        $service->ensureUserIdentifierAttribute(
            localUser: $user,
            dto: new EnsureUserIdentifierAttributeDto(
                attributeName: 'external-user-id',
                displayName: 'External user id',
                createIfMissing: false,
                exposeInJwt: true,
            ),
        );

        self::assertSame(
            ['getUserProfile', 'getClientScopes', 'getClientScopeProtocolMappers', 'updateClientScopeProtocolMapper'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
    }

    public function testEnsureUserIdentifierAttributeThrowsWhenClientScopeIsMissing(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper($this->buildProfileDto(), $this->buildTokenRequestDto());
        $service = $this->createService(httpClient: $httpClient, mapper: $mapper);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $httpClient->queueResult(
            'getUserProfile',
            new UserProfileDto(
                attributes: [
                    new AttributeDto(
                        name: 'external-user-id',
                        displayName: 'External user id',
                    ),
                ],
            ),
        );
        $httpClient->queueResult('getClientScopes', []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Client scope "profile" was not found');

        $service->ensureUserIdentifierAttribute(
            localUser: $user,
            dto: new EnsureUserIdentifierAttributeDto(
                attributeName: 'external-user-id',
                displayName: 'External user id',
                createIfMissing: false,
                exposeInJwt: true,
            ),
        );
    }

    private function createService(
        TestKeycloakHttpClient $httpClient,
        ServiceTestMapper $mapper
    ): KeycloakUserIdentifierAttributeService {
        return new KeycloakUserIdentifierAttributeService(
            httpClient: $httpClient,
            mapperResolver: new LocalUserMapperResolver([$mapper]),
        );
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
}
