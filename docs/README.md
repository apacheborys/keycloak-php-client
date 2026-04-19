# Documentation Index

- [Architecture](architecture.md)
  High-level mental model, layer boundaries, and design principles.
- [Service Layer](service-layer.md)
  Application-facing workflows and when to prefer services over direct HTTP access.
- [HTTP Layer](http-layer.md)
  Low-level contracts, specialized clients, and direct Keycloak endpoint mapping.
- [User Profile Attributes](user-profile-attributes.md)
  Bootstrap flow for application-specific identifier attributes and JWT exposure.
- [Client Scopes and Mappers](client-scopes-and-mappers.md)
  Scope and mapper DTOs, dedicated mapper lookup, and protocol-mapper upsert behavior.
- [Testing and Quality](testing-and-quality.md)
  Test layers and local quality-check commands.

Recommended reading order:

1. Start with [Architecture](architecture.md).
2. Continue with [Service Layer](service-layer.md) if you plan to use the high-level API.
3. Read [HTTP Layer](http-layer.md) if you need low-level control.
4. Use [User Profile Attributes](user-profile-attributes.md) and [Client Scopes and Mappers](client-scopes-and-mappers.md) for the identifier-attribute feature set.
