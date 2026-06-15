---
title: Custom Fields
description: REST endpoints for managing custom field definitions and the groups that organise them.
---

The custom fields API manages the **field definitions** and **field groups** that extend Signals entities with extra data. These endpoints configure the schema; the values themselves are read and written on each entity (for example, a member's custom field values appear inline in the [Members API](/docs/api/members) response and are queryable with `?q[cf.field_name_eq]=value`).

## Authentication

All endpoints require a Sanctum bearer token with the `custom-fields:read` ability for `GET` requests and the `custom-fields:write` ability for `POST`, `PUT`, and `DELETE` requests.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/custom_fields` | List custom field definitions |
| GET | `/api/v1/custom_fields/{id}` | Show a custom field |
| POST | `/api/v1/custom_fields` | Create a custom field |
| PUT | `/api/v1/custom_fields/{id}` | Update a custom field |
| DELETE | `/api/v1/custom_fields/{id}` | Delete a custom field |
| GET | `/api/v1/custom_field_groups` | List custom field groups |
| GET | `/api/v1/custom_field_groups/{id}` | Show a group |
| POST | `/api/v1/custom_field_groups` | Create a group |
| PUT | `/api/v1/custom_field_groups/{id}` | Update a group |
| DELETE | `/api/v1/custom_field_groups/{id}` | Delete a group |

> **Note:** See the [Custom Fields platform page](/docs/platform/custom-fields) for the full list of field types and the entities that support custom fields.

## Custom Fields

A custom field definition declares a field on one entity type (its `module_type`).

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Identifier |
| `name` | string | Internal identifier (lowercase, underscores) |
| `display_name` | string\|null | Human-readable label |
| `description` | string\|null | Help text |
| `module_type` | string | Entity this field applies to (e.g. `Member`, `Product`, `ProductGroup`, `StockLevel`, `Activity`, `Store`) |
| `field_type` | integer | Field-type code |
| `custom_field_group_id` | integer\|null | Group this field belongs to |
| `list_name_id` | integer\|null | Source list for Select/MultiSelect types |
| `sort_order` | integer | Display position within its group |
| `is_required` | boolean | Whether a value must be provided |
| `is_searchable` | boolean | Whether the field is included in search |
| `settings` | object\|null | Type-specific settings (JSONB) |
| `validation_rules` | object\|null | Validation constraints (JSONB) |
| `visibility_rules` | object\|null | Conditional visibility rules (JSONB) |
| `default_value` | string\|null | Pre-populated value for new records |
| `plugin_name` | string\|null | Owning plugin, if any |
| `document_layout_name` | string\|null | Associated document layout, if any |
| `is_active` | boolean | Whether the field is currently in use |
| `created_at` | string | ISO 8601 UTC timestamp |
| `updated_at` | string | ISO 8601 UTC timestamp |

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[name_eq]=po_reference` | Exact name match |
| `q[module_type_eq]=Member` | Fields for one entity type |
| `q[field_type_eq]=1` | Filter by field-type code |
| `q[is_active_eq]=true` | Active fields only |
| `q[is_required_eq]=true` | Required fields only |

Sortable fields: `name`, `module_type`, `sort_order`, `created_at`.

### Create Request Body

```json
{
    "name": "po_reference",
    "display_name": "PO Reference",
    "module_type": "Member",
    "field_type": 1,
    "custom_field_group_id": 2,
    "is_required": false,
    "is_searchable": true
}
```

### Response

```json
{
    "custom_field": {
        "id": 5,
        "name": "po_reference",
        "display_name": "PO Reference",
        "description": null,
        "module_type": "Member",
        "field_type": 1,
        "custom_field_group_id": 2,
        "list_name_id": null,
        "sort_order": 0,
        "is_required": false,
        "is_searchable": true,
        "settings": null,
        "validation_rules": null,
        "visibility_rules": null,
        "default_value": null,
        "plugin_name": null,
        "document_layout_name": null,
        "is_active": true,
        "created_at": "2026-01-15T14:30:00Z",
        "updated_at": "2026-01-15T14:30:00Z"
    }
}
```

## Custom Field Groups

Groups organise related custom fields for display.

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Identifier |
| `name` | string | Group name |
| `description` | string\|null | Optional description |
| `sort_order` | integer | Display position |
| `plugin_name` | string\|null | Owning plugin, if any |
| `created_at` | string | ISO 8601 UTC timestamp |
| `updated_at` | string | ISO 8601 UTC timestamp |
| `custom_fields` | array\|null | Included when the relationship is loaded |

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[name_eq]=Logistics` | Exact name match |

Sortable fields: `name`, `sort_order`, `created_at`.

### Response

```json
{
    "custom_field_groups": [
        {
            "id": 2,
            "name": "Logistics",
            "description": "Delivery and handling fields",
            "sort_order": 0,
            "plugin_name": null,
            "created_at": "2026-01-15T14:30:00Z",
            "updated_at": "2026-01-15T14:30:00Z"
        }
    ],
    "meta": {
        "total": 3,
        "per_page": 20,
        "page": 1
    }
}
```
