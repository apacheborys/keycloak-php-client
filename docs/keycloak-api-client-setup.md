# Keycloak API Client Setup

This tutorial creates the Keycloak credentials that `KeycloakClientConfig` needs:

```php
new KeycloakClientConfig(
    baseUrl: 'https://keycloak.example.com',
    clientRealm: 'my-realm',   // realm that owns the API client
    clientId: 'php-client-api',
    clientSecret: '...',
);
```

Written against Keycloak 26 (new Admin Console). Older versions have the same concepts with different screens.

## What You Are Creating

The library does **not** log in as a human admin. It authenticates as a **confidential OpenID Connect client with a service account** and uses the OAuth 2.0 Client Credentials grant to obtain admin tokens. Keycloak represents that service account as a hidden user named `service-account-<client-id>`; you grant it admin permissions through client roles of the built-in `realm-management` client.

Why not a regular admin user:

- no human password to leak or rotate together with a person;
- permissions are scoped to one realm and to exactly the features you use;
- the secret can be regenerated at any time without touching user accounts.

## Choose the Realm

Create the client **in the realm your application manages** (the realm that contains your users). The service account then only has power over that realm.

Placing the client in `master` is only needed when one client must manage several realms or create realms (`createRealm(...)`). That grants much broader power and is out of scope for this tutorial.

## Required Permissions

Assign only what the features you use need. All roles below are client roles of the `realm-management` client in your realm.

| Role | Needed for |
|---|---|
| `view-users` | `findUser`, `findUserById`, `searchUsers`, reading a user's role mappings |
| `manage-users` | `createUser`, `updateUser`, `deleteUser`, setting passwords, assigning/unassigning user roles |
| `view-realm` | reading realm roles and the realm user-profile configuration |
| `manage-realm` | creating/deleting realm roles (role sync), editing the user profile (`ensureUserIdentifierAttribute`), realm default client-scope assignment |
| `view-clients` | listing client scopes and their protocol mappers |
| `manage-clients` | creating/updating/deleting client scopes and protocol mappers (`ensureUserIdentifierAttribute` with `exposeInJwt`) |

Not needed for: OIDC discovery, JWKS and JWT verification (`verifyJwt`). Those use public endpoints.

Shortcut for local or test environments only: the composite `realm-admin` role includes everything above.

This exact list is what the optional [E2E suite](e2e-testing.md) grants its API client on every run, so the list is
verified against a real Keycloak whenever the suite passes.

If a call is missing a permission, the library throws `KeycloakAuthorizationException` (HTTP 403) with the endpoint in its error context.

## Option A: Admin Console

1. Open the Admin Console (for example `http://localhost:8080/admin`) and sign in as an administrator.
2. Use the realm selector in the top-left corner to switch to your realm.
3. Go to **Clients** -> **Create client**.
   - **Client type**: `OpenID Connect`
   - **Client ID**: `php-client-api` (any name works; it becomes `clientId`)
   - **Next**.
4. On **Capability config**:
   - **Client authentication**: `On` (this makes it a confidential client with a secret)
   - **Authorization**: `Off`
   - **Authentication flow**: tick **Service accounts roles**; untick **Standard flow**.
   - Tick **Direct access grants** only if your application calls `loginUser(...)` (Password grant, a legacy/migration flow).
   - **Next**, leave **Login settings** empty, **Save**.
5. Open the **Credentials** tab and copy the **Client secret**. This is `clientSecret`.
6. Open the **Service accounts roles** tab -> **Assign role**.
   - Switch the filter from **Filter by realm roles** to **Filter by clients**.
   - Search for `realm-management`, tick the roles from the table above, then **Assign**.

## Option B: Admin REST API (`curl` + `jq`)

Uses only HTTP calls to the Keycloak URL, so it works the same for a local, staging or sandbox server; no access to the
Keycloak host or container is needed. Requires `curl` and `jq`, and an administrator account that may manage the
target realm (and create realms, for step 0).

```bash
KC=https://keycloak.example.com   # same value as KeycloakClientConfig::baseUrl
ADMIN_REALM=master                # realm of the administrator account
ADMIN_USER=admin
read -rsp 'Admin password: ' ADMIN_PASSWORD; echo   # keeps the password out of shell history
REALM=my-realm
CLIENT_ID=php-client-api
DIRECT_ACCESS_GRANTS=false        # true only if you use loginUser(...)

admin_token() {
  curl -fsS -X POST "$KC/realms/$ADMIN_REALM/protocol/openid-connect/token" \
    -d grant_type=password -d client_id=admin-cli \
    --data-urlencode username="$ADMIN_USER" --data-urlencode password="$ADMIN_PASSWORD" | jq -r .access_token
}
api() { curl -fsS -H "Authorization: Bearer $(admin_token)" -H 'Content-Type: application/json' "$@"; }

# 0. Optional: create the realm (skip when using an existing realm)
api -X POST "$KC/admin/realms" -d "{\"realm\":\"$REALM\",\"enabled\":true}"

# 1. Create the confidential client with a service account
api -X POST "$KC/admin/realms/$REALM/clients" -d "{
  \"clientId\": \"$CLIENT_ID\", \"protocol\": \"openid-connect\", \"publicClient\": false,
  \"serviceAccountsEnabled\": true, \"standardFlowEnabled\": false,
  \"directAccessGrantsEnabled\": $DIRECT_ACCESS_GRANTS }"
CID=$(api "$KC/admin/realms/$REALM/clients?clientId=$CLIENT_ID" | jq -r '.[0].id')

# 2. Grant the realm-management roles from the permissions table to the service account
SA=$(api "$KC/admin/realms/$REALM/clients/$CID/service-account-user" | jq -r .id)
RM=$(api "$KC/admin/realms/$REALM/clients?clientId=realm-management" | jq -r '.[0].id')
ROLES=$(api "$KC/admin/realms/$REALM/clients/$RM/roles" | jq -c '[.[]
  | select(.name | IN("view-users","manage-users","view-realm","manage-realm","view-clients","manage-clients"))
  | {id, name}]')
echo "$ROLES" | jq length   # must print 6
api -X POST "$KC/admin/realms/$REALM/users/$SA/role-mappings/clients/$RM" -d "$ROLES"

# 3. Print the client secret (this is clientSecret)
api "$KC/admin/realms/$REALM/clients/$CID/client-secret" | jq -r .value
```

`curl -f` stops with a non-zero exit code on any HTTP error, for example `409` when the client already exists.

To remove the client later (and the realm, if you created it only for this purpose):

```bash
api -X DELETE "$KC/admin/realms/$REALM/clients/$CID"
api -X DELETE "$KC/admin/realms/$REALM"
```

## Verify the Client

Request a token with the Client Credentials grant:

```bash
curl -s -X POST "$KC/realms/$REALM/protocol/openid-connect/token" \
  -d grant_type=client_credentials \
  -d client_id="$CLIENT_ID" \
  -d client_secret='<client secret>'
```

A JSON response containing `access_token` means the client is configured correctly. `401` with `invalid_client` means the client ID or secret is wrong. `400` with `unauthorized_client` means **Service accounts roles** is not enabled.

To check permissions too, call an admin endpoint with that token:

```bash
curl -s -o /dev/null -w '%{http_code}\n' \
  -H "Authorization: Bearer <access_token>" \
  "$KC/admin/realms/$REALM/users?max=1"
```

`200` means the user permissions are in place. `403` means a role from the table is missing.

## Keep the Secret Safe

- Store `clientSecret` in your secret manager or environment, never in the repository.
- Rotate it from the **Credentials** tab (**Regenerate**); the old secret stops working immediately.
- Use separate clients per environment (dev/staging/prod) so a leaked secret has a limited blast radius.

## Next Steps

- Pass the values to `KeycloakClientConfig` as shown at the top, then follow the Quick Start in the main [README](../README.md).
- To run this library's optional end-to-end tests against your Keycloak, see [E2E Testing](e2e-testing.md).
