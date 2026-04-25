# Keycloak PHP Client

Framework-agnostic Keycloak client with a service layer and a thin orchestration facade.

## Requirements

- PHP 8.3+

## Installation

```bash
composer require apacheborys/keycloak-php-client
```

## Architecture

- `KeycloakClientConfig` - immutable connection/config value object for Keycloak client credentials.
- `KeycloakHttpClientFactory` - builds `KeycloakHttpClientInterface` from PSR-18/PSR-17 dependencies.
- `KeycloakHttpClient` - low-level HTTP facade over Keycloak Admin/OIDC endpoints.
- `KeycloakServiceFactory` - builds service graph with sane defaults around `KeycloakService`.
- `KeycloakService` - orchestration layer (user lifecycle + role sync + OIDC/JWT services composition).

Main service components:

- `KeycloakUserManagementService`
- `KeycloakRoleManagementService`
- `KeycloakUserIdentifierAttributeService`
- `KeycloakOidcAuthenticationService`
- `KeycloakJwtVerificationService`
- `KeycloakRealmService`

Recommended composition flow:

1. Build HTTP client via `KeycloakHttpClientFactory`.
2. Build service facade via `KeycloakServiceFactory`.
3. Use `KeycloakServiceInterface` in your application code.

Core design ideas:

- the HTTP layer maps directly to Keycloak Admin REST and OIDC endpoints;
- the service layer owns multi-step workflows and application-facing orchestration;
- realm user-profile mutations are handled as lossless document updates, so unknown Keycloak fields are preserved;
- protocol mapper upsert relies on dedicated mapper endpoints instead of optional embedded fields inside client-scope list responses.

## Quick Start (HTTP Client)

```php
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientFactory;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;

$config = new KeycloakClientConfig(
    baseUrl: 'http://localhost:8080',
    clientRealm: 'master',
    clientId: 'backend',
    clientSecret: 'secret',
);

$httpFactory = new KeycloakHttpClientFactory();
$httpClient = $httpFactory->create(
    config: $config,
    httpClient: $psr18Client,
    requestFactory: $psr17RequestFactory,
    streamFactory: $psr17StreamFactory,
);
```

## Quick Start (Service Layer)

```php
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceFactory;

$serviceFactory = new KeycloakServiceFactory();
$service = $serviceFactory->create(
    httpClient: $httpClient,
    mappers: [$yourLocalUserMapper],
    isRoleCreationAllowed: true,
);

$tokenResponse = $service->loginUser($localUser, 'PlainPassword123!');
$isValid = $service->verifyJwt($tokenResponse->getAccessToken()->getRawToken());
```

## User Identifier Attribute Quick Example

```php
use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;

$service->ensureUserIdentifierAttribute(
    realm: 'master',
    dto: new EnsureUserIdentifierAttributeDto(
        attributeName: 'external-user-id',
        displayName: 'External user id',
        createIfMissing: true,
        exposeInJwt: true,
        clientScopeName: 'profile',
        jwtClaimName: 'external_user_id',
    ),
);
```

Behavior:

- if the user-profile attribute is missing and `createIfMissing=false` -> throws exception;
- if missing and `createIfMissing=true` -> creates attribute in realm user profile;
- if `exposeInJwt=true` -> resolves the target client scope and creates or updates protocol mapper in selected client scope.

For auto-created identifier attributes, the default user-profile payload also marks the attribute as required for `admin` and `user`.

This workflow is intended for bootstrap or migration-like initialization of application-specific identifier attributes:

- the application can ensure that a realm contains the attribute it depends on;
- the same identifier can be exposed as a JWT claim to avoid extra lookups to Keycloak or another repository on hot paths;
- the implementation keeps the supported surface intentionally small while preserving unknown upstream Keycloak fields.

## Documentation

Detailed docs are in [`docs/README.md`](docs/README.md):

- architecture and layering;
- service-layer orchestration and responsibilities;
- HTTP client modules and contracts;
- user profile attributes and JWT exposure flow;
- client scopes and protocol mappers;
- testing strategy and local checks.

## Quality Checks

```bash
composer test
composer phpcs
composer phpstan
composer rector
```

## License

MIT. See [LICENSE](LICENSE).
