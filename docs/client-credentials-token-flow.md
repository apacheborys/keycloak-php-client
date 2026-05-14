# Client Credentials Token Flow

## Goal

Make Client Credentials a first-class token acquisition path for the configured technical client without widening the public API more than necessary in `0.1.x`.

## Current State

- `KeycloakHttpClientFactory` already receives one configured client tuple: `clientRealm`, `clientId`, `clientSecret`.
- `src/Http/Internal/AccessTokenProvider.php` already calls the OIDC token endpoint with `grant_type=client_credentials`.
- `AccessTokenProvider` is internal-only and currently returns a cached `JsonWebToken` for Admin REST authorization.
- `src/Http/Internal/OidcInteractionHttpClient.php` already has a generic private `requestToken(...)` helper, but its public contract only exposes:
  - `requestTokenByPassword(OidcTokenRequestDto $dto)`
  - `refreshToken(OidcTokenRequestDto $dto)`
- `OidcTokenRequestDto` plus `OidcGrantType` currently model only:
  - `password`
  - `refresh_token`
- `OidcTokenResponseDto` is already generic enough for token endpoint responses because `refreshToken` and `idToken` are nullable.
- The service layer exposes:
  - `loginUser(KeycloakUserInterface $user, string $plainPassword)`
  - `refreshToken(OidcTokenRequestDto $dto)`
- Token endpoint failure classification now distinguishes invalid credential/token failures from infrastructure failures:
  - `400 invalid_grant`, `400 invalid_client`, and `400 unauthorized_client` map to `KeycloakAuthenticationException`
  - transport/client failures remain `KeycloakTransportException`

## Target State

Client Credentials should become a public capability for the same configured client that already powers Admin REST authorization.

For `0.1.x`, the smallest useful public surface is:

- `KeycloakHttpClientInterface::requestTokenByClientCredentials(?string $scope = null): OidcTokenResponseDto`
- `KeycloakOidcAuthenticationServiceInterface::requestTokenByClientCredentials(?string $scope = null): OidcTokenResponseDto`
- `KeycloakServiceInterface` inherits that through `KeycloakOidcAuthenticationServiceInterface`

Why this boundary is the right size:

- it makes Client Credentials first-class for normal consumers of the library;
- it reuses the client configuration already supplied to `KeycloakHttpClientFactory`;
- it avoids forcing callers to resupply `clientId` and `clientSecret` on every request;
- it keeps arbitrary multi-client token acquisition out of scope for `0.1.x`.

## Public API vs Internal API

### Should become public

- A configured-client Client Credentials method on the HTTP facade.
- The same method on the service facade so application code can stay on the recommended service boundary.

### Should remain internal

- `AccessTokenProvider`
- the token-endpoint request assembly details
- cache-key generation and cache storage format
- any token-endpoint-specific error reclassification logic

`AccessTokenProvider` should not become public API. It is an internal infrastructure helper for library-owned token acquisition, caching, and admin authorization concerns.

## DTO Decision

Reuse the existing OIDC DTOs.

### Request side

- Keep `OidcTokenRequestDto`.
- Extend `OidcGrantType` with `CLIENT_CREDENTIALS`.
- Extend `OidcTokenRequestDto` validation and `toFormParams()` so `client_credentials` does not require `username`, `password`, or `refresh_token`.

### Response side

- Keep `OidcTokenResponseDto`.
- Do not introduce `TokenResponse` or `TokenSet` in `0.1.x`.

Rationale:

- `OidcTokenResponseDto` already models the relevant token endpoint shape.
- Client Credentials responses fit the existing nullable `refreshToken` and `idToken` fields.
- A parallel `TokenRequest` or `TokenSet` type would duplicate semantics without solving a current mismatch.

The new public `requestTokenByClientCredentials(?string $scope = null)` method can still construct an `OidcTokenRequestDto` internally. That keeps the public boundary cleaner while avoiding DTO duplication.

## Password Grant Position

Password Grant should be treated as a legacy and migration-oriented flow.

That means:

- `loginUser(...)` stays available for compatibility and migrations.
- `requestTokenByPassword(...)` stays available for compatibility and low-level usage.
- both should be documented as not recommended for new end-user login flows.

Recommended wording for docs and PHPDoc:

> Legacy migration flow. Not recommended for new end-user login. Prefer Authorization Code + PKCE outside this library.

For `0.1.x`, this should be documentation and API-positioning guidance, not removal. A hard deprecation annotation can wait until a later release if needed.

## Token Cache Placement

Token caching belongs inside the internal token acquisition path, not in the application-facing service API.

Guidance:

- reuse the existing optional PSR-6 cache already accepted by `KeycloakHttpClientFactory`;
- keep cache ownership in internal token code;
- let Admin REST authorization and public configured-client Client Credentials share the same cache behavior and TTL rules;
- include `scope` in the cache key, together with base URL, realm, and client id;
- do not introduce a large new cache abstraction in `0.1.x`.

This keeps the cache pragmatic and consistent with the rest of the library.

## Exception Semantics

The library should clearly distinguish invalid credentials from transport failures.

### Invalid credentials

Token endpoint credential problems should surface as authentication-oriented exceptions, including cases where Keycloak responds with HTTP `400`.

Minimum classification rule set:

- `401` on the token endpoint -> `KeycloakAuthenticationException`
- `400` with OAuth error `invalid_grant` -> `KeycloakAuthenticationException`
- `400` with OAuth error `invalid_client` -> `KeycloakAuthenticationException`
- `400` with OAuth error `unauthorized_client` -> `KeycloakAuthenticationException`

Examples:

- wrong username/password
- invalid refresh token
- wrong client secret
- client not allowed to use the requested grant

### Transport failures

These should remain `KeycloakTransportException`:

- PSR-18 client/network failures
- non-authentication HTTP failures that are not credential rejection
- generic `400` responses that do not represent an OAuth credential/authentication error

### Scope of the change

This reclassification should live in the token flow, not as a broad change to every `400` response in `KeycloakHttpCore`. The current HTTP-core mapping is acceptable for non-token endpoints.

No additional public exception hierarchy is required as long as token-endpoint `400` credential failures are mapped into the existing authentication branch with sanitized diagnostics.

## Concrete Next-Step Implementation Plan

1. Add `OidcGrantType::CLIENT_CREDENTIALS`.
2. Extend `OidcTokenRequestDto` so it can serialize and validate the new grant type.
3. Add `requestTokenByClientCredentials(?string $scope = null): OidcTokenResponseDto` to:
   - `KeycloakHttpClientInterface`
   - `KeycloakHttpClient`
   - `OidcInteractionHttpClientInterface`
   - `OidcInteractionHttpClient`
   - `KeycloakOidcAuthenticationServiceInterface`
   - `KeycloakOidcAuthenticationService`
   - `KeycloakServiceInterface`
   - `KeycloakService`
   - `TestKeycloakHttpClient`
4. Implement that public method against the factory-configured client credentials instead of asking callers for another public request DTO.
5. Keep `AccessTokenProvider` internal, but let it either:
   - become the shared configured-client Client Credentials implementation, or
   - delegate to a tiny new internal helper if that makes the code cleaner.
6. Share cache behavior between admin-token acquisition and public configured-client Client Credentials token acquisition.
7. Add token-endpoint-specific classification for `invalid_grant`, `invalid_client`, and `unauthorized_client`.
8. Update docs and PHPDoc so Password Grant is explicitly labeled as a legacy/migration flow.
9. Add focused tests when implementation starts:
   - DTO tests for `CLIENT_CREDENTIALS`
   - HTTP facade delegation tests
   - service delegation tests
   - internal cache tests for configured-client Client Credentials
   - exception classification tests for `400 invalid_grant` and `400 invalid_client`

## Summary

For `0.1.x`, the minimal realistic boundary is:

- public: a configured-client `requestTokenByClientCredentials(?string $scope = null)` on the HTTP and service facades;
- internal: keep token providers, cache management, and token-endpoint error classification hidden;
- DTOs: reuse `OidcTokenRequestDto` and `OidcTokenResponseDto`;
- Password Grant: keep it, but document it as legacy and migration-only;
- errors: classify token credential rejection as authentication, not transport.
