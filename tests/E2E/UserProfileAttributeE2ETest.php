<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopeProtocolMappersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\CreateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\DeleteUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\UpdateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileDto;

/**
 * Realm user-profile attributes (declarative user profile) and the identifier-attribute bootstrap flow.
 */
final class UserProfileAttributeE2ETest extends KeycloakE2ETestCase
{
    public function testUserProfileAttributeCanBeCreatedUpdatedAndDeleted(): void
    {
        $attributeName = str_replace('-', '_', $this->uniqueName('e2e_attr'));
        $this->trackProfileAttribute($attributeName);

        $created = $this->httpClient->createUserProfileAttribute(new CreateUserProfileAttributeDto(
            realm: $this->realm,
            attribute: new AttributeDto(
                name: $attributeName,
                displayName: 'E2E attribute',
                permissions: ['view' => ['admin', 'user'], 'edit' => ['admin']],
            ),
        ));
        self::assertTrue($created->hasAttribute($attributeName));
        self::assertSame('E2E attribute', self::attribute($created, $attributeName)->getDisplayName());

        $profile = $this->httpClient->getUserProfile(new GetUserProfileDto(realm: $this->realm));
        self::assertTrue($profile->hasAttribute($attributeName), 'A re-read of the profile must contain the attribute.');
        self::assertTrue($profile->hasAttribute('username'), 'Built-in attributes must be preserved.');
        self::assertTrue($profile->hasAttribute('email'), 'Built-in attributes must be preserved.');
        self::assertArrayHasKey(
            'email',
            self::attribute($profile, 'email')->getValidations(),
            'Built-in validators (with empty config objects) must survive the write round-trip.',
        );

        $updated = $this->httpClient->updateUserProfileAttribute(new UpdateUserProfileAttributeDto(
            realm: $this->realm,
            attribute: new AttributeDto(
                name: $attributeName,
                displayName: 'E2E attribute (renamed)',
                permissions: ['view' => ['admin', 'user'], 'edit' => ['admin']],
            ),
        ));
        self::assertSame('E2E attribute (renamed)', self::attribute($updated, $attributeName)->getDisplayName());

        $deleted = $this->httpClient->deleteUserProfileAttribute(new DeleteUserProfileAttributeDto(
            realm: $this->realm,
            attributeName: $attributeName,
        ));
        self::assertFalse($deleted->hasAttribute($attributeName));
        self::assertFalse(
            $this->httpClient->getUserProfile(new GetUserProfileDto(realm: $this->realm))->hasAttribute($attributeName),
        );
    }

    public function testEnsureUserIdentifierAttributeCreatesAttributeAndExposesItAsJwtClaim(): void
    {
        $attributeName = str_replace('_', '-', $this->uniqueName('e2e-ident'));
        $displayName = 'E2E identifier ' . $attributeName;
        $dto = new EnsureUserIdentifierAttributeDto(
            attributeName: $attributeName,
            displayName: $displayName,
            createIfMissing: true,
            exposeInJwt: true,
        );
        $this->trackProfileAttribute($attributeName);
        $this->trackProtocolMapper($dto->getClientScopeName(), $dto->getProtocolMapperName());

        $this->service->ensureUserIdentifierAttribute($this->realm, $dto);

        // attribute exists in the realm user profile
        $profile = $this->httpClient->getUserProfile(new GetUserProfileDto(realm: $this->realm));
        self::assertTrue($profile->hasAttribute($attributeName));

        // a user-attribute mapper exists in the target client scope
        $scope = $this->findClientScopeByName($dto->getClientScopeName());
        self::assertNotNull($scope, sprintf('Client scope "%s" must exist in the realm.', $dto->getClientScopeName()));
        $mapper = $this->findProtocolMapperByName($scope, $dto->getProtocolMapperName());
        self::assertNotNull($mapper);
        self::assertSame('oidc-usermodel-attribute-mapper', $mapper->getProtocolMapper());
        self::assertSame($attributeName, $mapper->getConfig()->get('user.attribute'));
        self::assertSame($dto->getJwtClaimName(), $mapper->getConfig()->get('claim.name'));

        // the operation is idempotent: a second call must not duplicate the mapper
        $this->service->ensureUserIdentifierAttribute($this->realm, $dto);
        $scopeId = $scope->getId();
        self::assertNotNull($scopeId);
        $mapperNames = array_map(
            static fn (ClientScopesProtocolMapperDto $mapper): string => $mapper->getName(),
            $this->httpClient->getClientScopeProtocolMappers(
                new GetClientScopeProtocolMappersDto(realm: $this->realm, clientScopeId: $scopeId),
            ),
        );
        self::assertCount(1, array_keys($mapperNames, $dto->getProtocolMapperName(), true));

        // a user carrying the attribute gets it as a claim in the access token
        $localUser = $this->newLocalUser(extraAttributes: [$attributeName => 'identifier-value-42']);
        $password = 'E2e-' . bin2hex(random_bytes(6)) . '!';
        $created = $this->service->createUser($localUser, new PasswordDto(plainPassword: $password));
        $this->trackUser($created->getId());

        self::assertSame(['identifier-value-42'], $created->getAttributes()[$attributeName] ?? null);

        $tokens = $this->service->loginUser($localUser, $password);
        self::assertSame(
            'identifier-value-42',
            $tokens->getAccessToken()->getPayload()->getClaim($dto->getJwtClaimName()),
        );
    }

    private static function attribute(UserProfileDto $profile, string $name): AttributeDto
    {
        foreach ($profile->getAttributes() as $attribute) {
            if ($attribute->getName() === $name) {
                return $attribute;
            }
        }

        self::fail(sprintf('Attribute "%s" not found in user profile.', $name));
    }
}
