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
| GET | `/api/v1/opportunities/{opportunity}/items/{item}/shortage_resolvers` | List resolver options for a line item's shortage |
| POST | `/api/v1/shortage_resolutions` | Apply a resolver option to a line item's shortage |
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
