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
- `KeycloakOidcAuthenticationService`
- `KeycloakJwtVerificationService`
- `KeycloakRealmService`

Recommended composition flow:

1. Build HTTP client via `KeycloakHttpClientFactory`.
2. Build service facade via `KeycloakServiceFactory`.
3. Use `KeycloakServiceInterface` in your application code.

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

## Quality Checks

```bash
composer test
composer phpcs
composer phpstan
composer rector
```

## License

MIT. See [LICENSE](LICENSE).
