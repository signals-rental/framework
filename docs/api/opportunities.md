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
