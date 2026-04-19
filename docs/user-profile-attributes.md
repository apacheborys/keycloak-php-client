# User Profile Attributes

This section describes realm user-profile attributes (`/admin/realms/{realm}/users/profile`) and service-level orchestration for custom identifier attributes.

## Service DTO

`EnsureUserIdentifierAttributeDto` (service-level):

- `attributeName`
- `displayName`
- `createIfMissing`
- `exposeInJwt`
- `clientScopeName` (default: `profile`)
- `jwtClaimName` (default: kebab-case to snake_case conversion)
- `protocolMapperName` (default: `<displayName> attribute`)

## Service Flow

`KeycloakUserIdentifierAttributeService::ensureUserIdentifierAttribute()`:

1. Resolve mapper and realm for local user.
2. Read current user profile.
3. If attribute is missing:
   - throw exception when `createIfMissing=false`;
   - create attribute when `createIfMissing=true`.
4. If `exposeInJwt=true`:
   - read client scopes in realm;
   - resolve target scope by name;
   - upsert protocol mapper (`oidc-usermodel-attribute-mapper`) for `user.attribute=<attributeName>`.

## Permissions and Defaults

When auto-creating attribute, default payload includes:

- `permissions.view`: `admin`, `user`
- `permissions.edit`: `admin`, `user`
- `annotations.inputType`: `text`
- `multivalued`: `false`

