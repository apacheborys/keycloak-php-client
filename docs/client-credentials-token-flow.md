# Client Credentials Token Flow

How the library obtains tokens for the configured technical client with the OAuth 2.0 Client Credentials grant,
and how applications can reuse that capability.

To create the client and its service-account permissions in Keycloak, see
[Keycloak API Client Setup](keycloak-api-client-setup.md).

## Public API

Client Credentials is exposed as a first-class **token provider**:

```php
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientFactory;

$tokenProvider = (new KeycloakHttpClientFactory())->createClientCredentialsTokenProvider(
    config: $config,                 // KeycloakClientConfig: baseUrl, clientRealm, clientId, clientSecret
    httpClient: $psr18Client,
    requestFactory: $psr17RequestFactory,
    streamFactory: $psr17StreamFactory,
    cache: $psr6Cache,               // optional
    scope: 'my-api',                 // optional
);

$jwt = $tokenProvider->getAccessToken(); // JsonWebToken
$bearer = $jwt->getRawToken();
```

- `Apacheborys\KeycloakPhpClient\Token\AccessTokenProviderInterface` - the contract: `getAccessToken(): JsonWebToken`.
- `Apacheborys\KeycloakPhpClient\Token\ClientCredentialsTokenProvider` - the implementation for the configured client.

The provider reuses the client tuple from `KeycloakClientConfig`, so callers never resupply the client id or secret.
Multi-client token acquisition is intentionally out of scope.

The same provider type powers Admin REST authorization internally: `KeycloakHttpClientFactory::create(...)` builds a
`ClientCredentialsTokenProvider` (without `scope`) and injects it into every Admin REST HTTP client.

For an arbitrary token-endpoint request, the low-level facade method
`KeycloakHttpClientInterface::requestOidcToken(OidcTokenRequestDto $dto)` is also available, together with
`OidcTokenRequestDto::forClientCredentials(...)`. Application code should normally prefer the token provider.

## DTOs

- Request: `OidcTokenRequestDto` with `OidcGrantType::CLIENT_CREDENTIALS`; `username`, `password` and
  `refresh_token` are not required for that grant.
- Response: `OidcTokenResponseDto`; `refreshToken` and `idToken` are nullable, which fits Client Credentials responses.

No separate `TokenRequest` / `TokenSet` types exist; they would duplicate these DTOs.

## Caching

Caching lives inside `ClientCredentialsTokenProvider`, not in the application-facing API:

- uses the optional PSR-6 pool passed to the factory;
- the cache key combines base URL, client realm, client id and, when set, `scope`;
- a cached token expires `expires_in - 1` seconds after it was issued (small safety skew);
- unreadable or malformed cache entries are ignored and a fresh token is requested.

## Exception Semantics

Token-endpoint credential problems are authentication failures, even when Keycloak answers with HTTP `400`:

| Token endpoint response | Exception |
|---|---|
| `401` | `KeycloakAuthenticationException` |
| `400` with `invalid_grant`, `invalid_client` or `unauthorized_client` | `KeycloakAuthenticationException` |
| other `400`, PSR-18 network/client failures | `KeycloakTransportException` |

This reclassification is limited to the token flow; other endpoints keep the general HTTP-status mapping described
in the main [README](../README.md#error-handling). Diagnostics are sanitized and never contain secrets or tokens.

## Password Grant Position

`loginUser(...)` (service layer) and Password-grant requests through `requestOidcToken(...)` remain available for
compatibility and user migrations, but they are a **legacy flow** and not recommended for new end-user login.
Prefer Authorization Code + PKCE, handled outside this library. A client needs **Direct access grants** enabled in
Keycloak to use the Password grant.

## Verification

- Unit coverage: `tests/Token/ClientCredentialsTokenProviderTest.php` (caching, scope, error mapping).
- Against a real Keycloak: `tests/E2E/ConnectivityE2ETest.php` checks token issuance, signature verification and the
  wrong-secret error mapping. See [E2E Testing](e2e-testing.md).
