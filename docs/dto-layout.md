# DTO Layout

This page explains how DTO namespaces are organized and where new DTOs should live.

## Current Structure

```text
src/DTO/
  PasswordDto.php
  RoleDto.php
  Request/
    User/
    Role/
    ClientScope/
    Realm/UserProfile/
    Oidc/
  Response/
    Oidc/
    Realm/
```

The test tree mirrors this structure:

```text
tests/DTO/
  PasswordDtoTest.php
  RoleDtoTest.php
  Request/...
  Response/...
```

## Placement Rules

### Shared DTOs stay at `DTO/*`

Keep a DTO at the root only when it is not specific to one transport surface or one feature group.

Current examples:

- `PasswordDto`
- `RoleDto`

### Request DTOs go under the feature that consumes them

- `DTO\Request\User\*` for user CRUD/search/password-reset payloads
- `DTO\Request\Role\*` for role listing and role assignment payloads
- `DTO\Request\ClientScope\*` for client-scope and protocol-mapper transport payloads
- `DTO\Request\Realm\UserProfile\*` for realm user-profile attribute payloads
- `DTO\Request\Oidc\*` for OIDC grant payloads

The goal is to group by bounded context, not by CRUD verb.

### Response DTOs follow upstream API surfaces

- `DTO\Response\Oidc\*` for OpenID configuration, JWK and token response models
- `DTO\Response\Realm\*` for Admin REST realm models such as client scopes and user-profile documents

## Why This Layout Is Better Than a Flat Directory

- contributors can navigate by feature instead of scanning a long mixed list;
- request DTOs stay close to the HTTP/service code that uses them;
- tests can mirror the same structure with low ambiguity;
- adding new features does not make one directory increasingly noisy.

## Namespace Migration Examples

Examples of grouped imports:

```php
use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\CreateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OpenIdConfigurationDto;
```

## Special Notes

`AttributeValueDto` currently lives under `DTO\Request\User\*` because it is used by:

- local-user-to-Keycloak identity lookup fallback;
- `CreateUserProfileDto` / `UpdateUserProfileDto` attribute payloads.

It is not a generic top-level DTO because its main use is still tied to user-facing request mapping.

## Contributor Rule Of Thumb

When adding a new DTO, ask:

1. Which HTTP/service surface owns this payload or representation?
2. Is it request-facing, response-facing, or shared across multiple surfaces?
3. Will a contributor naturally look for it under that feature directory?

If the answer points to one feature boundary, place the DTO there instead of adding another root-level file.
