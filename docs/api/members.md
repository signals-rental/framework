# Members API

CRUD endpoints for managing members (contacts, organisations, venues, and users).

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/members` | List members |
| GET | `/api/v1/members/{id}` | Show a member |
| POST | `/api/v1/members` | Create a member |
| PUT | `/api/v1/members/{id}` | Update a member |
| DELETE | `/api/v1/members/{id}` | Delete (soft-delete) a member |

### Nested Resources

| Method | URL | Description |
|--------|-----|-------------|
| GET/POST/PUT/DELETE | `/api/v1/members/{id}/addresses` | Member addresses |
| GET/POST/PUT/DELETE | `/api/v1/members/{id}/emails` | Member emails |
| GET/POST/PUT/DELETE | `/api/v1/members/{id}/phones` | Member phones |
| GET/POST/PUT/DELETE | `/api/v1/members/{id}/links` | Member links |
| GET/POST/DELETE | `/api/v1/members/{id}/relationships` | Member relationships |
| GET | `/api/v1/members/{id}/attachments` | Member attachments |

## Authentication

Requires a Sanctum bearer token with `members:read` (GET) or `members:write` (POST/PUT/DELETE) ability.

## List Members

```
GET /api/v1/members
```

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[name_cont]=acme` | Name contains |
| `q[membership_type_eq]=organisation` | Filter by type |
| `q[is_active_eq]=true` | Filter by active status |
| `q[cf.field_name_eq]=value` | Filter by custom field |

### Includes

Eager-load relationships with `?include=addresses,emails,phones,links,contacts,organisations`

### Custom Views

Apply a saved view with `?view_id=42`. See [Custom Views API](/docs/api/custom-views) for details.

### Sort

`sort=name`, `sort=membership_type`, `sort=created_at`, `sort=updated_at`

## Membership Types

| Type | Description |
|------|-------------|
| `organisation` | Companies, businesses |
| `contact` | Individual people |
| `venue` | Locations, event spaces |
| `user` | System users |
