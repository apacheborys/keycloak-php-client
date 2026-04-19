# Testing and Quality

## Test Types

- DTO unit tests (`tests/DTO/*`)
- Service unit tests (`tests/Service/*`)
- HTTP facade tests (`tests/Http/*`)
- Internal HTTP integration-like tests with mock server (`tests/Http/Internal/*`)
- OIDC behavior checks (`tests/Oidc/*`)

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

