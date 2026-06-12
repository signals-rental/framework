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
| POST | `/api/v1/members/{id}/merge` | Merge a secondary member into this member |
| POST | `/api/v1/members/{id}/anonymise` | Erase a member's personally identifiable information |
| POST | `/api/v1/members/{id}/restore` | Restore a soft-deleted (archived) member |

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

## Merge Members

```
POST /api/v1/members/{id}/merge
```

Merges a secondary member into the primary member (identified by `{id}`). The secondary member's relationships, contact details, custom field values, and memberships are transferred to the primary; the secondary is then archived. Both members must share the same `membership_type`.

Requires `members:write` ability and `members.delete` permission.

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `secondary_id` | integer | Yes | ID of the member to merge into the primary |

### Response

Returns the updated primary member under the `member` key.

### Error Cases

| Status | Condition |
|--------|-----------|
| 422 | `secondary_id` is missing or does not reference an existing, non-archived member |
| 422 | `secondary_id` is the same as the primary member (self-merge) |
| 422 | The two members have different `membership_type` values |

## Anonymise Member

```
POST /api/v1/members/{id}/anonymise
```

Erases a member's personally identifiable information to fulfil data removal requests. Replaces the member's name and description with anonymised placeholders, removes the icon, and deletes all linked emails, phones, addresses, and links. This operation is **irreversible**.

Requires `members:write` ability and `members.delete` permission. A user cannot anonymise their own member record.

### Response

Returns the anonymised member under the `member` key.

### Error Cases

| Status | Condition |
|--------|-----------|
| 422 | The authenticated user is attempting to anonymise their own member record |

## Restore Member

```
POST /api/v1/members/{id}/restore
```

Restores a soft-deleted (archived) member, reversing a `DELETE`. The member is undeleted and its `is_active` flag is set back to `true`. The route resolves archived members, so `{id}` may reference a soft-deleted record. Restoring a member that is not archived is a no-op and still returns the member.

Requires `members:write` ability and `members.delete` permission.

### Response

Returns the restored member under the `member` key.

### Error Cases

| Status | Condition |
|--------|-----------|
| 404 | No member (archived or active) exists for the given `{id}` |

## Membership Types

| Type | Description |
|------|-------------|
| `organisation` | Companies, businesses |
| `contact` | Individual people |
| `venue` | Locations, event spaces |
| `user` | System users |
