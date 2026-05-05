# Testing and Quality

## Test Types

- DTO unit tests (`tests/DTO/*`)
- Service unit tests (`tests/Service/*`)
- HTTP facade tests (`tests/Http/*`)
- Internal HTTP integration-like tests with mock server (`tests/Http/Internal/*`)
- OIDC behavior checks (`tests/Oidc/*`)

Why the test suite is layered this way:

- DTO tests protect parsing and serialization contracts;
- service tests protect orchestration and branching decisions;
- HTTP facade tests protect delegation wiring;
- internal HTTP integration-like tests protect request shape and endpoint mapping without needing a live Keycloak instance.

DTO tests mirror the source layout:

- `tests/DTO/Request/User/*`, `Role/*`, `ClientScope/*`, `Realm/UserProfile/*`, `Oidc/*`
- `tests/DTO/Response/Oidc/*`
- `tests/DTO/Response/Realm/*`
- root-level shared DTO tests such as `PasswordDtoTest.php` and `RoleDtoTest.php`

## Commands

Run all checks:

```bash
composer check
```

Run separately:

```bash
composer test
composer phpcs
composer phpstan
composer rector
```

Direct PHPUnit run (with deprecation output):

```bash
./vendor/bin/phpunit --display-phpunit-deprecations
```

Notes:

- some internal HTTP tests may be skipped when the local mock server is unavailable in the current environment;
- in constrained environments, `phpstan` may need an explicit memory limit, for example `php -d memory_limit=512M vendor/bin/phpstan analyse -c phpstan.neon --debug`.
