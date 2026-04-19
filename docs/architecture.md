# Architecture

## Goals

The library is designed around a few explicit goals:

- keep Keycloak Admin REST and OIDC access available through a thin HTTP facade;
- keep multi-step workflows outside of the low-level HTTP layer;
- expose a pragmatic API for common application workflows without pretending to model the entire Keycloak domain;
- stay extensible when Keycloak returns fields or configurations the library does not manage directly.

## Layer Model

```mermaid
flowchart LR
    App["Application Code"]
    Service["KeycloakServiceInterface"]
    Http["KeycloakHttpClientInterface"]

    App --> Service
    App --> Http

    Service --> UserSvc["KeycloakUserManagementService"]
    Service --> RoleSvc["KeycloakRoleManagementService"]
    Service --> IdentifierSvc["KeycloakUserIdentifierAttributeService"]
    Service --> OidcSvc["KeycloakOidcAuthenticationService"]
    Service --> JwtSvc["KeycloakJwtVerificationService"]
    Service --> RealmSvc["KeycloakRealmService"]

    Http --> UserHttp["UserManagementHttpClient"]
    Http --> RoleHttp["RoleManagementHttpClient"]
    Http --> ScopeHttp["ClientScopeManagementHttpClient"]
    Http --> RealmHttp["RealmSettingsManagementHttpClient"]
    Http --> OidcHttp["OidcInteractionHttpClient"]

    UserHttp --> KC["Keycloak"]
    RoleHttp --> KC
    ScopeHttp --> KC
    RealmHttp --> KC
    OidcHttp --> KC
```

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

## Boundary Rules

### HTTP layer

The HTTP layer should answer questions like:

- which Keycloak endpoint is called;
- which DTO is sent or returned;
- how errors are surfaced.

The HTTP layer should not decide business workflows such as:

- whether a missing attribute should be auto-created;
- whether a mapper should be created or updated;
- how local application users are mapped to realms.

### Service layer

The service layer owns orchestration and application-facing intent:

- resolve local-user mapping;
- combine multiple HTTP calls into one higher-level operation;
- enforce workflow decisions and defaults;
- keep the calling application away from Keycloak-specific multi-step coordination.

## Design Principles

### Thin transport, richer orchestration

`KeycloakHttpClient` is intentionally a thin facade over focused transport clients. The service layer is the place where workflows become meaningful to application code.

### Open-door document handling

Some Keycloak APIs behave like document APIs, especially realm user-profile configuration. The library intentionally preserves unknown fields when reading and writing those documents, so unsupported upstream fields are not silently deleted during partial updates.

### Dedicated source of truth over incidental response shape

When a feature has a specialized endpoint, prefer that endpoint over optional embedded fields from another representation. The protocol-mapper lookup flow follows this rule by reading mapper models from `/protocol-mappers/models` instead of relying on `protocolMappers` being embedded in `ClientScopeRepresentation`.
