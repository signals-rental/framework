# Product Groups API

CRUD endpoints for managing product groups (catalogue categories).

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/product_groups` | List product groups |
| GET | `/api/v1/product_groups/{id}` | Show a product group |
| POST | `/api/v1/product_groups` | Create a product group |
| PUT | `/api/v1/product_groups/{id}` | Update a product group |
| DELETE | `/api/v1/product_groups/{id}` | Delete a product group |

## Authentication

Requires a Sanctum bearer token with `products:read` (GET) or `products:write` (POST/PUT/DELETE) ability.

## List Product Groups

```
GET /api/v1/product_groups
```

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[name_cont]=lighting` | Name contains |
| `q[parent_id_eq]=2` | Filter by parent group |

### Sorts

| Parameter | Description |
|-----------|-------------|
| `sort=name` | Sort by name |
| `sort=sort_order` | Sort by sort order |

### Includes

Eager-load relationships with `?include=parent,children,customFieldValues`

### Response

```json
{
    "product_groups": [
        {
            "id": 1,
            "name": "Lighting",
            "description": "All lighting equipment",
            "parent_id": null,
            "sort_order": 0,
            "custom_fields": {},
            "created_at": "2026-01-15T14:30:00Z",
            "updated_at": "2026-01-15T14:30:00Z"
        }
    ],
    "meta": {
        "total": 12,
        "per_page": 20,
        "page": 1
    }
}
```

## Create Product Group

```
POST /api/v1/product_groups
```

```json
{
    "name": "Audio",
    "description": "Sound equipment",
    "parent_id": null,
    "sort_order": 1
}
```

Returns `201 Created`.

## Update / Delete

Standard update (PUT, 200) and delete (DELETE, 204) patterns.
