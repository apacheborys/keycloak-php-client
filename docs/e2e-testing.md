# E2E Testing Against a Real Keycloak

The default test suite (`composer test`) never needs Keycloak: it uses unit tests and a local mock HTTP server.
The optional **e2e** suite runs the library against a real Keycloak to catch what mocks cannot: real payload shapes,
permission models, token contents and server-side validation.

It uses only PHPUnit:

- `phpunit.xml.dist` declares two suites, `unit` (default) and `e2e`;
- `composer test` runs only `unit`, so CI and normal local runs are unaffected;
- `composer test:e2e` runs `e2e`;
- without configuration every E2E test is **skipped** with a message naming the missing variables.

## How a Run Works

Every run gets its own disposable stand, so runs never interfere with existing realms or with each other:

1. **Prepare (administrator).** When the first E2E test starts, `KeycloakTestStand` logs in as the administrator and
   creates:
   - a realm named `keycloak-php-client-e2e-<YYYYMMDD-HHMMSS>-<random>`;
   - a confidential client with the static id `keycloak-php-client-e2e` (service account + Direct access grants);
   - service-account roles: exactly the six `realm-management` roles from
     [Keycloak API Client Setup](keycloak-api-client-setup.md#required-permissions).
2. **Test (API client only).** All functional tests authenticate as `keycloak-php-client-e2e` with Client Credentials.
   The administrator credentials are never passed to the library under test, so a green run also proves that the
   documented least-privilege role list is sufficient.
3. **Clean up (administrator).** After all tests have executed, `E2EStandExtension` deletes the client and then the
   realm. A shutdown-function fallback covers abnormal exits; a failed provisioning removes what it created.

The administrator is used only for steps 1 and 3 and for reading the server's password-hashing providers.

## Configure

| Variable | Required | Example / default |
|---|---|---|
| `KEYCLOAK_E2E_BASE_URL` | yes | `http://localhost:8080` |
| `KEYCLOAK_E2E_ADMIN_USERNAME` | yes | `admin` |
| `KEYCLOAK_E2E_ADMIN_PASSWORD` | yes | `admin` |
| `KEYCLOAK_E2E_ADMIN_REALM` | no | `master` |
| `KEYCLOAK_E2E_ADMIN_CLIENT_ID` | no | `admin-cli` |

The administrator must be allowed to create and delete realms (for example the default `admin` of the `master` realm).

Either export the variables in your shell:

```bash
export KEYCLOAK_E2E_BASE_URL=http://localhost:8080
export KEYCLOAK_E2E_ADMIN_USERNAME=admin
export KEYCLOAK_E2E_ADMIN_PASSWORD=admin
```

or copy `phpunit.xml.dist` to `phpunit.xml` (git-ignored; PHPUnit picks it up automatically) and fill in the
`<env>` values. Shell variables take precedence over the file.

`KEYCLOAK_E2E_BASE_URL` must be the URL Keycloak uses as its issuer (the scheme/host/port shown in
`/realms/<realm>/.well-known/openid-configuration`), because token issuer checks compare against it.

## Run

```bash
composer test:e2e

# useful variations
composer test:e2e -- --display-skipped
composer test:e2e -- --filter ConnectivityE2ETest
```

The run prints the stand it used:

```text
[e2e] Provisioned realm "keycloak-php-client-e2e-20261117-172456-cc7bb6" with client "keycloak-php-client-e2e".
...............                                                   15 / 15 (100%)
[e2e] Removed client and realm "keycloak-php-client-e2e-20261117-172456-cc7bb6".
```

## What the Suite Covers

| Test class | Scenario |
|---|---|
| `ConnectivityE2ETest` | Client Credentials token, JWT signature verification (valid and tampered), OIDC discovery, JWKS, realm listing, `401`/`404` mapping to typed exceptions |
| `UserLifecycleE2ETest` | User management through the service facade: `ensureUserIdentifierAttribute` (idempotent, claim exposed through the realm `profile` scope) -> `createUser` with role sync -> `findUser` by local identifier only -> `searchUsers` -> `loginUser` (roles and identifier claim present in the real access token) -> `verifyJwt` -> `refreshToken` -> wrong password -> `updateUser` (profile change and role replacement) -> `deleteUser`; plus importing a user with a **bcrypt** password hash and logging in with the original password |
| `ClientScopeE2ETest` | Client scope create/read/update/delete with realm assignment, protocol mapper create/update/delete |

The bcrypt import test runs only when the server reports a `bcrypt` password-hashing provider. Stock Keycloak ships
`pbkdf2*` and `argon2*` only; `bcrypt`, `md5` and `argon` (the names `HashAlgorithm` uses) come from a custom
provider extension. Without it the test is skipped and the message lists the available providers.

Not covered: `createRealm(...)`, because it needs `create-realm` in `master`, which is deliberately outside the API
client's least-privilege setup.

## Leftovers After an Interrupted Run

Cleanup cannot run if the PHP process is killed (for example `kill -9`, or closing the terminal). Leftover stands are
easy to identify: their realm names start with `keycloak-php-client-e2e-` and contain the creation time. Delete such
realms in the Admin Console (realm settings -> **Action** -> **Delete**) or with the Admin REST API
(`DELETE /admin/realms/<realm>`). Deleting the realm also deletes the client.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| `E2E stand: administrator login failed` | wrong `KEYCLOAK_E2E_ADMIN_*` values, or the admin realm/client differs from `master`/`admin-cli` |
| `E2E stand: POST /admin/realms returned HTTP 403` | the administrator may not create realms |
| `KeycloakAuthorizationException` (403) in a functional test | a permission is missing from the documented role list; update `KeycloakTestStand::SERVICE_ACCOUNT_ROLES` and the tutorial together |
| issuer assertions fail | `KEYCLOAK_E2E_BASE_URL` differs from the issuer Keycloak advertises (for example `localhost` vs `127.0.0.1`, or a proxy host) |
