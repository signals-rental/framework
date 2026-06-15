---
title: Tax Classes
description: REST endpoints for product tax classes, organisation tax classes, tax rates, and tax rules.
---

The tax API exposes the four reference-data resources that drive Signals' tax calculation engine: **product tax classes**, **organisation tax classes**, **tax rates**, and **tax rules**. Together they let an integrator read and manage the classifications and rate matrix that `TaxCalculator` uses to resolve the tax due on a line item.

## Authentication

All four resources require a Sanctum bearer token with the `tax-classes:read` ability for `GET` requests and the `tax-classes:write` ability for `POST`, `PUT`, and `DELETE` requests.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/product_tax_classes` | List product tax classes |
| GET | `/api/v1/product_tax_classes/{id}` | Show a product tax class |
| POST | `/api/v1/product_tax_classes` | Create a product tax class |
| PUT | `/api/v1/product_tax_classes/{id}` | Update a product tax class |
| DELETE | `/api/v1/product_tax_classes/{id}` | Delete a product tax class |
| GET | `/api/v1/organisation_tax_classes` | List organisation tax classes |
| GET | `/api/v1/organisation_tax_classes/{id}` | Show an organisation tax class |
| POST | `/api/v1/organisation_tax_classes` | Create an organisation tax class |
| PUT | `/api/v1/organisation_tax_classes/{id}` | Update an organisation tax class |
| DELETE | `/api/v1/organisation_tax_classes/{id}` | Delete an organisation tax class |
| GET | `/api/v1/tax_rates` | List tax rates |
| GET | `/api/v1/tax_rates/{id}` | Show a tax rate |
| POST | `/api/v1/tax_rates` | Create a tax rate |
| PUT | `/api/v1/tax_rates/{id}` | Update a tax rate |
| DELETE | `/api/v1/tax_rates/{id}` | Delete a tax rate |
| GET | `/api/v1/tax_rules` | List tax rules |
| GET | `/api/v1/tax_rules/{id}` | Show a tax rule |
| POST | `/api/v1/tax_rules` | Create a tax rule |
| PUT | `/api/v1/tax_rules/{id}` | Update a tax rule |
| DELETE | `/api/v1/tax_rules/{id}` | Delete a tax rule |

> **Note:** See the [Tax Classes platform page](/docs/platform/tax-classes) for how these resources combine to calculate tax.

## Product Tax Classes

Product tax classes categorise items by their tax treatment (e.g. "Standard", "Reduced", "Zero Rated", "Exempt").

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Identifier |
| `name` | string | Class name |
| `description` | string\|null | Optional description |
| `is_default` | boolean | Whether this is the default applied to new products |
| `created_at` | string | ISO 8601 UTC timestamp |
| `updated_at` | string | ISO 8601 UTC timestamp |

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[name_eq]=Standard` | Exact name match |
| `q[is_default_eq]=true` | Default class only |

Sortable fields: `name`, `created_at`.

### Response

```json
{
    "product_tax_classes": [
        {
            "id": 1,
            "name": "Standard",
            "description": "Standard-rated goods",
            "is_default": true,
            "created_at": "2026-01-15T14:30:00Z",
            "updated_at": "2026-01-15T14:30:00Z"
        }
    ],
    "meta": {
        "total": 2,
        "per_page": 20,
        "page": 1
    }
}
```

## Organisation Tax Classes

Organisation tax classes categorise members by their tax status (e.g. "Standard", "Tax Exempt", "Reverse Charge", "Charity"). They share the same field shape as product tax classes.

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[name_eq]=Standard` | Exact name match |
| `q[is_default_eq]=true` | Default class only |

Sortable fields: `name`, `created_at`.

### Response

```json
{
    "organisation_tax_class": {
        "id": 1,
        "name": "Standard",
        "description": null,
        "is_default": true,
        "created_at": "2026-01-15T14:30:00Z",
        "updated_at": "2026-01-15T14:30:00Z"
    }
}
```

## Tax Rates

A tax rate is a named percentage (e.g. "UK Standard" at 20%). Rates are referenced by tax rules.

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Identifier |
| `name` | string | Rate name |
| `description` | string\|null | Optional description |
| `rate` | string | Percentage as a decimal string (e.g. `"20.0000"`) |
| `is_active` | boolean | Whether the rate is available for use |
| `created_at` | string | ISO 8601 UTC timestamp |
| `updated_at` | string | ISO 8601 UTC timestamp |

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[name_eq]=UK Standard` | Exact name match |
| `q[is_active_eq]=true` | Active rates only |

Sortable fields: `name`, `rate`, `created_at`.

### Response

```json
{
    "tax_rate": {
        "id": 1,
        "name": "UK Standard",
        "description": "Standard VAT",
        "rate": "20.0000",
        "is_active": true,
        "created_at": "2026-01-15T14:30:00Z",
        "updated_at": "2026-01-15T14:30:00Z"
    }
}
```

## Tax Rules

A tax rule maps a combination of organisation tax class, product tax class, and tax rate, with a priority used to break ties when more than one rule matches. `TaxCalculator` resolves the highest-priority active rule for the relevant class pair.

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Identifier |
| `organisation_tax_class_id` | integer | Organisation tax class this rule applies to |
| `product_tax_class_id` | integer | Product tax class this rule applies to |
| `tax_rate_id` | integer | Tax rate applied when the rule matches |
| `priority` | integer | Resolution priority (higher wins) |
| `is_active` | boolean | Whether the rule participates in resolution |
| `created_at` | string | ISO 8601 UTC timestamp |
| `updated_at` | string | ISO 8601 UTC timestamp |

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[organisation_tax_class_id_eq]=1` | Filter by organisation tax class |
| `q[product_tax_class_id_eq]=2` | Filter by product tax class |
| `q[tax_rate_id_eq]=1` | Filter by tax rate |
| `q[is_active_eq]=true` | Active rules only |

Sortable fields: `priority`, `created_at`.

### Create Request Body

```json
{
    "organisation_tax_class_id": 1,
    "product_tax_class_id": 2,
    "tax_rate_id": 1,
    "priority": 100,
    "is_active": true
}
```

### Response

```json
{
    "tax_rule": {
        "id": 1,
        "organisation_tax_class_id": 1,
        "product_tax_class_id": 2,
        "tax_rate_id": 1,
        "priority": 100,
        "is_active": true,
        "created_at": "2026-01-15T14:30:00Z",
        "updated_at": "2026-01-15T14:30:00Z"
    }
}
```
