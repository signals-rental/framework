# Shortages API

Detect inventory shortages on an opportunity and apply resolutions. A **shortage**
is the gap between what an opportunity line item needs and what the availability
engine reports as free for its product, store, and dates. Shortages are
**computed, never stored** — they are recalculated live on every request and
change as other bookings, returns, and stock movements occur. Only *resolutions*
and *acknowledgements* are persisted.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/opportunities/{opportunity}/shortages` | Detect current shortages on an opportunity |
| GET | `/api/v1/opportunities/{opportunity}/shortage_gate` | Pre-check the conversion gate (non-destructive) |
| GET | `/api/v1/opportunities/{opportunity}/shortage_resolutions` | List persisted resolutions for an opportunity |
| GET | `/api/v1/opportunities/{opportunity}/items/{item}/shortage_resolvers` | List resolver options for a line item's shortage |
| POST | `/api/v1/shortage_resolutions` | Apply a resolver option to a line item's shortage |
| PATCH | `/api/v1/shortage_resolutions/{resolution}/confirm` | Confirm a pending resolution |
| PATCH | `/api/v1/shortage_resolutions/{resolution}/start` | Start fulfilment of a confirmed resolution |
| PATCH | `/api/v1/shortage_resolutions/{resolution}/fulfill` | Mark a resolution fulfilled |
| PATCH | `/api/v1/shortage_resolutions/{resolution}/cancel` | Cancel a resolution |
| PATCH | `/api/v1/shortage_resolutions/{resolution}/fail` | Mark a resolution failed |
| POST | `/api/v1/opportunities/{opportunity}/shortages/acknowledge` | Acknowledge an opportunity's shortages |

## Authentication

Reads require a Sanctum bearer token with the `shortages:read` ability and the
`shortages.view` permission. Writes require `shortages:write` and the
`shortages.resolve` permission.

## Detect Shortages

```
GET /api/v1/opportunities/42/shortages
```

Returns one entry per short line item. `shortfall` is the gross gap
(`requested_quantity - available_quantity`); `remaining_shortfall` nets off any
active resolution records and is the figure resolvers act on.

### Response

```json
{
    "shortages": [
        {
            "opportunity_item_id": 1001,
            "opportunity_id": 42,
            "product_id": 5,
            "product_name": "MegaPointe Moving Head",
            "store_id": 1,
            "requested_quantity": 6,
            "available_quantity": 4,
            "shortfall": 2,
            "remaining_shortfall": 2,
            "tracking_type": "serialised",
            "starts_at": "2026-07-02T09:00:00.000Z",
            "ends_at": "2026-07-05T17:00:00.000Z",
            "is_critical": false
        }
    ],
    "meta": { "total": 1 }
}
```

`is_critical` is true when the opportunity is a confirmed order (not a quote).
`tracking_type` is `bulk` or `serialised`.

## List Resolver Options

```
GET /api/v1/opportunities/42/items/1001/shortage_resolvers
```

Each registered resolver applicable to the line's shortage contributes zero or
more concrete options. The built-in non-PO resolvers are `reallocate`,
`substitute`, `transfer`, `date_shift`, `partial`, and `waitlist`, ordered by
display priority.

### Response

```json
{
    "resolvers": [
        {
            "resolver_key": "partial",
            "name": "Partial fulfilment",
            "priority": 50,
            "auto_executable": true,
            "options": [
                {
                    "resolver_key": "partial",
                    "type": "partial",
                    "label": "Fulfil 4 of 6",
                    "description": "Reduce the line to the 4 available; 2 would remain unfulfilled.",
                    "quantity_resolved": 4,
                    "is_partial": true,
                    "auto_executable": true,
                    "estimated_cost": null,
                    "estimated_lead_time": null,
                    "requires_confirmation": false,
                    "metadata": []
                }
            ]
        }
    ]
}
```

## Shortage Gate Pre-Check

```
GET /api/v1/opportunities/42/shortage_gate
```

Returns a read-only, non-destructive pre-check of the **Block / Warn / Allow** shortage gate that would run when the quotation is converted to an order. This tells the UI whether the conversion would be blocked, warned, or allowed — and shows the current shortages — without committing anything, recording an acknowledgement, or triggering auto-resolution. The real gate runs (and may record an acknowledgement) when `POST /convert_to_order` is called.

### Response

```json
{
    "decision": "warn",
    "store_policy": "warn",
    "permission_used": false,
    "would_block": false,
    "acknowledgement_required": true,
    "shortages": [
        {
            "opportunity_item_id": 1001,
            "opportunity_id": 42,
            "product_id": 5,
            "product_name": "MegaPointe Moving Head",
            "store_id": 1,
            "requested_quantity": 6,
            "available_quantity": 4,
            "shortfall": 2,
            "remaining_shortfall": 2,
            "tracking_type": "serialised",
            "starts_at": "2026-07-02T09:00:00.000Z",
            "ends_at": "2026-07-05T17:00:00.000Z",
            "is_critical": false
        }
    ]
}
```

| Field | Type | Description |
|-------|------|-------------|
| `decision` | string | Gate decision: `allow`, `warn`, or `block` |
| `store_policy` | string | The store's base shortage policy (`allow`, `warn`, `block`) |
| `permission_used` | boolean | Whether the actor's `shortages.ignore` permission relaxed the policy |
| `would_block` | boolean | `true` when `decision` is `block` |
| `acknowledgement_required` | boolean | `true` when proceeding would record an acknowledgement |
| `shortages` | array | Array of current shortages (same shape as the detect endpoint) |

Requires `shortages.view` / `shortages:read`.

## List Shortage Resolutions

```
GET /api/v1/opportunities/42/shortage_resolutions
```

Returns the persisted shortage resolutions recorded against this opportunity's short line items, under the `shortage_resolutions` key. Supports `?q[field_predicate]=value` Ransack filtering (filterable on `status`, `resolver_key`, `resolution_type`) and `sort` / `page` / `per_page`.

### Response

```json
{
    "shortage_resolutions": [
        {
            "id": 7,
            "resolver_key": "partial",
            "resolution_type": "partial",
            "status": "confirmed",
            "status_label": "Confirmed",
            "quantity_resolved": 4,
            "cost": null,
            "metadata": null,
            "resolved_by": null,
            "confirmed_at": "2026-07-01T10:15:00.000Z",
            "cancelled_at": null,
            "cancellation_reason": null,
            "notes": "Reduced to 4 available units.",
            "created_at": "2026-07-01T10:15:00.000Z",
            "updated_at": "2026-07-01T10:15:00.000Z",
            "items": [
                { "id": 12, "opportunity_item_id": 1001, "quantity_allocated": 4 }
            ]
        }
    ],
    "meta": { "total": 1, "per_page": 20, "page": 1 }
}
```

Requires `shortages.view` / `shortages:read`.

## Apply a Resolution

```
POST /api/v1/shortage_resolutions
```

| Field | Required | Description |
|-------|----------|-------------|
| `opportunity_item_id` | Yes | The short line item to resolve. |
| `resolver_key` | Yes | The resolver to apply (e.g. `partial`, `waitlist`). |
| `option_index` | No | Which of the resolver's options to apply (default `0`). |
| `notes` | No | Free-text note stored on the resolution. |

Self-contained resolvers (`partial`, `date_shift`, `waitlist`) take effect
immediately and return a `confirmed` or `monitoring` resolution. Resolvers that
depend on a not-yet-built domain (`reallocate`, `substitute`, `transfer`) record
the resolution **intent** as `pending` and flag `requires_followup`.

### Response

```json
{
    "resolution": {
        "id": 7,
        "resolver_key": "partial",
        "resolution_type": "partial",
        "status": "confirmed",
        "status_label": "Confirmed",
        "quantity_resolved": 4,
        "cost": null
    },
    "status": "confirmed",
    "message": "Recorded partial fulfilment of 4 unit(s).",
    "requires_followup": false
}
```

## Resolution Lifecycle Transitions

Once a resolution is recorded its status can be advanced through the lifecycle matrix:

```
PATCH /api/v1/shortage_resolutions/{resolution}/confirm
PATCH /api/v1/shortage_resolutions/{resolution}/start
PATCH /api/v1/shortage_resolutions/{resolution}/fulfill
PATCH /api/v1/shortage_resolutions/{resolution}/cancel
PATCH /api/v1/shortage_resolutions/{resolution}/fail
```

### Status Transition Matrix

| Transition | Allowed from | Resulting status |
|------------|-------------|-----------------|
| `confirm` | `pending`, `monitoring` | `confirmed` |
| `start` | `confirmed` | `in_progress` |
| `fulfill` | `in_progress`, `partially_fulfilled` | `fulfilled` |
| `cancel` | `pending`, `monitoring`, `confirmed` | `cancelled` |
| `fail` | `pending`, `monitoring` | `failed` |

An invalid transition (e.g. starting an already-fulfilled resolution) yields a `422`. The `cancel` and `fail` transitions accept an optional `reason` body field.

### Response

```json
{
    "resolution": {
        "id": 7,
        "status": "confirmed",
        "status_label": "Confirmed"
    }
}
```

Requires `shortages.resolve` / `shortages:write`.

## Confirmation Gate

When a quotation is converted to an order
(`POST /api/v1/opportunities/{opportunity}/convert_to_order`), the store's
**shortage policy** is enforced:

| Policy | Behaviour |
|--------|-----------|
| `block` | Conversion is rejected (`422`) while unresolved shortages exist. |
| `warn` | Conversion proceeds, but an acknowledgement is recorded. |
| `allow` | Conversion proceeds; shortages remain visible but never gate it. |

The `shortages.ignore` permission (the spec's `can_ignore_shortages`) relaxes the
gate one level for the actor — `block` becomes `warn`, `warn` becomes `allow` —
so a senior operator can knowingly push an order through. The shortages stay
visible either way; only the hard block is removed.

> **Note:** Even with the override, proceeding past a warning records a
> `shortage_acknowledgement` with a frozen snapshot of the shortage state — the
> audit trail for who confirmed a short order and what they saw.

## Acknowledge Shortages

```
POST /api/v1/opportunities/42/shortages/acknowledge
```

Records a standalone acknowledgement ahead of conversion. Accepts an optional
`notes` field. Returns `422` when the opportunity has no shortages to
acknowledge.

### Response

```json
{
    "acknowledgement": {
        "id": 3,
        "opportunity_id": 42,
        "policy_at_time": "block",
        "permission_used": true,
        "acknowledged_at": "2026-07-01T10:15:00.000Z"
    }
}
```
