# Opportunities API

CRUD and lifecycle endpoints for opportunities — the event-sourced quote/order documents that drive the rental lifecycle.

Opportunities use a two-axis model: a **state** (Draft, Quotation, Order) and a per-state **status** (e.g. Quotation/Reserved, Order/On Hire). Reads hit a read-optimised projection; every write is recorded as an event so full history is preserved.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/opportunities` | List opportunities |
| GET | `/api/v1/opportunities/{id}` | Show an opportunity |
| POST | `/api/v1/opportunities` | Create an opportunity (as a Draft) |
| PUT | `/api/v1/opportunities/{id}` | Update an opportunity's header fields |
| DELETE | `/api/v1/opportunities/{id}` | Delete (soft-delete) an opportunity |
| POST | `/api/v1/opportunities/{id}/convert_to_quotation` | Convert a Draft into a Quotation |
| POST | `/api/v1/opportunities/{id}/convert_to_order` | Convert a Quotation into an Order |
| POST | `/api/v1/opportunities/{id}/change_status` | Move to another status within the current state |

## Authentication

Requires a Sanctum bearer token with `opportunities:read` (GET) or `opportunities:write` (POST/PUT/DELETE) ability, alongside the matching `opportunities.view` / `opportunities.create` / `opportunities.edit` / `opportunities.delete` permission.

## Two-Axis State Model

| State | `state` | Statuses (`status`) |
|-------|---------|---------------------|
| Draft | `0` | `0` Open |
| Quotation | `1` | `0` Provisional, `1` Reserved, `2` Lost, `3` Dead, `4` Postponed |
| Order | `2` | `0` Active, `1` Dispatched, `2` On Hire, `3` Returned, `4` Checked, `5` Complete, `6` Cancelled |

Each response includes both the raw RMS integers (`state`, `status`) and human-readable labels (`state_label`, `status_label`), plus the `availability_phase` the current status places on the availability engine.

## List Opportunities

```
GET /api/v1/opportunities
```

Returns opportunities under the `opportunities` key with a `meta` block (`total`, `per_page`, `page`).

### Filters

Ransack-compatible `q[field_predicate]=value` parameters:

| Parameter | Description |
|-----------|-------------|
| `q[subject_cont]=stage` | Subject contains |
| `q[state_eq]=1` | Filter by state (0 Draft, 1 Quotation, 2 Order) |
| `q[status_eq]=0` | Filter by per-state status |
| `q[member_id_eq]=42` | Filter by member |
| `q[store_id_eq]=3` | Filter by store |
| `q[reference_eq]=PO-123` | Filter by reference |
| `q[invoiced_eq]=false` | Filter by invoiced flag |
| `q[starts_at_gteq]=2026-01-01` | Starts on or after |
| `q[cf.field_name_eq]=value` | Filter by custom field |

### Includes

Eager-load relationships with `?include=member,venue,store,owner`.

### Custom Views

Apply a saved view with `?view_id=42`. The view's columns project a sparse response (only those fields plus `id`); custom-field columns (`cf.*`) filter the `custom_fields` object. Explicit `q` filters take priority over the view's filters. See [Custom Views API](/docs/api/custom-views) for details.

### Sort

`sort=created_at`, `sort=-starts_at`, `sort=subject`, `sort=charge_total`, `sort=state`, `sort=status` (prefix with `-` for descending).

### Pagination

Offset-based: `?page=2&per_page=20` (max `per_page` 100).

## Show Opportunity

```
GET /api/v1/opportunities/{id}
```

Returns the opportunity under the `opportunity` key. Supports `?include=`.

## Create Opportunity

```
POST /api/v1/opportunities
```

Creates the opportunity as a **Draft / Open**. Money is supplied as a decimal string or minor-unit integer and returned as a decimal string.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `subject` | string | Yes | The opportunity subject (max 255) |
| `member_id` | integer | No | Owning member (must exist, not archived) |
| `venue_id` | integer | No | Venue member |
| `owned_by` | integer | No | Owner member |
| `store_id` | integer | No | Store |
| `reference` | string | No | External reference / PO number |
| `description` | string | No | Internal description |
| `external_description` | string | No | Customer-facing description |
| `starts_at` | date | No | Hire start |
| `ends_at` | date | No | Hire end (on/after `starts_at`) |
| `currency` | string | No | ISO-4217 code (default `GBP`) |
| `charge_total` | numeric | No | Header charge total |

Returns `201` with the created opportunity under the `opportunity` key.

## Update Opportunity

```
PUT /api/v1/opportunities/{id}
```

Partial update of editable header fields (same fields as create, all optional). A **closed/terminal** opportunity (Complete, Cancelled, Lost, Dead) cannot be edited and yields a `422`.

## Delete Opportunity

```
DELETE /api/v1/opportunities/{id}
```

Soft-deletes the opportunity. The deletion is recorded as an event so history is preserved; the row drops out of list and availability reads. Returns `204`.

## Convert to Quotation

```
POST /api/v1/opportunities/{id}/convert_to_quotation
```

Transitions a **Draft** opportunity to a **Quotation / Provisional**. Returns the updated opportunity under the `opportunity` key. An invalid transition (not currently a Draft) yields a `422`.

## Convert to Order

```
POST /api/v1/opportunities/{id}/convert_to_order
```

Transitions a **Quotation** opportunity to an **Order / Active**. Returns the updated opportunity. An invalid transition yields a `422`.

## Change Status

```
POST /api/v1/opportunities/{id}/change_status
```

Moves the opportunity to a different status **within its current state**.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `status` | integer | Yes | The per-state status integer (see the table above) |

Returns the updated opportunity under the `opportunity` key.

### Error Cases

| Status | Condition |
|--------|-----------|
| 401 | No valid Sanctum token |
| 403 | Token lacks `opportunities:read`/`opportunities:write`, or the user lacks the permission |
| 404 | Opportunity not found (or soft-deleted) |
| 422 | Validation failure, or an invalid state/status transition |

## Opportunity Items

Line items are the priced rows of an opportunity. Every write below flows through the
event-sourced lifecycle: the rate engine resolves the per-unit price and duration-aware
subtotal, the line discount is applied to the **net** (before tax), and the tax engine
computes line-level tax. The opportunity's totals (`charge_excluding_tax_total`,
`tax_total`, `charge_including_tax_total`, the per-type `rental`/`sale`/`service`
totals, and the headline `charge_total`) are recomputed and rolled up automatically.

Every item endpoint returns the **parent opportunity** (under the `opportunity` key)
with its refreshed totals — include `?include=items` (or `items.assets`) when reading
to see the line rows themselves.

Optional lines (`is_optional = true`) still claim availability but are **excluded** from
all charge totals.

### Add Line Item

```
POST /api/v1/opportunities/{id}/items
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Display name of the line |
| `item_id` | integer | No | Catalogue item id (product) the line refers to |
| `item_type` | string | No | Polymorphic type (`Product` / FQCN) — pairs with `item_id` |
| `description` | string | No | Line description |
| `quantity` | numeric | No | Quantity (default `1`) |
| `transaction_type` | integer | No | `0` Rental, `1` Sale, `2` Service, `3` Sub-rental |
| `charge_period` | integer | No | `0` Hour, `1` Day, `2` Week, `3` Month, `4` Fixed |
| `starts_at` / `ends_at` | datetime | No | Per-item hire window (inherits the opportunity's dates when null) |
| `is_optional` | boolean | No | Exclude from charge totals (default `false`) |
| `unit_price` | money | No | Manual unit-price override (int = minor units, decimal string/float = major units against `currency`). Omit to price from the rate engine |
| `currency` | string | No | Currency scale for `unit_price` (default base currency) |
| `discount_percent` | numeric | No | Line discount percentage (applied before tax) |
| `sort_order` | integer | No | Display ordering |
| `notes` | string | No | Free-form notes |
| `custom_fields` | object | No | Inline line-item custom-field map |

Returns the opportunity with refreshed totals under the `opportunity` key (`201 Created`).

### Update Line Item

```
PATCH /api/v1/opportunities/{id}/items/{item}
```

Accepts any subset of the fields below; each provided field dispatches its own
lifecycle event in turn (quantity → price → discount → dates → optional → substitution).

| Field | Type | Description |
|-------|------|-------------|
| `quantity` | numeric | New quantity (resyncs availability demand) |
| `unit_price` | money | Manual unit-price override; send `null` to clear it and revert to rate pricing |
| `currency` | string | Currency scale for `unit_price` |
| `discount_percent` | numeric | Line discount percentage; send `null` to clear |
| `starts_at` / `ends_at` | datetime | Per-item hire window (resyncs availability demand) |
| `is_optional` | boolean | Toggle whether the line counts toward totals |
| `item_id` / `item_type` | integer / string | Substitute the catalogue reference (re-prices, resyncs demand) |
| `name` | string | New display name (with a substitution) |

Returns the opportunity with refreshed totals.

### Remove Line Item

```
DELETE /api/v1/opportunities/{id}/items/{item}
```

Releases the line's availability demand, removes the row, and rolls the totals back
down. Returns the opportunity with refreshed totals (`200 OK`).

### Set Deal Price

```
POST /api/v1/opportunities/{id}/deal_price
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `deal_total` | money | Yes | Manual deal total (int = minor units, decimal string/float = major units) |
| `currency` | string | No | Currency scale for `deal_total` |

Replaces the engine-computed headline `charge_total` with the manual override. The
per-type and tax totals continue to reflect the line items. Returns the opportunity.

### Clear Deal Price

```
DELETE /api/v1/opportunities/{id}/deal_price
```

Clears the manual override, reverting `charge_total` to the engine-computed gross total.
Returns the opportunity.

### Error Cases

| Status | Condition |
|--------|-----------|
| 401 | No valid Sanctum token |
| 403 | Token lacks `opportunities:write`, or the user lacks the `opportunities.edit` permission |
| 404 | Opportunity not found, or the line item does not belong to the opportunity |
| 422 | Validation failure, or a write against a closed/terminal opportunity |
