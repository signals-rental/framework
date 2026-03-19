# Custom Views API

Endpoints for managing saved list configurations (columns, filters, sort).

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/custom_views` | List custom views |
| GET | `/api/v1/custom_views/{id}` | Show a custom view |
| POST | `/api/v1/custom_views` | Create a custom view |
| PUT | `/api/v1/custom_views/{id}` | Update a custom view |
| DELETE | `/api/v1/custom_views/{id}` | Delete a custom view |
| POST | `/api/v1/custom_views/{id}/clone` | Clone a custom view |

## Visibility Levels

| Level | Description |
|-------|-------------|
| `personal` | Visible only to the creator |
| `shared` | Visible to users with matching roles |
| `system` | Visible to all users (admin-created, cannot be deleted) |

## Using Views on List Endpoints

Pass `view_id` to any list endpoint to apply a saved view:

```
GET /api/v1/members?view_id=42
```

When a view is active:
- **Filters** from the view merge with explicit `q` params (explicit params take priority)
- **Sort** from the view applies unless an explicit `sort` param is given
- **Response** is sparse — only includes fields matching the view's columns plus `id`
- **Meta** includes `view.id` and `view.name`

## Create Custom View

```
POST /api/v1/custom_views
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | View name (max 255 chars) |
| `entity_type` | string | Yes | Entity type (e.g. "members") |
| `visibility` | string | No | "personal" (default) or "shared" |
| `columns` | array | Yes | List of column keys (min 1) |
| `filters` | array | No | Filter definitions |
| `sort_column` | string | No | Sort column key |
| `sort_direction` | string | No | "asc" (default) or "desc" |
| `per_page` | integer | No | Items per page (1-100, default 20) |
| `role_ids` | array | No | Role IDs for shared visibility |

### Filter Structure

Each filter is an object:

```json
{
  "field": "membership_type",
  "predicate": "eq",
  "value": "organisation",
  "logic": "and"
}
```

Available predicates: `eq`, `not_eq`, `cont`, `not_cont`, `gt`, `lt`, `gteq`, `lteq`, `true`, `false`, `null`, `not_null`, `in`, `not_in`, `start`, `end`, `present`, `blank`

Logic operators: `and` (default), `or`, `nand`, `nor`

### Column Validation

Column keys are validated against the entity's column registry. Invalid keys return 422. Custom field columns use the `cf.` prefix (e.g. `cf.po_reference`).
