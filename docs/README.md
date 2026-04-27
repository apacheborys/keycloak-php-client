# Documentation Index

- [Architecture](architecture.md)
  High-level mental model, layer boundaries, and design principles.
- [Service Layer](service-layer.md)
  Application-facing workflows and the recommended integration boundary.
- [HTTP Layer](http-layer.md)
  Transport contracts, specialized clients, and the internal Keycloak endpoint mapping used by services.
- [User Profile Attributes](user-profile-attributes.md)
  Bootstrap flow for application-specific identifier attributes and JWT exposure.
- [Client Scopes and Mappers](client-scopes-and-mappers.md)
  Scope and mapper DTOs, dedicated mapper lookup, and protocol-mapper upsert behavior.
- [Testing and Quality](testing-and-quality.md)
  Test layers and local quality-check commands.

Recommended reading order:

1. Start with [Architecture](architecture.md).
2. Continue with [Service Layer](service-layer.md).
3. Use [User Profile Attributes](user-profile-attributes.md) and [Client Scopes and Mappers](client-scopes-and-mappers.md) for the identifier-attribute feature set.
4. Read [HTTP Layer](http-layer.md) if you are extending or contributing to the transport foundation.

Audience guide:

- application developers should usually read `README.md`, then [Service Layer](service-layer.md);
- library contributors should start with [Architecture](architecture.md), then [HTTP Layer](http-layer.md);
- teams adopting identifier-attribute bootstrap should read [User Profile Attributes](user-profile-attributes.md) and [Client Scopes and Mappers](client-scopes-and-mappers.md) together.
