# Client Scopes and Mappers

## DTOs

- `ClientScopeDto`
- `ClientScopesProtocolMapperDto`
- `ClientScopesProtocolMapperConfigDto`

## `ClientScopesProtocolMapperConfigDto`

`ClientScopesProtocolMapperDto` stores mapper config as a dedicated DTO instead of raw array.

Benefits:

- consistent validation for config keys/values;
- explicit API (`has`, `get`, `toArray`);
- less accidental typo-prone array access.

Typical keys for `oidc-usermodel-attribute-mapper`:

- `user.attribute`
- `claim.name`
- `jsonType.label`
- `id.token.claim`
- `access.token.claim`
- `userinfo.token.claim`
- `introspection.token.claim`
- `lightweight.claim`
- `multivalued`
- `aggregate.attrs`

## HTTP Operations

Handled by `ClientScopeManagementHttpClientInterface`:

- list scopes by realm;
- get scope by id;
- create/update/delete scope;
- create/update/delete protocol mapper.

These operations map to Keycloak Admin REST endpoints under:

- `/admin/realms/{realm}/client-scopes`
- `/admin/realms/{realm}/client-scopes/{scopeId}/protocol-mappers/models`

