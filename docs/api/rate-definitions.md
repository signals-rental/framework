# Rate Definitions API

Endpoints for managing rate definitions, assigning rates to products, calculating charges, and reading rate-engine metadata.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/rate_definitions` | List rate definitions |
| GET | `/api/v1/rate_definitions/{id}` | Show a rate definition |
| POST | `/api/v1/rate_definitions` | Create a rate definition |
| PUT | `/api/v1/rate_definitions/{id}` | Update a rate definition |
| DELETE | `/api/v1/rate_definitions/{id}` | Delete a rate definition |
| POST | `/api/v1/rate_definitions/{id}/duplicate` | Duplicate a definition into an editable copy |

### Product Rates (nested)

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/products/{product}/rates` | List a product's rate assignments |
| GET | `/api/v1/products/{product}/rates/{id}` | Show a product rate |
| POST | `/api/v1/products/{product}/rates` | Assign a rate to a product |
| PUT | `/api/v1/products/{product}/rates/{id}` | Update a product rate |
| DELETE | `/api/v1/products/{product}/rates/{id}` | Remove a product rate |

### Calculation & Metadata

| Method | URL | Description |
|--------|-----|-------------|
| POST | `/api/v1/products/{product}/calculate_rate` | Calculate a rate breakdown for a rental window |
| GET | `/api/v1/rate_engine/strategies` | List calculation strategies |
| GET | `/api/v1/rate_engine/modifiers` | List modifiers |
| GET | `/api/v1/rate_engine/presets` | List shipped presets |
| GET | `/api/v1/rate_engine/schema` | Compose the config-form schema for a strategy |

## Authentication

Read endpoints require a Sanctum bearer token with the `rates:read` ability; write endpoints require `rates:write`. The `calculate_rate` endpoint accepts either `rates:read` or `products:read`, so product-scoped integrations can price without rate management access.

## List Rate Definitions

```
GET /api/v1/rate_definitions
```

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[name_cont]=daily` | Name contains |
| `q[calculation_strategy_eq]=period` | Filter by strategy (`period`, `fixed`, `hybrid`) |
| `q[base_period_eq]=daily` | Filter by base period |
| `q[is_preset_true]=1` | Presets only |
| `q[preset_slug_eq]=daily-rate` | Filter by preset slug |

### Includes

Eager-load relationships with `?include=clonedFrom,productRates`.

### Response

```json
{
    "rate_definitions": [
        {
            "id": 1,
            "name": "Daily Rate",
            "description": "A flat charge for each day of the rental.",
            "calculation_strategy": "period",
            "calculation_strategy_name": "Period-based",
            "base_period": "daily",
            "base_period_name": "Daily",
            "enabled_modifiers": [],
            "strategy_config": {},
            "modifier_configs": {},
            "is_preset": true,
            "preset_slug": "daily-rate",
            "cloned_from_id": null,
            "created_at": "2026-01-15T14:30:00Z",
            "updated_at": "2026-01-15T14:30:00Z"
        }
    ],
    "meta": {
        "total": 11,
        "per_page": 20,
        "page": 1
    }
}
```

## Create Rate Definition

```
POST /api/v1/rate_definitions
```

### Request Body

```json
{
    "name": "Daily with Tiers",
    "calculation_strategy": "period",
    "base_period": "daily",
    "description": "Daily rate with duration multipliers.",
    "enabled_modifiers": ["multiplier"],
    "strategy_config": { "day_type": "clock", "leeway_minutes": 60 },
    "modifier_configs": {
        "multiplier": { "tiers": [{ "multiplier": "1.0" }, { "multiplier": "0.9" }] }
    }
}
```

`strategy_config` and each entry in `modifier_configs` are validated against the composed schema for the chosen strategy and enabled modifiers. Hidden and disabled-modifier values are stripped before saving. Returns `201 Created` with the rate definition object.

## Duplicate Rate Definition

```
POST /api/v1/rate_definitions/{id}/duplicate
```

Creates an editable copy named "{name} (Copy)" with `is_preset` false, `preset_slug` null, and `cloned_from_id` set to the source. Returns `201 Created`.

## Product Rates

```
GET /api/v1/products/{product}/rates
POST /api/v1/products/{product}/rates
```

### Request Body

```json
{
    "rate_definition_id": 1,
    "transaction_type": "rental",
    "price": 5000,
    "currency": "GBP",
    "store_id": null,
    "valid_from": "2026-01-01",
    "valid_to": null,
    "priority": 0
}
```

> **Note:** `price` is sent in **minor units** (pence/cents) as an integer. Responses return it as a decimal string (`"50.00"`).

### Response

```json
{
    "product_rate": {
        "id": 7,
        "product_id": 1,
        "rate_definition_id": 1,
        "store_id": null,
        "transaction_type": "rental",
        "transaction_type_name": "Rental",
        "price": "50.00",
        "currency": "GBP",
        "valid_from": "2026-01-01",
        "valid_to": null,
        "priority": 0,
        "created_at": "2026-01-15T14:30:00Z",
        "updated_at": "2026-01-15T14:30:00Z"
    },
    "meta": {
        "overlapping_rate_ids": []
    }
}
```

`meta.overlapping_rate_ids` lists any existing rates that overlap the saved rate on store, transaction type, priority, and date window. Overlaps are permitted (resolved by priority at calculation time); the list is a non-blocking warning.

## Calculate Rate

```
POST /api/v1/products/{product}/calculate_rate
```

Resolves the highest-priority product rate for the given store, transaction type, and start date, then runs the rate engine over the rental window.

### Request Body

```json
{
    "quantity": 2,
    "start": "2026-02-01T09:00:00Z",
    "end": "2026-02-06T17:00:00Z",
    "transaction_type": "rental",
    "store_id": null
}
```

### Response

```json
{
    "rate_breakdown": {
        "currency": "GBP",
        "unit_price": "100.00",
        "units": 5,
        "unit_label": "days",
        "per_unit_subtotal": "500.00",
        "quantity": 2,
        "total": "1000.00",
        "line_items": [
            {
                "period_from": 1,
                "period_to": 5,
                "multiplier": "1.0",
                "unit_price": "100.00",
                "line_total": "500.00"
            }
        ],
        "applied_modifiers": []
    },
    "meta": {
        "resolved": true,
        "rate_definition_id": 1,
        "product_rate_id": 7
    }
}
```

> **Note:** When no product rate is configured, the response is a zero-priced breakdown spanning the period and `meta.resolved` is `false` — products carry no standalone price, so this is an explicit "no rate configured" signal rather than a misleading amount.

## Rate Engine Metadata

These read-only endpoints expose the same data the framework uses to render its rate-definition form, so external builders can reproduce it.

```
GET /api/v1/rate_engine/strategies
GET /api/v1/rate_engine/modifiers
GET /api/v1/rate_engine/presets
GET /api/v1/rate_engine/schema?strategy=period&modifiers[]=multiplier
```

`strategies` reports each strategy's `allowed_base_periods` and whether it supports each modifier. `schema` requires a `strategy` and optional `modifiers[]`, validates them against the registered set (422 on unknown values), and returns ordered form `sections` of fields.

## RMS Rate Engine Mapping

Signals presets mirror common industry-standard RMS engine types, so an imported product rate maps onto a preset of the same shape. Match the source RMS rate engine name to the preset slug below.

| Source RMS Rate Engine | Signals Preset Slug |
|-------------------------|---------------------|
| Daily Rate | `daily-rate` |
| Daily Multiplier and Factor Rate | `daily-multiplier-factor` |
| Hourly Rate | `hourly-rate` |
| Hourly Multiplier and Factor Rate | `hourly-multiplier-factor` |
| Half Hourly Rate | `half-hourly-rate` |
| Weekly Rate | `weekly-rate` |
| Monthly Rate | `monthly-rate` |
| Monthly Multiplier and Factor Rate | `monthly-multiplier-factor` |
| Fixed Rate | `fixed-rate` |
| Fixed Rate and Factor | `fixed-rate-factor` |
| Fixed Rate and Subs Days | `fixed-rate-subs-days` |
| Days Used Rate | `daily-rate` (fallback) |

> **Note:** Some RMS platforms offer a "Days Used Rate Engine" that depends on per-asset usage tracking, which is not implemented in this version. Such rates map to the **Daily Rate** preset as a documented fallback and can be revisited once usage data is available.
