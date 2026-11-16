# E2E Testing Against a Real Keycloak

The default test suite (`composer test`) never needs Keycloak: it uses unit tests and a local mock HTTP server.
The optional **e2e** suite runs the library against a real Keycloak to catch what mocks cannot: real payload shapes,
permission models, token contents and server-side validation.

It uses only PHPUnit:

- `phpunit.xml.dist` declares two suites, `unit` (default) and `e2e`;
- `composer test` runs only `unit`, so CI and normal local runs are unaffected;
- `composer test:e2e` runs `e2e`;
- without configuration every E2E test is **skipped** with a message naming the missing variables.

## 1. Prepare Keycloak

Use a **dedicated realm** for E2E runs. The tests create and remove users, roles, client scopes, protocol mappers and
user-profile attributes. Never point the suite at a realm with real users.

1. Create a realm, for example `php-client-e2e` (Admin Console: realm selector -> **Create realm**; or
   `kcadm.sh create realms -s realm=php-client-e2e -s enabled=true`).
2. In that realm, create the API client by following [Keycloak API Client Setup](keycloak-api-client-setup.md), with
   two E2E-specific choices:
   - enable **Direct access grants**: the suite exercises `loginUser(...)` and `refreshToken(...)`;
   - assign **all six** `realm-management` roles from the permissions table (or `realm-admin`).

The suite authenticates in the same realm it tests (`clientRealm` = test realm).

## 2. Configure

Provide four environment variables:

| Variable | Example |
|---|---|
| `KEYCLOAK_E2E_BASE_URL` | `http://localhost:8080` |
| `KEYCLOAK_E2E_REALM` | `php-client-e2e` |
| `KEYCLOAK_E2E_CLIENT_ID` | `php-client-api` |
| `KEYCLOAK_E2E_CLIENT_SECRET` | the client secret |

Either export them in your shell:

```bash
export KEYCLOAK_E2E_BASE_URL=http://localhost:8080
export KEYCLOAK_E2E_REALM=php-client-e2e
export KEYCLOAK_E2E_CLIENT_ID=php-client-api
export KEYCLOAK_E2E_CLIENT_SECRET='...'
```

or copy `phpunit.xml.dist` to `phpunit.xml` (git-ignored; PHPUnit picks it up automatically) and fill in the
`<env>` values. Shell variables take precedence over the file.

`KEYCLOAK_E2E_BASE_URL` must be the URL Keycloak uses as its issuer (the scheme/host/port you see in
`/realms/<realm>/.well-known/openid-configuration`), because token issuer checks compare against it.

## 3. Run

```bash
composer test:e2e

# useful variations
composer test:e2e -- --display-skipped
composer test:e2e -- --filter ConnectivityE2ETest
```

Start with `ConnectivityE2ETest`: if it fails, the client setup is wrong and the other tests will fail for the same reason.

## What the Suite Covers

| Test class | Scenario |
|---|---|
| `ConnectivityE2ETest` | Client Credentials token, JWT signature verification (valid and tampered), OIDC discovery, JWKS, realm listing, `401`/`404` mapping to typed exceptions |
| `UserLifecycleE2ETest` | `ensureUserIdentifierAttribute` (idempotent, exposed through the realm `profile` scope) -> `createUser` with role sync -> `findUser` by local identifier only -> `searchUsers` -> `loginUser` (roles and identifier claim present in the real access token) -> `verifyJwt` -> `refreshToken` -> wrong password -> `updateUser` (profile change and role replacement) -> `deleteUser` |
| `ClientScopeE2ETest` | Client scope create/read/update/delete with realm assignment, protocol mapper create/update/delete |

Not covered yet:

- `createRealm(...)`: needs `create-realm` in `master`, which is deliberately outside the least-privilege setup.
- Hashed password import (`PasswordDto` with a hash): stock Keycloak has no `bcrypt`/`md5` hash providers, so this
  needs a Keycloak with matching custom providers.

## Isolation and Cleanup

- Every object a test class creates is named `e2e-<run id>-...` (the run id is 8 random hex characters), and so is the
  temporary user-profile attribute. The JWT claim it adds is `e2e_external_id_<run id>`.
- Cleanup runs in `tearDown()`/`tearDownAfterClass()` even when assertions fail. Cleanup errors are printed to STDERR
  as `[e2e cleanup] ...` and do not hide the original failure.
- `UserLifecycleE2ETest` temporarily adds a protocol mapper to the realm's built-in `profile` client scope (so the
  claim appears in tokens without changing client configuration) and removes it afterwards.
- If a run is killed mid-way (for example Ctrl+C), search the realm for names starting with `e2e-` to remove leftovers.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| `KeycloakAuthenticationException` in `ConnectivityE2ETest` | wrong client ID/secret, or client created in another realm than `KEYCLOAK_E2E_REALM` |
| `KeycloakAuthorizationException` (403) | a `realm-management` role is missing from the service account |
| login tests fail with `unauthorized_client` | **Direct access grants** is not enabled on the client |
| issuer assertions fail | `KEYCLOAK_E2E_BASE_URL` differs from the issuer Keycloak advertises (for example `localhost` vs `127.0.0.1`, or a proxy host) |
| login fails with `Account is not fully set up` | a realm-level required action or a required user-profile attribute the test user does not have |
