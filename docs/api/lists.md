---
title: Lists
description: REST endpoints for configurable list names and their values, with an RMS-compatible alias.
---

The lists API exposes the configurable dropdown lists used across Signals — **list names** and their nested **list values**. It provides drop-in compatibility with industry-standard rental management systems through the `list_of_values` alias.

## Authentication

Every endpoint requires a Sanctum bearer token with the `static-data:read` ability for `GET` requests and the `static-data:write` ability for `POST`, `PUT`, and `DELETE` requests.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/list_names` | List all list names |
| GET | `/api/v1/list_names/{id}` | Show a list name |
| POST | `/api/v1/list_names` | Create a list name |
| PUT | `/api/v1/list_names/{id}` | Update a list name |
| DELETE | `/api/v1/list_names/{id}` | Delete a list name |
| GET | `/api/v1/list_names/{list_name}/list_values` | List values for a list |
| POST | `/api/v1/list_names/{list_name}/list_values` | Create a list value |
| PUT | `/api/v1/list_names/{list_name}/list_values/{id}` | Update a list value |
| DELETE | `/api/v1/list_names/{list_name}/list_values/{id}` | Delete a list value |

### RMS-Compatible Alias

The same controllers are mounted under `list_of_values` for compatibility with industry-standard rental management systems:

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/list_of_values` | List all list names |
| GET | `/api/v1/list_of_values/{id}` | Show a list name |
| GET | `/api/v1/list_of_values/{list_of_values}/list_values` | List values for a list |

The alias accepts the same write verbs as the canonical routes.

## List Names

A list name groups a set of values. Built-in (system) lists are seeded on installation and cannot be deleted.

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Identifier |
| `name` | string | List name, e.g. `"Activity Type"` |
| `description` | string\|null | What the list is used for |
| `is_system` | boolean | Whether the list is built-in |
| `is_hierarchical` | boolean | Whether values can have parents |
| `created_at` | string | ISO 8601 UTC timestamp |
| `updated_at` | string | ISO 8601 UTC timestamp |
| `values` | array\|null | Included when the relationship is loaded |

> **Note:** Built-in list names are stored with spaces, e.g. `"Activity Type"`, `"Address Type"`. Query by the exact stored name.

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[name_eq]=Activity Type` | Exact name match |
| `q[is_system_eq]=true` | System lists only |

Sortable fields: `name`, `created_at`.

### Response

```json
{
    "list_names": [
        {
            "id": 1,
            "name": "Activity Type",
            "description": "Activity classification",
            "is_system": true,
            "is_hierarchical": false,
            "created_at": "2026-01-15T14:30:00Z",
            "updated_at": "2026-01-15T14:30:00Z"
        }
    ],
    "meta": {
        "total": 5,
        "per_page": 20,
        "page": 1
    }
}
```

## List Values

List values are the individual options within a list. They are managed as a nested resource under a list name.

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Identifier |
| `list_name_id` | integer | Parent list name |
| `name` | string | Display text |
| `parent_id` | integer\|null | Parent value (hierarchical lists) |
| `sort_order` | integer | Display position |
| `is_system` | boolean | Whether the value is built-in |
| `is_active` | boolean | Whether the value appears in dropdowns |
| `metadata` | object\|null | Arbitrary JSON metadata (e.g. an icon key) |
| `created_at` | string | ISO 8601 UTC timestamp |
| `updated_at` | string | ISO 8601 UTC timestamp |

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[is_active_eq]=true` | Active values only |
| `q[name_eq]=Task` | Exact name match |
| `q[parent_id_eq]=3` | Filter by parent value |
| `q[is_system_eq]=true` | System values only |

Sortable fields: `name`, `sort_order`, `created_at`, `updated_at`.

### Response

```json
{
    "list_values": [
        {
            "id": 10,
            "list_name_id": 1,
            "name": "Task",
            "parent_id": null,
            "sort_order": 0,
            "is_system": true,
            "is_active": true,
            "metadata": { "icon": "task" },
            "created_at": "2026-01-15T14:30:00Z",
            "updated_at": "2026-01-15T14:30:00Z"
        }
    ],
    "meta": {
        "total": 7,
        "per_page": 20,
        "page": 1
    }
}
```

Fetching only the active values for a list is the common case:

```
GET /api/v1/list_names/1/list_values?q[is_active_eq]=true
```
