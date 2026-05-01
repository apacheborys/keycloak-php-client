# Architecture

## Goals

The library is designed around a few explicit goals:

- keep Keycloak Admin REST and OIDC access available through a focused transport foundation;
- keep multi-step workflows outside of the low-level HTTP layer;
- expose a pragmatic service API for common application workflows without pretending to model the entire Keycloak domain;
- stay extensible when Keycloak returns fields or configurations the library does not manage directly.

## Non-Goals

The library intentionally does not try to:

- model the entire Keycloak Admin REST schema as a complete domain model;
- hide every Keycloak concept behind application-specific abstractions;
- make application code orchestrate Keycloak endpoint flows directly.

## Architectural Overview

This overview intentionally shows only runtime boundaries. Detailed composition is documented in the sections below and in the service/HTTP layer pages.

```mermaid
flowchart LR
    App["Application code"]
    ServiceFacade["KeycloakServiceInterface<br/>application-facing facade"]
    ServiceGraph["Focused services<br/>user / role / identifier attribute / OIDC / JWT / realm"]
    HttpFacade["KeycloakHttpClientInterface<br/>transport facade"]
    HttpGraph["Focused HTTP clients<br/>user / role / client scope / realm settings / OIDC"]
    KC["Keycloak<br/>Admin REST + OIDC"]

    App --> ServiceFacade
    ServiceFacade --> ServiceGraph
    ServiceGraph --> HttpFacade
    HttpFacade --> HttpGraph
    HttpGraph --> KC

    ServiceFactory["KeycloakServiceFactory"] -.-> ServiceFacade
    HttpFactory["KeycloakHttpClientFactory"] -.-> HttpFacade
    Mapper["Local user mappers<br/>+ LocalUserMapperResolver"] -.-> ServiceGraph
    Config["KeycloakClientConfig"] -.-> HttpFactory
```

Solid arrows show the main runtime call path. Dotted arrows show wiring or supporting dependencies.

## Layer Model

```mermaid
flowchart TB
    subgraph Application["Application boundary"]
        App["Your code"]
        Mapper["Local user mapper(s)"]
    end

    subgraph Services["Service layer"]
        Facade["KeycloakService"]
        User["User management"]
        Role["Role management"]
        Identifier["Identifier attributes"]
        Auth["OIDC authentication"]
        Jwt["JWT verification"]
        Realm["Realm listing"]
    end

    subgraph Transport["HTTP layer"]
        HttpFacade["KeycloakHttpClient"]
        UserHttp["Users"]
        RoleHttp["Roles"]
        ScopeHttp["Client scopes"]
        RealmHttp["Realm settings"]
        OidcHttp["OIDC"]
    end

    App --> Facade
    Mapper -.-> Facade
    Facade --> User
    Facade --> Role
    Facade --> Identifier
    Facade --> Auth
    Facade --> Jwt
    Facade --> Realm
    Facade --> HttpFacade
    HttpFacade --> UserHttp
    HttpFacade --> RoleHttp
    HttpFacade --> ScopeHttp
    HttpFacade --> RealmHttp
    HttpFacade --> OidcHttp
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

For application code, the service layer is the intended runtime boundary. The HTTP layer exists underneath it as transport infrastructure and as an extension point for custom service composition.

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
`KeycloakServiceFactory` is the composition root for service helpers: it creates one `KeycloakUserLookup` and injects it into the management services that need Keycloak user resolution.

Local user identity is split into two coordinates:

- `KeycloakUserInterface::getId()` is the stable local application id and must return `int`, `string` or `Ramsey\Uuid\UuidInterface`;
- `KeycloakUserInterface::getKeycloakId()` is the Keycloak user id and may be `null` for applications that cannot persist it locally.

The service layer is authoritative for choosing how to identify the user in Keycloak. It uses `getKeycloakId()` first because the direct user-by-id endpoint is the cheapest lookup, then falls back to searching by the local-id user attribute returned from `LocalKeycloakUserBridgeMapperInterface::getLocalUserIdAttributeName(...)`. The default attribute-name convention is `LocalKeycloakUserBridgeMapperInterface::DEFAULT_LOCAL_USER_ID_ATTRIBUTE_NAME` (`external-user-id`).

Mapper-created DTOs for existing-user operations carry the local id as service metadata. Their Keycloak id may be null; services resolve and populate the final transport DTO before calling HTTP.

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

`KeycloakHttpClient` is intentionally a thin facade over focused transport clients. The service layer is the place where workflows become meaningful to application code, and it is the boundary application code should depend on.

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
- `KeycloakServiceInterface` is the application-facing facade; `KeycloakHttpClientInterface` is an infrastructural facade used below it.
- Mapper strategy objects isolate application-specific realm and profile mapping rules from transport logic.
- Mapper strategy objects compile final Keycloak role names in user profile DTOs, including application-specific prefixes or suffixes.
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
