# Local User Mapping

This page documents the public contract between your application's local user model and the library service layer.

## Why This Contract Exists

The library does not assume that your application stores users exactly like Keycloak does.

Instead, the service layer works with:

- `KeycloakUserInterface` for the local user shape;
- `LocalKeycloakUserBridgeMapperInterface` for realm selection, payload mapping and role mapping.

This keeps the HTTP layer transport-focused and moves application-specific decisions to mapper code.

## `KeycloakUserInterface`

Your local user object must implement:

- `getId(): int|string|UuidInterface`
- `getKeycloakId(): ?string`
- profile getters such as `getUsername()`, `getEmail()`, `getFirstName()`
- `getRoles(): string[]`

Important rules:

- `getId()` is the stable local application identifier.
- `getKeycloakId()` may be `null` when your application cannot persist the Keycloak user id locally.
- returning a stored Keycloak id is still recommended when available because it lets the service layer use the cheapest lookup path.

## Mapper Responsibilities

`LocalKeycloakUserBridgeMapperInterface` is split into several responsibility groups.

### Realm and lookup identity

- `getRealm(localUser)` returns the target Keycloak realm.
- `getLocalUserIdAttribute(localUser)` returns `AttributeValueDto`.

`AttributeValueDto` must provide:

- the Keycloak attribute name used for local-id lookup;
- exactly one lookup value when it is used as a fallback identifier.

The service-layer lookup helper normalizes attribute values, but it will reject a local-id attribute that expands to zero or multiple values during fallback lookup.

### User payload mapping

- `prepareLocalUserForKeycloakUserCreation(...)` returns `CreateUserProfileDto`
- `prepareLocalUserDiffForKeycloakUserUpdate(...)` returns `UpdateUserDto`
- `prepareLocalUserForKeycloakUserDeletion(...)` returns `DeleteUserDto`

Rules:

- `UpdateUserDto::getLocalUserId()` and `DeleteUserDto::getLocalUserId()` must carry `KeycloakUserInterface::getId()`.
- `UpdateUserDto::getUserId()` and `DeleteUserDto::getUserId()` may be `null`.
- the service layer resolves the final Keycloak target id and injects it into the transport DTO before calling HTTP.

### Role mapping

- `prepareLocalUserRolesForKeycloakUserCreation(...)`
- `prepareLocalUserRolesForKeycloakUserUpdate(...)`

These methods return `UserRolesDto` with final Keycloak realm role names.

Rules:

- apply application-specific role prefixes/suffixes inside the mapper;
- return `null` or an empty role list to skip role synchronization;
- do not place role intent into `CreateUserProfileDto` or `UpdateUserDto`.

### OIDC login mapping

- `prepareLocalUserForKeycloakLoginUser(localUser, plainPassword)` returns `OidcTokenRequestDto`

Use this when your local login identity is not a direct one-to-one copy of the Keycloak username contract.

## Service-Owned Identity Resolution

For existing-user operations such as `findUser`, `updateUser` and `deleteUser`, the service layer resolves the Keycloak target user in this order:

1. use `getKeycloakId()` when it is available;
2. otherwise use `getLocalUserIdAttribute(...)` and search Keycloak by attribute;
3. throw when the lookup does not return exactly one Keycloak user.

This decision belongs to the service layer, not to the mapper and not to the HTTP layer.

`KeycloakUserLookup` is only a helper for that service-owned logic. It does not read `KeycloakUserInterface` directly; it receives the mapper-provided `AttributeValueDto`.

## Minimal Mapper Example

```php
use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\UserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\AttributeValueDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;

final class AppUserMapper implements LocalKeycloakUserBridgeMapperInterface
{
    public function getRealm(KeycloakUserInterface $localUser): string
    {
        return 'master';
    }

    public function getLocalUserIdAttribute(KeycloakUserInterface $localUser): AttributeValueDto
    {
        return new AttributeValueDto(
            attributeName: self::DEFAULT_LOCAL_USER_ID_ATTRIBUTE_NAME,
            attributeValue: $localUser->getId(),
        );
    }

    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser
    ): CreateUserProfileDto {
        return new CreateUserProfileDto(
            username: $localUser->getUsername(),
            email: $localUser->getEmail(),
            emailVerified: $localUser->isEmailVerified(),
            enabled: $localUser->isEnabled(),
            firstName: $localUser->getFirstName(),
            lastName: $localUser->getLastName(),
            realm: $this->getRealm($localUser),
            attributes: [
                new AttributeValueDto(
                    attributeName: self::DEFAULT_LOCAL_USER_ID_ATTRIBUTE_NAME,
                    attributeValue: $localUser->getId(),
                ),
            ],
        );
    }

    // other interface methods omitted for brevity
}
```

## Placement Guidance

- user-facing request DTOs for mapper methods live under `DTO\Request\User\*`
- role-specific mapper return DTOs live under `DTO\Request\Role\*`
- OIDC login request DTOs live under `DTO\Request\Oidc\*`

See [DTO Layout](dto-layout.md) for the full namespace structure.
