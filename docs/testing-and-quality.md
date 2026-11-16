# Testing and Quality

## Test Types

- DTO unit tests (`tests/DTO/*`)
- Service unit tests (`tests/Service/*`)
- HTTP facade tests (`tests/Http/*`)
- Internal HTTP integration-like tests with mock server (`tests/Http/Internal/*`)
- OIDC behavior checks (`tests/Oidc/*`)
- Opt-in end-to-end tests against a real Keycloak (`tests/E2E/*`, see below)

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

## End-to-End Tests Against a Real Keycloak

`tests/E2E` contains tests that drive the library against a live Keycloak instance through the
public factories, exactly like an application would. They are **opt-in**:

- `phpunit.xml.dist` declares two suites, `unit` (default) and `e2e`; `composer test`, `vendor/bin/phpunit`
  and CI run only `unit`;
- the `e2e` suite runs only when explicitly selected, and every E2E test skips itself unless all
  `KEYCLOAK_E2E_*` variables below are set.

No extra tooling is involved: PHPUnit test suites and the test-support PSR-18/PSR-17 implementations
already in `tests/Support` are enough.

### 1. Provision a realm and an API client

The suite needs a confidential client with a service account and `realm-management` roles. The
background and the manual steps are in [Keycloak API Client Setup](keycloak-api-client-setup.md);
the shortest path is the bundled script, which uses a master-realm admin account:

```bash
KEYCLOAK_E2E_BASE_URL=http://localhost:8080 \
KEYCLOAK_E2E_ADMIN_PASSWORD='<admin password>' \
php tests/E2E/bin/provision-keycloak.php
```

Defaults: admin user `admin`, realm `keycloak-php-client-e2e`, client `php-client-e2e`.
The script is idempotent and prints the `export` lines for step 2.

Use a **dedicated realm**. The tests create and delete users, realm roles, client scopes, protocol
mappers and user-profile attributes; all of them carry an `e2e-` prefix and are removed in `tearDown()`,
but you should not point the suite at a realm you care about.

### 2. Configure and run

```bash
export KEYCLOAK_E2E_BASE_URL='http://localhost:8080'
export KEYCLOAK_E2E_REALM='keycloak-php-client-e2e'
export KEYCLOAK_E2E_CLIENT_ID='php-client-e2e'
export KEYCLOAK_E2E_CLIENT_SECRET='<secret printed by the script>'

composer test:e2e
# or
vendor/bin/phpunit --testsuite e2e
# both suites at once
vendor/bin/phpunit --testsuite unit,e2e
```

Alternatively create a git-ignored `phpunit.xml` next to `phpunit.xml.dist` and set the variables there:

```xml
<phpunit ...>
  <php>
    <env name="KEYCLOAK_E2E_BASE_URL" value="http://localhost:8080"/>
    <env name="KEYCLOAK_E2E_REALM" value="keycloak-php-client-e2e"/>
    <env name="KEYCLOAK_E2E_CLIENT_ID" value="php-client-e2e"/>
    <env name="KEYCLOAK_E2E_CLIENT_SECRET" value="..."/>
  </php>
</phpunit>
```

### What is covered

| Test class | Scope |
|---|---|
| `OidcDiscoveryAndTokenE2ETest` | OIDC discovery document, Client Credentials token, JWKS, `verifyJwt` (valid, tampered), wrong secret → `KeycloakAuthenticationException`, realm listing |
| `UserLifecycleE2ETest` | Service layer with a real mapper: `createUser` (password + role sync + local-id attribute), `findUser` by attribute, `findUserById`, `searchUsers`, `loginUser` (password grant), `updateUser` (profile + role removal), `deleteUser`, not-found and wrong-password errors |
| `RoleManagementE2ETest` | Realm role create/list/delete, assign/unassign to a user, available roles |
| `ClientScopeManagementE2ETest` | Client scope create/read/update/delete with realm assignment, protocol mapper create/update/delete |
| `UserProfileAttributeE2ETest` | User-profile attribute create/update/delete, `ensureUserIdentifierAttribute` end to end including the resulting JWT claim and idempotency |

### Writing new E2E tests

- extend `KeycloakE2ETestCase`; it builds `$this->httpClient` and `$this->service` from the environment and skips when unconfigured;
- name everything with `$this->uniqueName('e2e-...')` and register it with the matching `track*()` method so cleanup happens even if the test fails;
- prefer the service layer (`$this->service`) with `E2ELocalUser` + `E2EUserMapper`; use `$this->httpClient` for transport-level behavior;
- never hard-code ids or assume an empty realm: assert on the entities the test created.
