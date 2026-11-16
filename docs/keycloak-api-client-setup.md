# Creating a Keycloak API Client for This Library

This library authenticates against Keycloak as a **confidential OIDC client with a service account**
(the OAuth 2.0 Client Credentials grant). The service account is a technical user that Keycloak
creates for the client; the Admin REST permissions the library needs are granted to that technical
user through the realm's built-in `realm-management` client roles.

This page explains what to create and why, then shows three ways to do it: the admin console,
the bundled provisioning script, and `kcadm.sh`.

Tested with Keycloak 26.x. Older versions (>= 22) use the same model with slightly different menu labels.

## What the library needs

| Item | Value | Why |
|---|---|---|
| Client type | OpenID Connect, confidential (`Client authentication: On`) | The library sends `client_id` + `client_secret` to the token endpoint. |
| Service accounts roles | `On` | Enables the Client Credentials grant used for every Admin REST call. |
| Direct access grants | `On` **only** if you use `loginUser()` / `requestOidcToken()` with the password grant | This is the legacy Resource Owner Password flow; leave it off otherwise. |
| Standard flow / implicit flow | `Off` | The library never performs a browser login. |
| Realm | the realm whose users, roles and scopes the library should manage | A service account can only manage the realm its client lives in (see "Managing other realms"). |

### Which `realm-management` roles to grant

Grant only what the features you use require:

| Library feature | Methods | Required `realm-management` roles |
|---|---|---|
| Read users | `searchUsers`, `findUser`, `findUserById`, `getUsers`, `getUserById` | `view-users` |
| Create / update / delete users, set passwords | `createUser`, `updateUser`, `deleteUser`, `resetPassword` | `manage-users` |
| Realm roles and user role mappings | `createRole`, `deleteRole`, `getRoles`, `assignRolesToUser`, `unassignRolesFromUser`, `getAvailableUserRoles`, role synchronization inside `createUser`/`updateUser` | `view-realm`, `manage-realm`, `manage-users` |
| Realm user-profile attributes | `getUserProfile`, `createUserProfileAttribute`, `updateUserProfileAttribute`, `deleteUserProfileAttribute`, `ensureUserIdentifierAttribute` | `view-realm`, `manage-realm` |
| Client scopes and protocol mappers | `getClientScopes`, `createClientScope`, `createClientScopeProtocolMapper`, ..., `ensureUserIdentifierAttribute(exposeInJwt: true)` | `view-clients`, `manage-clients` |
| Realm listing | `getAvailableRealms` | `view-realm` (returns the realms the token can see) |
| OIDC discovery, JWKS, token requests, `verifyJwt` | `getOpenIdConfiguration`, `getJwks`, `requestOidcToken`, `verifyJwt` | none (public endpoints) |

The full set used by the E2E suite is therefore:
`view-users`, `manage-users`, `view-realm`, `manage-realm`, `view-clients`, `manage-clients`.

Avoid `realm-admin` unless you really need everything; it is a composite of all `realm-management` roles.

### Custom user attributes

Keycloak 24+ enforces the *declarative user profile*: user attributes that are not declared in
**Realm settings → User profile** are silently dropped on create/update. The library's local-user
mapping relies on at least one custom attribute (`external-user-id` by default), so either:

- declare each attribute in the user profile (`ensureUserIdentifierAttribute()` can do this for you), or
- set **Realm settings → General → Unmanaged attributes** to `Enabled` (what the provisioning script does).

## Option A: Admin console

1. Log in to the admin console and select (or create) the realm.
2. **Clients → Create client**
   - Client type: `OpenID Connect`
   - Client ID: e.g. `php-backend`
   - Next → **Client authentication**: `On`
   - Authentication flow: tick **Service accounts roles**; untick **Standard flow** and **Direct access grants**
     (tick Direct access grants only if you use the password grant)
   - Save.
3. **Clients → php-backend → Credentials** → copy the **Client secret**.
4. **Clients → php-backend → Service accounts roles → Assign role**
   - Filter by clients, search `realm-management`, select the roles from the table above, **Assign**.
5. (If you use custom user attributes) **Realm settings → General → Unmanaged attributes** → `Enabled` → Save,
   or declare the attributes under **Realm settings → User profile**.

## Option B: The bundled provisioning script

`tests/E2E/bin/provision-keycloak.php` performs the steps above through the Admin REST API using a
master-realm admin account. It needs only PHP, is idempotent, and prints the resulting settings as
`export` lines. It is written for the E2E test realm but works for any realm/client name:

```bash
KEYCLOAK_E2E_BASE_URL=http://localhost:8080 \
KEYCLOAK_E2E_ADMIN_USERNAME=admin \
KEYCLOAK_E2E_ADMIN_PASSWORD='<admin password>' \
KEYCLOAK_E2E_REALM=my-realm \
KEYCLOAK_E2E_CLIENT_ID=php-backend \
php tests/E2E/bin/provision-keycloak.php
```

What it does, in order:

1. obtains an admin token from `master` via `admin-cli`;
2. creates the realm if it does not exist;
3. sets the realm's unmanaged attribute policy to `ENABLED`;
4. creates the confidential client with service accounts and direct access grants enabled
   (the E2E suite exercises the legacy password grant too);
5. grants the six `realm-management` roles listed above to the service-account user;
6. verifies that a Client Credentials token can be obtained and prints the settings.

Read the script if you want to adapt it; each step is a single Admin REST call.

## Option C: `kcadm.sh`

```bash
bin/kcadm.sh config credentials --server http://localhost:8080 --realm master --user admin
bin/kcadm.sh create realms -s realm=my-realm -s enabled=true
bin/kcadm.sh create clients -r my-realm -s clientId=php-backend -s publicClient=false \
  -s serviceAccountsEnabled=true -s standardFlowEnabled=false -s directAccessGrantsEnabled=false
bin/kcadm.sh get clients -r my-realm -q clientId=php-backend --fields id,secret
bin/kcadm.sh add-roles -r my-realm --uusername service-account-php-backend --cclientid realm-management \
  --rolename view-users --rolename manage-users --rolename view-realm --rolename manage-realm \
  --rolename view-clients --rolename manage-clients
```

## Wiring the client into the library

```php
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientFactory;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;

$config = new KeycloakClientConfig(
    baseUrl: 'http://localhost:8080',
    clientRealm: 'my-realm',      // the realm the client was created in
    clientId: 'php-backend',
    clientSecret: getenv('KEYCLOAK_CLIENT_SECRET'),
);

$httpClient = (new KeycloakHttpClientFactory())->create(
    config: $config,
    httpClient: $psr18Client,
    requestFactory: $psr17RequestFactory,
    streamFactory: $psr17StreamFactory,
    cache: $psr6Cache, // optional, caches the service-account token and OIDC metadata
);
```

Quick check that the client works, with nothing but `curl`:

```bash
curl -s -X POST http://localhost:8080/realms/my-realm/protocol/openid-connect/token \
  -d grant_type=client_credentials -d client_id=php-backend -d client_secret="$KEYCLOAK_CLIENT_SECRET"
```

## Managing other realms

A service account in realm `A` holds `A`'s `realm-management` roles and therefore can only manage
realm `A`. To manage several realms from one client, create the client in the `master` realm and
grant it the per-realm roles exposed there as `<realm>-realm` client roles (for example
`my-realm-realm → manage-users`). The library's `KeycloakClientConfig::clientRealm` is then `master`
while request DTOs carry the target realm.

## Security notes

- Treat the client secret like a password: inject it from the environment or a secret store, never commit it.
- Rotate the secret from **Clients → Credentials → Regenerate** when it leaks; the library picks up the new value on the next token request.
- Keep **Direct access grants** off unless you depend on `loginUser()`; prefer Authorization Code + PKCE for end-user login.
- The service-account user (`service-account-<clientId>`) appears in the realm's user list; do not delete it, Keycloak recreates it but the role grants are lost.
