# Keycloak PHP Client

Minimal, framework-agnostic Keycloak client.

## Requirements

- PHP 8.4+

## Installation

```bash
composer require apacheborys/keycloak-php-client
```

## Quick start

```php
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientFactory;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;

$config = new KeycloakClientConfig(
    baseUrl: 'http://localhost:8080',
    clientRealm: 'master',
    clientId: 'backend',
    clientSecret: 'secret',
);

$factory = new KeycloakHttpClientFactory();
$client = $factory->create(
    config: $config,
    httpClient: $psr18Client,
    requestFactory: $psr17RequestFactory,
    streamFactory: $psr17StreamFactory,
);
```

## Tests

```bash
composer test
```

## License

MIT. See `LICENSE`.
