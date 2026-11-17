# Documentation Index

- [Keycloak API Client Setup](keycloak-api-client-setup.md)
  Tutorial: create the confidential client and service-account permissions that `KeycloakClientConfig` needs.
- [Architecture](architecture.md)
  High-level mental model, layer boundaries, and design principles.
- [Local User Mapping](local-user-mapping.md)
  `KeycloakUserInterface`, mapper responsibilities, and service-owned identity resolution.
- [Service Layer](service-layer.md)
  Application-facing workflows and the recommended integration boundary.
- [HTTP Layer](http-layer.md)
  Transport contracts, specialized clients, and the internal Keycloak endpoint mapping used by services.
- [Client Credentials Token Flow](client-credentials-token-flow.md)
  `ClientCredentialsTokenProvider`: public token provider for the configured client, caching and token-error semantics.
- [DTO Layout](dto-layout.md)
  Request/response namespace grouping, placement rules, and migration notes for DTO imports.
- [User Profile Attributes](user-profile-attributes.md)
  Bootstrap flow for application-specific identifier attributes and JWT exposure.
- [Client Scopes and Mappers](client-scopes-and-mappers.md)
  Scope and mapper DTOs, dedicated mapper lookup, and protocol-mapper upsert behavior.
- [Testing and Quality](testing-and-quality.md)
  Test layers and local quality-check commands.
- [E2E Testing](e2e-testing.md)
  Optional end-to-end suite against a real Keycloak: setup, configuration, coverage and cleanup.

Recommended reading order:

1. If you do not have Keycloak credentials yet, start with [Keycloak API Client Setup](keycloak-api-client-setup.md).
2. Read [Architecture](architecture.md).
3. Continue with [Local User Mapping](local-user-mapping.md) if your application passes local users into the service layer.
4. Continue with [Service Layer](service-layer.md).
5. Use [User Profile Attributes](user-profile-attributes.md) and [Client Scopes and Mappers](client-scopes-and-mappers.md) for the identifier-attribute feature set.
6. Read [DTO Layout](dto-layout.md) and [HTTP Layer](http-layer.md) if you are extending or contributing to the transport foundation.

Audience guide:

- application developers should usually read `README.md`, then [Service Layer](service-layer.md);
- application developers implementing `LocalKeycloakUserBridgeMapperInterface` should also read [Local User Mapping](local-user-mapping.md);
- library contributors should start with [Architecture](architecture.md), then [HTTP Layer](http-layer.md);
- contributors touching request/response models should also read [DTO Layout](dto-layout.md);
- teams adopting identifier-attribute bootstrap should read [User Profile Attributes](user-profile-attributes.md) and [Client Scopes and Mappers](client-scopes-and-mappers.md) together.
