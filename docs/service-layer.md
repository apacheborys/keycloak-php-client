# Service Layer

## Main Facade

`KeycloakServiceInterface` aggregates:

- user management;
- user identifier attribute management;
- OIDC authentication;
- JWT verification;
- realm listing.

In practice, the facade mixes two kinds of operations:

- orchestration methods such as `createUser`, `updateUser`, `ensureUserIdentifierAttribute`;
- convenience pass-through methods such as `searchUsers`, `findUserById`, `loginUser`.

Application code should integrate through the service layer. Even when an operation is only one transport call today, keeping it behind the service boundary preserves a consistent integration style and leaves room for future orchestration, defaults or validation without changing the application contract.

## Service Composition

```mermaid
flowchart TD
    Facade["KeycloakService"]
    Facade --> User["KeycloakUserManagementService"]
    Facade --> Role["KeycloakRoleManagementService"]
    Facade --> Identifier["KeycloakUserIdentifierAttributeService"]
    Facade --> Oidc["KeycloakOidcAuthenticationService"]
    Facade --> Jwt["KeycloakJwtVerificationService"]
    Facade --> Realm["KeycloakRealmService"]
```

## Method Selection Guide

- Use `findUser(localUser)` when your application already has a local user object and wants mapper-based realm resolution.
- Use `findUserById(realm, userId)` when your application already knows both the realm and the Keycloak user id.
- Use `searchUsers(SearchUsersDto)` when your application needs repository-style lookup with filters and pagination.
- Use `createUser` or `updateUser` when mapper-driven transformation from local user shape to Keycloak payload is part of the use case.

## Responsibilities

### `createUser`

- creates user via `KeycloakUserManagementService`;
- synchronizes roles via `KeycloakRoleManagementService`;
- uses roles from the mapper-created `CreateUserProfileDto`;
- creates missing realm roles and assigns them when the mapper returns a non-empty role list;
- skips role synchronization when the mapper returns an empty role list;
- fetches final user representation by id.

### `updateUser`

- updates user profile via `KeycloakUserManagementService`;
- synchronizes role assignments/unassignments;
- uses roles from the mapper-created `UpdateUserDto`;
- creates missing desired realm roles and synchronizes mappings when the mapper returns a non-empty role list;
- skips role synchronization when the mapper returns null or an empty role list;
- fetches final user representation by id.

### `findUser`

- resolves realm from mapper;
- reads the Keycloak user id from `KeycloakUserInterface::getKeycloakId()`;
- fetches the current Keycloak representation through the dedicated user-by-id endpoint.

### `findUserById`

- performs direct user lookup by explicit realm and Keycloak user id;
- skips mapper resolution because the caller already provides the required lookup coordinates;
- is useful for workflows that persist Keycloak ids externally.

### `searchUsers`

- delegates user repository search to `KeycloakUserManagementService`;
- accepts `SearchUsersDto` as a query object with realm, filters and pagination;
- returns the current Keycloak user representations matching the query.

### `deleteUser`

- delegates deletion workflow to `KeycloakUserManagementService`.

### `ensureUserIdentifierAttribute`

Handled by `KeycloakUserIdentifierAttributeService`:

- uses the explicit realm provided by the caller;
- checks realm user-profile attribute existence;
- optionally creates missing attribute;
- optionally creates/updates protocol mapper in client scope for JWT exposure.

This method is designed for application bootstrap or migration-like initialization. It lets the application declare:

- which user-profile attribute must exist in the target realm;
- whether the attribute may be auto-created;
- whether the same value must be exposed as a JWT claim.

The method intentionally hides the multi-step orchestration required to make this safe and predictable.

### `loginUser` and `refreshToken`

- delegated to `KeycloakOidcAuthenticationService`.

### `verifyJwt`

- delegated to `KeycloakJwtVerificationService`.

## Service Boundary Notes

- Services are the right place for defaults such as the identifier-attribute payload and default JWT claim name.
- Services may perform multiple HTTP calls to complete one operation.
- Services should prefer stable Keycloak contracts over incidental response shape.
- `SearchUsersDto` is acceptable at the service boundary because it models a repository query, not a raw transport payload.
- Services are allowed to throw workflow-level exceptions such as "required attribute is missing and auto-create is disabled".
- Role naming is a mapper policy. The mapper should apply any prefix/suffix before returning role DTOs.
- Missing returned roles are created by the service; return no roles when role management should not run for that local user type.

## Service Patterns

```mermaid
flowchart LR
    Facade["KeycloakService"]
    UserSvc["User Management"]
    RoleSvc["Role Management"]
    IdentifierSvc["Identifier Attribute"]
    Resolver["LocalUserMapperResolver"]
    Http["KeycloakHttpClient"]

    Facade --> UserSvc
    Facade --> RoleSvc
    Facade --> IdentifierSvc
    UserSvc --> Resolver
    RoleSvc --> Resolver
    UserSvc --> Http
    RoleSvc --> Http
    IdentifierSvc --> Http
```

Interpretation:

- orchestration lives in focused services rather than in the facade itself;
- mapper resolution is a dependency of user- and role-oriented workflows, not of the HTTP layer;
- the facade stays small and coordinates service composition rather than re-implementing workflow logic.
- application code should depend on this facade/service graph rather than on transport clients directly.
