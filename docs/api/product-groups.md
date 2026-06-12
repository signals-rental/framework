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
            "icon": {
                "url": "https://example.com/storage/groups/1/icon.jpg",
                "thumb_url": "https://example.com/storage/groups/1/icon_thumb.jpg"
            },
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

> **Note:** `icon` is `null` when no icon has been uploaded for the group. Icon files are managed through the platform UI; the API returns URLs only.

## Show Product Group

```
GET /api/v1/product_groups/{id}
```

### Response with includes

Request: `GET /api/v1/product_groups/3?include=parent,children`

```json
{
    "product_group": {
        "id": 3,
        "name": "Moving Heads",
        "description": "Automated moving-head fixtures",
        "parent_id": 1,
        "sort_order": 1,
        "icon": null,
        "custom_fields": {},
        "created_at": "2026-01-15T14:30:00Z",
        "updated_at": "2026-01-15T14:30:00Z",
        "parent": {
            "id": 1,
            "name": "Lighting"
        },
        "children": []
    }
}
```

`parent` is `null` when the group has no parent. `children` is an empty array `[]` when the group has no subgroups. Both fields are omitted entirely when the respective include is not requested.

### Response with `?include=parent`

When `parent` is included, the response adds:

```json
{
    "parent": {
        "id": 1,
        "name": "Lighting"
    }
}
```

### Response with `?include=children`

When `children` is included, the response adds:

```json
{
    "children": [
        { "id": 3, "name": "Moving Heads" },
        { "id": 4, "name": "LED Bars" }
    ]
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

> **Clearing nullable fields:** Omitting a field or sending `null` leaves it unchanged. To clear a nullable field to `null`, send an empty string `""` as its value.

> **Icons:** Icon uploads are not accepted on this endpoint. Icons are managed through the platform UI's `IconUpload` component and the stored URLs are returned read-only in API responses.
