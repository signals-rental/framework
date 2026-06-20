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
| GET | `/api/v1/availability/calendar` | Multi-product daily availability grid for a store |
| GET | `/api/v1/availability/{product}/gantt` | Demand-bar Gantt for a single product at a store |
| GET | `/api/v1/availability/shortages` | Store-wide shortage sweep over a date range |
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

## Calendar Grid

```
GET /api/v1/availability/calendar?store_id=1&from=2026-07-01&to=2026-07-14
```

Returns the pre-calculated daily availability summary for **all** (or a filtered subset of) products at a store over a date range. Each product row contains one entry per day with the available quantity, a shortage flag, and the pending check-in count. This drives the Equipment Availability calendar view.

> **Note:** Kit and composed products whose availability is derived at read time (rather than from a demand snapshot) do not appear in the calendar grid. Use the point/range endpoints for those.

### Query Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `store_id` | Yes | The store to query. |
| `from` | Yes | Start date (ISO). |
| `to` | Yes | End date (ISO, on or after `from`). |
| `product_ids[]` | No | Array of product ids to narrow the result (omit for all products). |

### Response

```json
{
    "calendar": {
        "store_id": 1,
        "from": "2026-07-01T00:00:00+00:00",
        "to": "2026-07-14T00:00:00+00:00",
        "products": [
            {
                "product_id": 5,
                "product_name": "MegaPointe Moving Head",
                "days": [
                    {
                        "date": "2026-07-01",
                        "available": 6,
                        "has_shortage": false,
                        "pending_checkin": 0
                    },
                    {
                        "date": "2026-07-02",
                        "available": -2,
                        "has_shortage": true,
                        "pending_checkin": 3
                    }
                ]
            }
        ]
    }
}
```

## Gantt (demand bars for one product)

```
GET /api/v1/availability/{product}/gantt?store_id=1&from=2026-07-01&to=2026-07-14
```

Returns the individual demand bars for a single product at a store, decomposed into their prep / on-hire / turnaround zones. Shortage windows are surfaced as a separate `shortages` array. This drives the per-product Gantt timeline view.

### Query Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `store_id` | Yes | The store to query. |
| `from` | Yes | Start date (ISO). |
| `to` | Yes | End date (ISO, on or after `from`). |
| `asset_ids[]` | No | Array of serialised asset ids to narrow the result (omit for all assets). |

### Response

```json
{
    "gantt": {
        "product_id": 5,
        "store_id": 1,
        "from": "2026-07-01T00:00:00+00:00",
        "to": "2026-07-14T00:00:00+00:00",
        "total_stock": 10,
        "demands": [
            {
                "demand_id": 42,
                "asset_id": 7,
                "asset_serial": "SN-0007",
                "quantity": 1,
                "source_type": "opportunity_item",
                "source_id": 1001,
                "source_name": "Corporate Conference 2026",
                "colour": null,
                "phase": "reserved",
                "period_start": "2026-07-01T08:00:00Z",
                "buffer_before_end": "2026-07-01T09:00:00Z",
                "buffer_after_start": "2026-07-05T18:00:00Z",
                "period_end": "2026-07-06T09:00:00Z",
                "starts_at": "2026-07-01T09:00:00Z",
                "ends_at": "2026-07-05T18:00:00Z"
            }
        ],
        "shortages": [
            {
                "from": "2026-07-02T00:00:00Z",
                "to": "2026-07-04T00:00:00Z",
                "severity": 2,
                "in_buffer_zone": false
            }
        ]
    }
}
```

Each demand has:

| Field | Type | Description |
|-------|------|-------------|
| `demand_id` | integer | Demand record id |
| `asset_id` | integer\|null | Serialised asset id (null for bulk-quantity demands) |
| `asset_serial` | string\|null | Serial number of the asset |
| `quantity` | integer | Quantity demanded |
| `source_type` | string | Demand source type (e.g. `opportunity_item`) |
| `source_id` | integer | Source record id |
| `source_name` | string\|null | Display name of the source (opportunity subject) |
| `colour` | string\|null | Optional display colour hint |
| `phase` | string | Current demand phase (e.g. `reserved`, `on_hire`) |
| `period_start` | string | Earliest boundary including prep buffer |
| `buffer_before_end` | string | End of the prep/turnaround-before zone |
| `buffer_after_start` | string | Start of the post-return turnaround zone |
| `period_end` | string | Latest boundary including post-return buffer |
| `starts_at` | string | Hire start (the on-hire window start) |
| `ends_at` | string | Hire end (the on-hire window end) |

## Store-Wide Shortages

```
GET /api/v1/availability/shortages?store_id=1&from=2026-07-01&to=2026-07-14
```

Returns the pre-calculated shortage windows from the daily-summary read model — any day/product/store combination where `available < 0`. This drives the calendar shortage panel and dashboard shortage widget. Omit `store_id` to sweep all stores.

### Query Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `from` | Yes | Start date (ISO). |
| `to` | Yes | End date (ISO, on or after `from`). |
| `store_id` | No | Narrow to a single store. Omit to sweep all stores. |

### Response

```json
{
    "shortages": [
        {
            "product_id": 5,
            "product_name": "MegaPointe Moving Head",
            "store_id": 1,
            "date": "2026-07-02",
            "available": -2,
            "severity": 2,
            "calculated_at": "2026-06-18T09:30:00Z"
        }
    ],
    "meta": { "total": 1, "per_page": 50, "page": 1 }
}
```

`severity` is the absolute shortfall at its worst point on that day. `calculated_at` is the snapshot recalculation timestamp for that slot.

## Error Cases

| Status | Condition |
|--------|-----------|
| 401 | No valid Sanctum token |
| 403 | Token lacks `availability:read`, or user lacks `availability.view` |
| 422 | Missing `store_id`/`product_id`, no `date` or `from`+`to`, or `date` combined with `from`/`to` |
| 422 | Available-assets: missing `store_id`, `from`, or `to` |
| 422 | Calendar/Gantt/Shortages: missing `store_id` (where required), `from`, or `to` |
