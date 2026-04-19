<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetClientScopeProtocolMappersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetClientScopesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Service\Internal\LocalUserMapperResolver;
use Apacheborys\KeycloakPhpClient\ValueObject\AttributePermission;
use LogicException;
use Override;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

final readonly class KeycloakUserIdentifierAttributeService implements KeycloakUserIdentifierAttributeServiceInterface
{
    public function __construct(
        private KeycloakHttpClientInterface $httpClient,
        private LocalUserMapperResolver $mapperResolver,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function ensureUserIdentifierAttribute(
        KeycloakUserInterface $localUser,
        EnsureUserIdentifierAttributeDto $dto
    ): void {
        $mapper = $this->mapperResolver->resolveForUser(localUser: $localUser);
        $realm = $mapper->getRealm(localUser: $localUser);

        $profile = $this->httpClient->getUserProfile(
            dto: new GetUserProfileDto(realm: $realm),
        );

        if (!$profile->hasAttribute(attributeName: $dto->getAttributeName())) {
            if (!$dto->shouldCreateIfMissing()) {
                $this->debug(
                    message: 'User identifier attribute is missing and auto-create is disabled.',
                    context: [
                        'realm' => $realm,
                        'attribute_name' => $dto->getAttributeName(),
                        'user_id' => $localUser->getId(),
                    ],
                );

                throw new LogicException(
                    message: sprintf(
                        'User profile attribute "%s" is missing in realm "%s".',
                        $dto->getAttributeName(),
                        $realm,
                    )
                );
            }

            $this->httpClient->createUserProfileAttribute(
                dto: new CreateUserProfileAttributeDto(
                    realm: $realm,
                    attribute: $this->buildIdentifierAttribute(dto: $dto),
                ),
            );
            $this->debug(
                message: 'User identifier attribute was created in user profile.',
                context: [
                    'realm' => $realm,
                    'attribute_name' => $dto->getAttributeName(),
                    'user_id' => $localUser->getId(),
                ],
            );
        }

        if (!$dto->shouldExposeInJwt()) {
            return;
        }

        $clientScope = $this->resolveClientScopeByName(
            realm: $realm,
            clientScopeName: $dto->getClientScopeName(),
        );
        $clientScopeId = $clientScope->getId();
        if (!$clientScopeId instanceof UuidInterface) {
            throw new LogicException(
                message: sprintf(
                    'Client scope "%s" in realm "%s" does not have identifier.',
                    $clientScope->getName(),
                    $realm,
                )
            );
        }

        $protocolMappers = $this->httpClient->getClientScopeProtocolMappers(
            dto: new GetClientScopeProtocolMappersDto(
                realm: $realm,
                clientScopeId: $clientScopeId,
            ),
        );
        $existingMapper = $this->findUserAttributeMapper(
            protocolMappers: $protocolMappers,
            attributeName: $dto->getAttributeName(),
        );

        $protocolMapperPayload = $this->buildProtocolMapperPayload(
            dto: $dto,
            existingMapper: $existingMapper,
        );

        if ($existingMapper?->getId() instanceof UuidInterface) {
            $this->httpClient->updateClientScopeProtocolMapper(
                dto: new UpdateClientScopeProtocolMapperDto(
                    realm: $realm,
                    clientScopeId: $clientScopeId,
                    protocolMapperId: $existingMapper->getId(),
                    protocolMapper: $protocolMapperPayload,
                ),
            );
            $this->debug(
                message: 'User identifier protocol mapper was updated.',
                context: [
                    'realm' => $realm,
                    'client_scope' => $clientScope->getName(),
                    'attribute_name' => $dto->getAttributeName(),
                ],
            );

            return;
        }

        $this->httpClient->createClientScopeProtocolMapper(
            dto: new CreateClientScopeProtocolMapperDto(
                realm: $realm,
                clientScopeId: $clientScopeId,
                protocolMapper: $protocolMapperPayload,
            ),
        );
        $this->debug(
            message: 'User identifier protocol mapper was created.',
            context: [
                'realm' => $realm,
                'client_scope' => $clientScope->getName(),
                'attribute_name' => $dto->getAttributeName(),
            ],
        );
    }

    private function buildIdentifierAttribute(EnsureUserIdentifierAttributeDto $dto): AttributeDto
    {
        return new AttributeDto(
            name: $dto->getAttributeName(),
            displayName: $dto->getDisplayName(),
            permissions: [
                'view' => [AttributePermission::ADMIN->value, AttributePermission::USER->value],
                'edit' => [AttributePermission::ADMIN->value, AttributePermission::USER->value],
            ],
            multivalued: false,
            annotations: [
                'inputType' => 'text',
            ],
        );
    }

    private function resolveClientScopeByName(string $realm, string $clientScopeName): ClientScopeDto
    {
        $clientScopes = $this->httpClient->getClientScopes(
            dto: new GetClientScopesDto(realm: $realm),
        );

        foreach ($clientScopes as $clientScope) {
            if ($clientScope->getName() === $clientScopeName) {
                return $clientScope;
            }
        }

        throw new LogicException(
            message: sprintf(
                'Client scope "%s" was not found in realm "%s".',
                $clientScopeName,
                $realm,
            )
        );
    }

    /**
     * @param list<ClientScopesProtocolMapperDto> $protocolMappers
     */
    private function findUserAttributeMapper(
        array $protocolMappers,
        string $attributeName
    ): ?ClientScopesProtocolMapperDto {
        foreach ($protocolMappers as $mapper) {
            if ($mapper->getProtocolMapper() !== 'oidc-usermodel-attribute-mapper') {
                continue;
            }

            $configuredAttributeName = $mapper->getConfig()->get('user.attribute');
            if ($configuredAttributeName !== $attributeName) {
                continue;
            }

            return $mapper;
        }

        return null;
    }

    private function buildProtocolMapperPayload(
        EnsureUserIdentifierAttributeDto $dto,
        ?ClientScopesProtocolMapperDto $existingMapper
    ): ClientScopesProtocolMapperDto {
        return new ClientScopesProtocolMapperDto(
            id: $existingMapper?->getId(),
            name: $existingMapper?->getName() ?? $dto->getProtocolMapperName(),
            protocol: 'openid-connect',
            protocolMapper: 'oidc-usermodel-attribute-mapper',
            consentRequired: $existingMapper?->isConsentRequired() ?? false,
            config: $this->buildProtocolMapperConfig(dto: $dto),
        );
    }

    /**
     * @return array<string, string>
     */
    private function buildProtocolMapperConfig(EnsureUserIdentifierAttributeDto $dto): array
    {
        return [
            'claim.name' => $dto->getJwtClaimName(),
            'jsonType.label' => 'String',
            'id.token.claim' => 'true',
            'access.token.claim' => 'true',
            'userinfo.token.claim' => 'true',
            'introspection.token.claim' => 'true',
            'lightweight.claim' => 'false',
            'user.attribute' => $dto->getAttributeName(),
            'multivalued' => 'false',
            'aggregate.attrs' => 'false',
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function debug(string $message, array $context = []): void
    {
        $this->logger?->debug(message: $message, context: $context);
    }
}
