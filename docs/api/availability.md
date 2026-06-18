# Availability API

Read-only availability queries for the rental availability engine. Availability
is the quantity of a product that is free to book at a store over time:
`available = total_stock - total_demanded`, and may be negative when demand
exceeds stock (a shortage).

The endpoint serves a **two-tier read strategy**:

- A **point query** (single `date`) is computed on-the-fly from active demands —
  always live, no snapshot dependency.
- A **range query** (`from` + `to`) is read from pre-calculated availability
  snapshots, with a `calculated_at` freshness marker so consumers know how
  current the data is.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/availability` | Availability for a product at a store |
| GET | `/api/v1/products/{product}/availability` | Availability for a specific product |
| GET | `/api/v1/products/{product}/available-assets` | Serialised assets of a product free across a window |

## Authentication

Requires a Sanctum bearer token with the `availability:read` ability, and the
`availability.view` permission.

## Query Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `product_id` | Yes (flat endpoint only) | The product to query. Supplied by the route on the nested endpoint. |
| `store_id` | Yes | The store to query. |
| `date` | One of `date` or `from`+`to` | ISO date/time for a **point** query. |
| `from` | With `to` | Range start (ISO) for a **range** query. |
| `to` | With `from` | Range end (ISO) for a **range** query. |

`date` cannot be combined with `from`/`to`. Dates are aligned to the configured
availability resolution slot (daily, half-daily, or hourly) in the store's local
timezone.

## Point Query

```
GET /api/v1/availability?product_id=5&store_id=1&date=2026-07-02
```

### Response

```json
{
    "availability": {
        "product_id": 5,
        "store_id": 1,
        "date": "2026-07-02T00:00:00.000Z",
        "total_stock": 10,
        "total_demanded": 4,
        "available": 6,
        "demand_breakdown": {
            "opportunity_item": 4
        }
    }
}
```

`demand_breakdown` reports the demanded quantity per source type (e.g.
`opportunity_item`, `quarantine`).

## Range Query

```
GET /api/v1/availability?product_id=5&store_id=1&from=2026-07-01&to=2026-07-04
```

### Response

```json
{
    "availability": {
        "product_id": 5,
        "store_id": 1,
        "from": "2026-07-01T00:00:00.000Z",
        "to": "2026-07-04T00:00:00.000Z",
        "min_available": 6,
        "max_available": 10,
        "calculated_at": "2026-06-18T09:30:00.000Z",
        "slots": [
            {
                "slot_start": "2026-07-01T00:00:00.000Z",
                "total_stock": 10,
                "total_demanded": 4,
                "available": 6,
                "demand_breakdown": { "opportunity_item": 4 }
            }
        ]
    }
}
```

`slots` contains one entry per resolution-aligned time slot in the range.
`min_available` / `max_available` summarise the range; `calculated_at` is the
oldest snapshot recalculation time in the range (`null` when no snapshots exist
yet for the range).

## Available Assets (serialised products)

For products tracked as **serialised** (one physical unit per stock level), list
the specific assets that are free for an entire window — i.e. no active demand
claims that asset over a period overlapping `[from, to)`. A bulk product has no
discrete assets and returns an empty collection; use the point/range endpoints
above for quantity-based reads.

```
GET /api/v1/products/5/available-assets?store_id=1&from=2026-07-02&to=2026-07-04
```

### Query Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `store_id` | Yes | The store to query. |
| `from` | Yes | Window start (ISO). |
| `to` | Yes | Window end (ISO), on or after `from`. |

### Response

```json
{
    "available_assets": [
        {
            "id": 42,
            "item_name": "Shure SM58",
            "asset_number": "A-0042",
            "serial_number": "SN-000042",
            "barcode": null,
            "location": "Bay 3"
        }
    ],
    "meta": { "total": 1, "per_page": 1, "page": 1 }
}
```

The overlap is evaluated against the demand period: on PostgreSQL via the native
`tstzrange &&` operator (backed by the `idx_demands_asset_period` GiST index and
the `excl_demands_asset_period` exclusion constraint), and via a scalar
comparison on the SQLite test connection.

## Error Cases

| Status | Condition |
|--------|-----------|
| 401 | No valid Sanctum token |
| 403 | Token lacks `availability:read`, or user lacks `availability.view` |
| 422 | Missing `store_id`/`product_id`, no `date` or `from`+`to`, or `date` combined with `from`/`to` |
| 422 | Available-assets: missing `store_id`, `from`, or `to` |
