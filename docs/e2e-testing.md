# E2E Testing Against a Real Keycloak

The default test suite (`composer test`) never needs Keycloak: it uses unit tests and a local mock HTTP server.
The optional **e2e** suite runs the library against a real Keycloak to catch what mocks cannot: real payload shapes,
permission models, token contents and server-side validation.

It uses only PHPUnit:

- `phpunit.xml.dist` declares two suites, `unit` (default) and `e2e`;
- `composer test` runs only `unit`, so CI and normal local runs are unaffected;
- `composer test:e2e` runs `e2e` and writes machine-readable reports (see [Reports](#reports));
- with no `KEYCLOAK_E2E_*` variable set, every E2E test is **skipped**; an incomplete configuration **fails**.

Everything talks to Keycloak over HTTP only (OIDC endpoints and the Admin REST API at `KEYCLOAK_E2E_BASE_URL`).
Nothing requires access to the Keycloak host or container, so the same suite runs against local, staging and
sandbox environments.

## Choose a Mode

| | Mode 1: preconfigured | Mode 2: provisioned |
|---|---|---|
| Use when | you cannot (or may not) create realms, e.g. shared staging/sandbox | you have an administrator who may create realms, e.g. a local or throw-away Keycloak |
| Preparation | once, manually: a realm and an API client ([below](#mode-1-preconfigured-realm-and-client)) | none; done automatically per run |
| Credentials given to the suite | the API client only | administrator (used only to create/delete the stand) |
| Realm used | your prepared realm, reused across runs | a new realm per run, deleted afterwards |

In mode 1 a **preflight check** runs first: it obtains the API client's token and verifies, from the token's own
`resource_access` claim, that all required roles are granted. A misconfigured client therefore produces one clear
error naming the missing roles instead of many individual `401`/`403` failures.

In both modes the functional tests authenticate **only as the API client**. Administrator credentials are never
passed to the library under test, so a green run also proves that the documented least-privilege role list is enough.

## Mode 1: Preconfigured Realm and Client

### Prepare (once, manually)

1. **Pick a realm** that is not used by real users or live applications. A dedicated realm is strongly recommended.
   During a run the tests create and remove users, realm roles and client scopes (all named `e2e-<random>-...`), and
   they **temporarily** add a *required* user-profile attribute plus a protocol mapper in the realm's `profile` client
   scope. While that attribute exists, other users of the realm may be asked to complete their profile on login.
   Do not use `master` or any realm shared with running services.
2. **Create the API client** in that realm by following
   [Keycloak API Client Setup](keycloak-api-client-setup.md) (Admin Console or Admin REST API), with:
   - **Direct access grants** enabled: the suite covers `loginUser(...)` and `refreshToken(...)`;
   - all six roles from the [permissions table](keycloak-api-client-setup.md#required-permissions), taken from the
     `realm-management` client (from `master-realm` if you use the `master` realm).
3. Note the **realm name**, **client ID** and **client secret**.

### Run

```bash
export KEYCLOAK_E2E_BASE_URL=https://keycloak.staging.example.com
export KEYCLOAK_E2E_REALM=php-client-e2e
export KEYCLOAK_E2E_CLIENT_ID=php-client-api
read -rsp 'Client secret: ' KEYCLOAK_E2E_CLIENT_SECRET; echo; export KEYCLOAK_E2E_CLIENT_SECRET

composer test:e2e
```

Everything a test creates is removed by the test itself, so the realm is left as it was. If a run is killed
(see [Leftovers](#leftovers-after-an-interrupted-run)), look for objects whose names start with `e2e-`.

## Mode 2: Provisioned Realm per Run

```bash
export KEYCLOAK_E2E_BASE_URL=http://localhost:8080
export KEYCLOAK_E2E_ADMIN_USERNAME=admin
read -rsp 'Admin password: ' KEYCLOAK_E2E_ADMIN_PASSWORD; echo; export KEYCLOAK_E2E_ADMIN_PASSWORD

composer test:e2e
```

What happens:

1. **Prepare (administrator, Admin REST API).** On the first E2E test the suite creates a realm named
   `keycloak-php-client-e2e-<YYYYMMDD-HHMMSS>-<random>`, a client with the static id `keycloak-php-client-e2e`
   (service account + Direct access grants) and grants the six `realm-management` roles: exactly the steps of
   [Keycloak API Client Setup](keycloak-api-client-setup.md).
2. **Test (API client only).**
3. **Clean up (administrator).** After all tests ran, the client and then the realm are deleted. A shutdown-function
   fallback covers abnormal exits; a failed provisioning removes whatever it created.

The administrator must be allowed to create and delete realms (for example the default `admin` of `master`).

## All Variables

| Variable | Mode | Required | Default / example |
|---|---|---|---|
| `KEYCLOAK_E2E_BASE_URL` | both | yes | `https://keycloak.staging.example.com` |
| `KEYCLOAK_E2E_REALM` | 1 | yes | `php-client-e2e` |
| `KEYCLOAK_E2E_CLIENT_ID` | 1 | yes | `php-client-api` |
| `KEYCLOAK_E2E_CLIENT_SECRET` | 1 | yes | |
| `KEYCLOAK_E2E_ADMIN_USERNAME` | 2 | yes | `admin` |
| `KEYCLOAK_E2E_ADMIN_PASSWORD` | 2 | yes | |
| `KEYCLOAK_E2E_ADMIN_REALM` | 2 | no | `master` |
| `KEYCLOAK_E2E_ADMIN_CLIENT_ID` | 2 | no | `admin-cli` |
| `KEYCLOAK_E2E_PASSWORD_HASHING_PROVIDERS` | both | no | detected from the server; e.g. `bcrypt` |
| `KEYCLOAK_E2E_REPORT` | both | no | `build/e2e/report.json` |

If any mode-1 variable is set, mode 1 is used and administrator variables are ignored.
Instead of exporting, you can copy `phpunit.xml.dist` to `phpunit.xml` (git-ignored) and fill in the `<env>` values;
shell variables take precedence.

`KEYCLOAK_E2E_BASE_URL` must be the URL Keycloak uses as its issuer (the scheme/host/port shown in
`/realms/<realm>/.well-known/openid-configuration`), because token issuer checks compare against it.

## Reports

Each `composer test:e2e` run writes two files (the `build/` directory is git-ignored):

| File | Format | For |
|---|---|---|
| `build/e2e/junit.xml` | JUnit XML | CI test-report widgets (GitHub Actions, GitLab, Jenkins, ...) |
| `build/e2e/report.json` | JSON, schema `keycloak-php-client.e2e-report/v1` | scripts: one `status` field, plus stand and cleanup details |

The PHPUnit exit code is `0` only when no test failed or errored. Scripts should additionally check the JSON
`status`, because it also covers a failed **cleanup** of the provisioned stand, which happens after the tests ran.

```json
{
    "schema": "keycloak-php-client.e2e-report/v1",
    "status": "passed",
    "problems": [],
    "startedAt": "2026-11-17T20:02:19+00:00",
    "finishedAt": "2026-11-17T20:02:23+00:00",
    "keycloak": {
        "mode": "provisioned",
        "baseUrl": "http://localhost:8080",
        "realm": "keycloak-php-client-e2e-20261117-200215-2c1e67",
        "clientId": "keycloak-php-client-e2e",
        "version": "26.0.5",
        "passwordHashingProviders": ["argon2", "pbkdf2-sha512", "bcrypt", "..."]
    },
    "stand": { "provisioned": true, "provisioningError": null, "cleanup": "succeeded", "cleanupError": null },
    "notes": [],
    "summary": { "total": 15, "passed": 15, "failed": 0, "errored": 0, "skipped": 0, "incomplete": 0 },
    "tests": [
        { "id": "...\\ConnectivityE2ETest::testClientCredentialsTokenIsIssuedForConfiguredClient",
          "class": "...\\ConnectivityE2ETest", "method": "testClientCredentialsTokenIsIssuedForConfiguredClient",
          "status": "passed", "tests": 1, "message": null }
    ]
}
```

| Field | Values |
|---|---|
| `status` | `passed` (no failures; individual skips allowed), `failed` (test failure/error, provisioning error or cleanup failure), `skipped` (nothing configured) |
| `problems` | human-readable reasons when `status` is not `passed` |
| `stand.cleanup` | `not_needed` (mode 1), `pending`, `succeeded`, `failed` |
| `tests[].status` | `passed`, `failed`, `errored`, `skipped`, `incomplete`; `method` is `null` for class-level outcomes (e.g. a whole class skipped or failing in `setUpBeforeClass`), and `tests` holds how many tests that entry covers |

Secrets (client secret, administrator password, tokens) are never written to the report.

Example gate for a CI step or script:

```bash
composer test:e2e || true
status=$(jq -r .status build/e2e/report.json)
jq -r '.problems[]' build/e2e/report.json
[ "$status" = passed ]
```

## What the Suite Covers

| Test class | Scenario |
|---|---|
| `ConnectivityE2ETest` | Client Credentials token, JWT signature verification (valid and tampered), OIDC discovery, JWKS, realm listing, `401`/`404` mapping to typed exceptions |
| `UserLifecycleE2ETest` | User management through the service facade: `ensureUserIdentifierAttribute` (idempotent, claim exposed through the realm `profile` scope) -> `createUser` with role sync -> `findUser` by local identifier only -> `searchUsers` -> `loginUser` (roles and identifier claim present in the real access token) -> `verifyJwt` -> `refreshToken` -> wrong password -> `updateUser` (profile change and role replacement) -> `deleteUser`; plus importing a user with a **bcrypt** password hash and logging in with the original password |
| `ClientScopeE2ETest` | Client scope create/read/update/delete with realm assignment, protocol mapper create/update/delete |

About the identifier attribute: applications normally use the shared `external-user-id` attribute
(`LocalKeycloakUserBridgeMapperInterface::DEFAULT_LOCAL_USER_ID_ATTRIBUTE_NAME`) to keep one user identifier across
several services. The suite deliberately uses its own per-run attribute (`e2e-<random>-external-id`, claim
`e2e_external_id_<random>`) and **never changes or deletes an existing `external-user-id`** attribute or mapper,
so it is safe for realms where that identifier is already in use. (Keycloak saves the user profile as one document,
so adding the temporary attribute rewrites the profile. All other attributes, including `external-user-id`, are sent
back unchanged; the only normalization is an empty `"annotations": {}` on entries that had none, which Keycloak
treats the same as an absent value.)

The bcrypt import test needs a `bcrypt` password-hashing provider. Stock Keycloak ships `pbkdf2*` and `argon2*` only;
`bcrypt`, `md5` and `argon` (the names `HashAlgorithm` uses) come from a custom provider extension. The suite reads the
provider list from `/admin/serverinfo` (in mode 1 with the API client's token). If that is not possible it skips the
test with a hint; set `KEYCLOAK_E2E_PASSWORD_HASHING_PROVIDERS=bcrypt` when you know the server supports it.

Not covered: `createRealm(...)`, because it needs `create-realm` in `master`, which is deliberately outside the API
client's least-privilege setup.

## Leftovers After an Interrupted Run

Cleanup cannot run if the PHP process is killed (for example `kill -9`, or closing the terminal).

- Mode 2: delete realms whose names start with `keycloak-php-client-e2e-` (the name contains the creation time).
  Admin Console: realm settings -> **Action** -> **Delete**; or `DELETE /admin/realms/<realm>`. Deleting the realm
  also deletes the client.
- Mode 1: in your realm, delete users, realm roles and client scopes whose names start with `e2e-`, user-profile
  attributes named `e2e-...-external-id`, and protocol mappers in the `profile` client scope that map such attributes.

## Troubleshooting

| Symptom (`problems` / test message) | Likely cause |
|---|---|
| `Incomplete E2E configuration for ... mode. Missing: ...` | a variable of the chosen mode is not set |
| `E2E stand: administrator login failed (HTTP 401, unauthorized_client ...)` | `KEYCLOAK_E2E_ADMIN_CLIENT_ID` is a confidential client (e.g. `backend`); leave it empty to use `admin-cli` |
| `E2E stand: administrator login failed (HTTP 401, invalid_grant ...)` | wrong `KEYCLOAK_E2E_ADMIN_USERNAME`/`PASSWORD`, or wrong `KEYCLOAK_E2E_ADMIN_REALM` |
| `E2E stand: POST /admin/realms returned HTTP 403` | the administrator may not create realms; use mode 1 |
| `KeycloakAuthenticationException` in `ConnectivityE2ETest` | wrong client ID/secret, or the client lives in another realm than `KEYCLOAK_E2E_REALM` |
| `E2E preflight: ... cannot obtain a token` | wrong realm/client ID/secret, or **Service accounts roles** disabled on the client |
| `E2E preflight: ... is missing client roles of "realm-management"` (or `"master-realm"`) | assign the listed roles to the client's service account; in `master` they are on the `master-realm` client |
| `KeycloakAuthorizationException` (403) in a functional test | the token passed preflight but Keycloak still denies a call; please report it, the documented role list may be incomplete |
| login tests fail with `unauthorized_client` | **Direct access grants** is not enabled on the client |
| issuer assertions fail | `KEYCLOAK_E2E_BASE_URL` differs from the issuer Keycloak advertises (for example `localhost` vs `127.0.0.1`, or a proxy host) |
