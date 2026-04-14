# Architecture

## Layers

The library is split into two main layers:

- HTTP layer (`src/Http/*`) for direct Keycloak REST/OIDC interaction.
- Service layer (`src/Service/*`) for orchestration and business workflows.

## Entry Points

- `KeycloakHttpClientFactory` creates `KeycloakHttpClientInterface`.
- `KeycloakServiceFactory` creates `KeycloakServiceInterface`.

## HTTP Composition

`KeycloakHttpClient` is a facade over specialized clients:

- `UserManagementHttpClient`
- `RoleManagementHttpClient`
- `ClientScopeManagementHttpClient`
- `RealmSettingsManagementHttpClient`
- `OidcInteractionHttpClient`

## Service Composition

`KeycloakService` is an orchestrator over focused services:

- `KeycloakUserManagementService`
- `KeycloakRoleManagementService`
- `KeycloakUserIdentifierAttributeService`
- `KeycloakOidcAuthenticationService`
- `KeycloakJwtVerificationService`
- `KeycloakRealmService`

Mapper resolution for local users is handled by `LocalUserMapperResolver` and `LocalKeycloakUserBridgeMapperInterface`.

