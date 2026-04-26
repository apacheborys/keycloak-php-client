# Architecture

## Goals

The library is designed around a few explicit goals:

- keep Keycloak Admin REST and OIDC access available through a thin HTTP facade;
- keep multi-step workflows outside of the low-level HTTP layer;
- expose a pragmatic API for common application workflows without pretending to model the entire Keycloak domain;
- stay extensible when Keycloak returns fields or configurations the library does not manage directly.

## Non-Goals

The library intentionally does not try to:

- model the entire Keycloak Admin REST schema as a complete domain model;
- hide every Keycloak concept behind application-specific abstractions;
- replace direct HTTP access when the caller needs endpoint-level control.

## Architectural Overview

```mermaid
flowchart TB
    App["Application code"]
    HttpFactory["KeycloakHttpClientFactory"]
    ServiceFactory["KeycloakServiceFactory"]
    Config["KeycloakClientConfig"]
    Mapper["LocalKeycloakUserBridgeMapperInterface[]"]
    Resolver["LocalUserMapperResolver"]

    HttpFacade["KeycloakHttpClientInterface"]
    ServiceFacade["KeycloakServiceInterface"]

    UserSvc["KeycloakUserManagementService"]
    RoleSvc["KeycloakRoleManagementService"]
    IdentifierSvc["KeycloakUserIdentifierAttributeService"]
    OidcSvc["KeycloakOidcAuthenticationService"]
    JwtSvc["KeycloakJwtVerificationService"]
    RealmSvc["KeycloakRealmService"]

    UserHttp["UserManagementHttpClient"]
    RoleHttp["RoleManagementHttpClient"]
    ScopeHttp["ClientScopeManagementHttpClient"]
    RealmHttp["RealmSettingsManagementHttpClient"]
    OidcHttp["OidcInteractionHttpClient"]

    KC["Keycloak Admin REST / OIDC"]

    App --> HttpFactory
    App --> ServiceFactory
    App --> HttpFacade
    App --> ServiceFacade

    Config --> HttpFactory
    HttpFactory --> HttpFacade

    Mapper --> ServiceFactory
    ServiceFactory --> Resolver
    ServiceFactory --> ServiceFacade

    ServiceFacade --> UserSvc
    ServiceFacade --> RoleSvc
    ServiceFacade --> IdentifierSvc
    ServiceFacade --> OidcSvc
    ServiceFacade --> JwtSvc
    ServiceFacade --> RealmSvc

    Resolver --> UserSvc
    Resolver --> RoleSvc
    Resolver --> OidcSvc

    ServiceFacade --> HttpFacade
    HttpFacade --> UserHttp
    HttpFacade --> RoleHttp
    HttpFacade --> ScopeHttp
    HttpFacade --> RealmHttp
    HttpFacade --> OidcHttp

    UserHttp --> KC
    RoleHttp --> KC
    ScopeHttp --> KC
    RealmHttp --> KC
    OidcHttp --> KC
```

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

## Patterns In Use

```mermaid
flowchart LR
    Factory["Factory\nKeycloakHttpClientFactory\nKeycloakServiceFactory"]
    Facade["Facade\nKeycloakHttpClientInterface\nKeycloakServiceInterface"]
    Strategy["Strategy + Resolver\nLocalKeycloakUserBridgeMapperInterface\nLocalUserMapperResolver"]
    Query["Query Object\nSearchUsersDto"]
    Document["Lossless Document Model\nUserProfileDto + AttributeDto + extra fields"]
    Truth["Dedicated Source Of Truth\nprotocol-mappers/models lookup"]

    Factory --> Facade
    Strategy --> Facade
    Query --> Facade
    Document --> Truth
```

Pattern notes:

- Factories keep wiring and dependency composition out of application code.
- Facades keep the public surface compact while allowing the internals to stay specialized.
- Mapper strategy objects isolate application-specific realm and profile mapping rules from transport logic.
- `SearchUsersDto` is treated as a query object because it captures search intent, not a raw REST payload.
- The lossless document model preserves unknown Keycloak fields during read-modify-write cycles.
- Dedicated lookup endpoints are preferred whenever response shape from aggregate endpoints is optional or unstable.

## Typical Flow

```mermaid
sequenceDiagram
    participant App as Application
    participant Service as KeycloakService
    participant Resolver as LocalUserMapperResolver
    participant Http as KeycloakHttpClient
    participant KC as Keycloak

    App->>Service: createUser(localUser, password)
    Service->>Resolver: resolveForUser(localUser)
    Resolver-->>Service: mapper
    Service->>Http: createUser(...)
    Http->>KC: POST /admin/realms/{realm}/users
    Service->>Http: getUserById(...)
    Http->>KC: GET /admin/realms/{realm}/users/{id}
    Service-->>App: KeycloakUser
```
