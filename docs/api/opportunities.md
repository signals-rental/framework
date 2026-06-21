# Opportunities API

CRUD and lifecycle endpoints for opportunities — the event-sourced quote/order documents that drive the rental lifecycle.

Opportunities use a two-axis model: a **state** (Draft, Quotation, Order) and a per-state **status** (e.g. Quotation/Reserved, Order/On Hire). Reads hit a read-optimised projection; every write is recorded as an event so full history is preserved.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/opportunities` | List opportunities |
| GET | `/api/v1/opportunities/{id}` | Show an opportunity |
| GET | `/api/v1/opportunities/{id}/assets` | List every line's asset assignments (flat) |
| GET | `/api/v1/opportunities/{id}/availability` | Per-line availability picture |
| GET | `/api/v1/opportunities/{id}/activity` | Scoped audit timeline |
| GET | `/api/v1/opportunities/{id}/available_actions` | Legal actions for the current state |
| POST | `/api/v1/opportunities` | Create an opportunity (as a Draft) |
| PUT | `/api/v1/opportunities/{id}` | Update an opportunity's header fields |
| DELETE | `/api/v1/opportunities/{id}` | Delete (soft-delete) an opportunity |
| POST | `/api/v1/opportunities/{id}/restore` | Restore a soft-deleted opportunity |
| POST | `/api/v1/opportunities/{id}/clone` | Clone an opportunity into a new Draft |
| POST | `/api/v1/opportunities/{id}/convert_to_quotation` | Convert a Draft into a Quotation |
| POST | `/api/v1/opportunities/{id}/convert_to_order` | Convert a Quotation into an Order |
| POST | `/api/v1/opportunities/{id}/change_status` | Move to another status within the current state |
| POST | `/api/v1/opportunities/{id}/reinstate` | Reinstate a closed opportunity |
| POST | `/api/v1/opportunities/{id}/reopen` | Re-open a completed order |
| POST | `/api/v1/opportunities/{id}/revert_to_quotation` | Revert an Order back to a Quotation |
| POST | `/api/v1/opportunities/{id}/revert_to_draft` | Revert a Quotation back to a Draft |
| POST | `/api/v1/opportunities/{id}/unlock_locks` | Release FX/tax locks for re-pricing |
| POST | `/api/v1/opportunities/{id}/participants` | Add a participant |
| PATCH | `/api/v1/opportunities/{id}/participants/{participant}` | Update a participant's role or mute flag |
| DELETE | `/api/v1/opportunities/{id}/participants/{participant}` | Remove a participant |
| POST | `/api/v1/opportunities/{id}/items` | Add a line item |
| PATCH | `/api/v1/opportunities/{id}/items/{item}` | Update a line item |
| DELETE | `/api/v1/opportunities/{id}/items/{item}` | Remove a line item |
| PATCH | `/api/v1/opportunities/{id}/items/{item}/fulfilment` | Dispatch/return/adjust a bulk line |
| POST | `/api/v1/opportunities/{id}/quick_allocate` | Batch-allocate serialised assets |
| POST | `/api/v1/opportunities/{id}/quick_prepare` | Batch-prepare allocated assets |
| POST | `/api/v1/opportunities/{id}/quick_book_out` | Batch-dispatch serialised assets |
| POST | `/api/v1/opportunities/{id}/quick_check_in` | Batch-return serialised assets |
| POST | `/api/v1/opportunities/{id}/costs` | Add an ad-hoc cost |
| PATCH | `/api/v1/opportunities/{id}/costs/{cost}` | Update a cost |
| DELETE | `/api/v1/opportunities/{id}/costs/{cost}` | Remove a cost |
| POST | `/api/v1/opportunities/{id}/deal_price` | Set a manual deal-total override |
| DELETE | `/api/v1/opportunities/{id}/deal_price` | Clear the deal-total override |

## Authentication

Requires a Sanctum bearer token with `opportunities:read` (GET) or `opportunities:write` (POST/PUT/DELETE) ability, alongside the matching `opportunities.view` / `opportunities.create` / `opportunities.edit` / `opportunities.delete` permission.

## Opportunity Field Reference

All opportunity responses include the following fields under the `opportunity` key.

### Identity & Header

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Opportunity identifier |
| `subject` | string | Opportunity subject / title |
| `number` | string\|null | Zero-padded RMS number (e.g. `"0000000042"`) |
| `reference` | string\|null | External reference / PO number |
| `description` | string\|null | Internal description |
| `external_description` | string\|null | Customer-facing description |
| `member_id` | integer\|null | Owning member (customer) id |
| `venue_id` | integer\|null | Venue member id |
| `store_id` | integer\|null | Home store id |
| `owned_by` | integer\|null | Owner (staff member) id |
| `source_opportunity_id` | integer\|null | Source opportunity id when this was cloned from another |
| `rating` | integer\|null | Customer satisfaction rating (1–5) |
| `tag_list` | string[] | Tag labels attached to the opportunity |
| `custom_fields` | object | Custom field values as a flat key/value map (`{}` when none) |

### State & Status

| Field | Type | Description |
|-------|------|-------------|
| `state` | integer | RMS state integer: `0` Draft, `1` Quotation, `2` Order |
| `state_label` | string | Human-readable state label (e.g. `"Quotation"`) |
| `state_name` | string | Alias of `state_label` (RMS compatibility) |
| `status` | integer | Per-state status integer (see the Two-Axis table below) |
| `status_label` | string | Human-readable status label (e.g. `"Reserved"`) |
| `status_name` | string | Alias of `status_label` (RMS compatibility) |
| `availability_phase` | string | Demand phase implied by the current status (e.g. `"reserved"`, `"on_hire"`) |

### Dates

| Field | Type | Description |
|-------|------|-------------|
| `starts_at` | string\|null | Hire start (ISO 8601 UTC) |
| `ends_at` | string\|null | Hire end (ISO 8601 UTC) |
| `charge_starts_at` | string\|null | Chargeable period start |
| `charge_ends_at` | string\|null | Chargeable period end |
| `ordered_at` | string\|null | Timestamp when the opportunity was converted to an order |
| `quote_invalid_at` | string\|null | Date/time after which the quote expires |
| `prep_starts_at` / `prep_ends_at` | string\|null | Warehouse preparation window |
| `load_starts_at` / `load_ends_at` | string\|null | Loading window |
| `deliver_starts_at` / `deliver_ends_at` | string\|null | Delivery window |
| `setup_starts_at` / `setup_ends_at` | string\|null | Setup/rigging window |
| `show_starts_at` / `show_ends_at` | string\|null | Show/event window |
| `takedown_starts_at` / `takedown_ends_at` | string\|null | Takedown/de-rig window |
| `collect_starts_at` / `collect_ends_at` | string\|null | Collection window |
| `unload_starts_at` / `unload_ends_at` | string\|null | Unloading window |
| `deprep_starts_at` / `deprep_ends_at` | string\|null | De-preparation window |

### Fulfilment Flags

| Field | Type | Description |
|-------|------|-------------|
| `use_chargeable_days` | boolean | Whether chargeable days override the duration calculation |
| `chargeable_days` | string\|null | Override chargeable day count (decimal string) |
| `open_ended_rental` | boolean | Whether the hire has no fixed return date |
| `customer_collecting` | boolean | Customer collects from store (affects delivery demand) |
| `customer_returning` | boolean | Customer returns to store (affects return demand) |
| `invoiced` | boolean | Whether the opportunity has been invoiced |
| `has_shortage` | boolean | Whether any line item currently has a shortage |

### Delivery & Collection Addresses

| Field | Type | Description |
|-------|------|-------------|
| `delivery_address_id` | integer\|null | Member address id for delivery |
| `collection_address_id` | integer\|null | Member address id for collection |
| `delivery_instructions` | string\|null | Free-text delivery instructions |
| `collection_instructions` | string\|null | Free-text collection instructions |

Include `delivery_address` and `collection_address` via `?include=delivery_address,collection_address` to embed the full address object (street, city, postcode, country, etc.).

### Pricing & Totals

All money values are returned as decimal strings (e.g. `"125.50"`).

| Field | Type | Description |
|-------|------|-------------|
| `currency_code` | string\|null | ISO 4217 currency code (e.g. `"GBP"`) |
| `exchange_rate` | string | Exchange rate snapshot at creation or last unlock (decimal string) |
| `exchange_rate_locked` | boolean | Whether the exchange rate is locked |
| `tax_locked` | boolean | Whether the tax snapshot is locked |
| `pricing_locked` | boolean | Computed: `true` when either `exchange_rate_locked` or `tax_locked` is set |
| `prices_include_tax` | boolean | Whether prices are tax-inclusive |
| `charge_total` | string | Gross headline total (net when no deal override) |
| `deal_total` | string\|null | Manual deal-total override when set |
| `rental_charge_total` | string | Total for rental lines |
| `sale_charge_total` | string | Total for sale lines |
| `service_charge_total` | string | Total for service, labour, and surcharge lines |
| `sub_rental_charge_total` | string | Total for sub-rental lines |
| `transit_charge_total` | string | Total for delivery costs |
| `loss_damage_charge_total` | string | Total for loss/damage costs |
| `charge_excluding_tax_total` | string | Net total (all lines and costs, tax excluded) |
| `tax_total` | string | Total tax |
| `charge_including_tax_total` | string | Gross total including tax |

### Quote Version Summary

| Field | Type | Description |
|-------|------|-------------|
| `active_version_id` | integer | Id of the currently active quote version |
| `version_count` | integer | Total number of versions |
| `has_alternatives` | boolean | Whether there is more than one non-superseded version |

### Lazy Includes

These fields are `null` unless the relationship is eager-loaded via `?include=`:

| Include key | Field | Description |
|-------------|-------|-------------|
| `member` | `member` | `{id, name}` reference for the owning member |
| `venue` | `venue` | `{id, name}` reference for the venue member |
| `store` | `store` | `{id, name}` reference for the store |
| `owner` | `owner` | `{id, name}` reference for the owning user/member |
| `delivery_address` | `delivery_address` | Full address object |
| `collection_address` | `collection_address` | Full address object |
| `items` | `items` | Array of line item objects |
| `costs` | `costs` | Array of cost objects |
| `versions` | `versions` | Array of quote version objects |
| `participants` | `participants` | Array of participant objects |

### Response Meta Block

Every show/write response includes a top-level `meta` block alongside `opportunity`:

```json
{
    "opportunity": { ... },
    "meta": {
        "can_edit": true,
        "can_destroy": false
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `can_edit` | boolean | Whether the authenticated user can update this opportunity (respects policy + closed state) |
| `can_destroy` | boolean | Whether the authenticated user can delete (soft-delete) this opportunity |

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

Eager-load relationships with `?include=member,venue,store,owner,items,items.assets,costs,versions,versions.items`.

The opportunity's `custom_fields` object is populated from the `customFieldValues` relation, which is **eager-loaded by default** — `custom_fields` is always present (`{}` when none are set), with no need to request it explicitly.

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

The opportunity body also exposes the version summary fields `active_version_id`, `version_count`, and `has_alternatives`, the `tag_list` array, and (when included) a `versions` collection — so the Show page can sideload the quote versions in one call with `?include=versions,versions.items`.

## Opportunity Assets

```
GET /api/v1/opportunities/{id}/assets
```

A flat, paginated list of every per-asset assignment across **all** of the opportunity's line items (the `opportunity_item_assets` rows), returned under the `assets` key with a `meta` block. Use this for the Show page's assets tab without loading the full items payload. Each row matches the asset shape used elsewhere (`id`, `opportunity_item_id`, `stock_level_id`, `status` + `status_label`, lifecycle timestamps).

Requires `opportunities.view` / `opportunities:read`.

### Filters

Ransack-compatible `q[field_predicate]=value`:

| Parameter | Description |
|-----------|-------------|
| `q[status_eq]=2` | Filter by assignment status (0 Allocated … 5 Finalised) |
| `q[opportunity_item_id_eq]=10` | Filter to a single line |
| `q[stock_level_id_eq]=55` | Filter by assigned stock level |

### Sort

`sort=status`, `sort=-dispatched_at`, `sort=allocated_at`, `sort=created_at` (prefix with `-` for descending). Defaults to oldest-first.

## Opportunity Availability

```
GET /api/v1/opportunities/{id}/availability
```

Returns the per-line availability picture under the `availability` key: for each product-backed line, how many units are free over the line's own window at its own store with the line's **own** demand excluded, plus its shortage shortfall. Lines that reference no product (services, ad-hoc lines) are omitted. Computed live from demands (no snapshot dependency), so it always reflects current state.

Each entry: `opportunity_item_id`, `product_id`, `store_id`, `requested_quantity`, `available_for_item`, `shortage_quantity`, `has_shortage`, `from`, `to`.

Requires `opportunities.view` / `opportunities:read`.

## Opportunity Activity

```
GET /api/v1/opportunities/{id}/activity
```

A paginated, newest-first read of the audit trail (`action_logs`) scoped to this opportunity, returned under the `activity` key with a `meta` block. Saves the caller from knowing the underlying model FQCN that the global `/api/v1/actions` endpoint filters on. Each entry matches the action-log shape (`action`, `user_id`, `user_name`, `old_values`, `new_values`, `ip_address`, `created_at`).

Gated like the global action-log endpoint: requires `action-log.view` / `action-log:read`.

## Create Opportunity

```
POST /api/v1/opportunities
```

Creates the opportunity as a **Draft / Open**. A zero-padded RMS `number` (e.g. `"0000000042"`) is auto-generated from a per-store running sequence and returned in the response. Money is supplied as a decimal string or minor-unit integer and returned as a decimal string.

### Identity & Header

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `subject` | string | Yes | The opportunity subject (max 255) |
| `member_id` | integer | No | Owning member — must be an Organisation member (not archived) |
| `venue_id` | integer | No | Venue member |
| `owned_by` | integer | No | Owner member |
| `store_id` | integer | No | Store |
| `reference` | string | No | External reference / PO number (max 255) |
| `description` | string | No | Internal description |
| `external_description` | string | No | Customer-facing description |
| `currency` | string | No | ISO-4217 currency code (default `GBP`) |
| `prices_include_tax` | boolean | No | Whether entered prices are tax-inclusive (set at create time only) |
| `charge_total` | numeric | No | Header charge total (int = minor units, decimal string/float = major units) |
| `rating` | integer | No | Sales priority rating 0–5 |
| `tag_list` | string[] | No | Tag labels |
| `custom_fields` | object | No | Custom field values as a flat key/value map |

### Hire & Charge Dates

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `starts_at` | date | No | Hire start |
| `ends_at` | date | No | Hire end (on/after `starts_at`) |
| `charge_starts_at` | date | No | Chargeable period start |
| `charge_ends_at` | date | No | Chargeable period end (on/after `charge_starts_at`) |

### Event Logistics Dates

All fields are optional ISO 8601 date strings. Each phase is a `_starts_at` / `_ends_at` pair.

| Field pair | Description |
|------------|-------------|
| `prep_starts_at` / `prep_ends_at` | Warehouse preparation window |
| `load_starts_at` / `load_ends_at` | Loading window |
| `deliver_starts_at` / `deliver_ends_at` | Delivery window |
| `setup_starts_at` / `setup_ends_at` | Setup/rigging window |
| `show_starts_at` / `show_ends_at` | Show/event window |
| `takedown_starts_at` / `takedown_ends_at` | Takedown/de-rig window |
| `collect_starts_at` / `collect_ends_at` | Collection window |
| `unload_starts_at` / `unload_ends_at` | Unloading window |
| `deprep_starts_at` / `deprep_ends_at` | De-preparation window |
| `ordered_at` | Timestamp when the opportunity was converted to an order |
| `quote_invalid_at` | Date/time after which the quote expires |

### Fulfilment Flags

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `use_chargeable_days` | boolean | No | Whether `chargeable_days` overrides the duration calculation (default `false`) |
| `chargeable_days` | numeric string | No | Override chargeable day count |
| `open_ended_rental` | boolean | No | Whether the hire has no fixed return date (default `false`) |
| `customer_collecting` | boolean | No | Customer collects from store (default `false`) |
| `customer_returning` | boolean | No | Customer returns to store (default `false`) |

### Delivery & Collection

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `delivery_address_id` | integer | No | Member address id for delivery (must belong to `member_id`) |
| `collection_address_id` | integer | No | Member address id for collection (must belong to `member_id`) |
| `delivery_instructions` | string | No | Free-text delivery instructions |
| `collection_instructions` | string | No | Free-text collection instructions |

Returns `201` with the created opportunity under the `opportunity` key.

## Update Opportunity

```
PUT /api/v1/opportunities/{id}
```

Partial update of editable header fields. All fields are optional — omitted fields are left unchanged. A **closed/terminal** opportunity (Complete, Cancelled, Lost, Dead) cannot be edited and yields a `422`.

For nullable clearable fields (`venue_id`, `reference`, `description`, `external_description`, `delivery_instructions`, `collection_instructions`, `delivery_address_id`, `collection_address_id`, `chargeable_days`, `rating`, `tag_list`) an **explicit `null`** clears the value; **omitting the key entirely** leaves it unchanged.

### Identity & Header

| Field | Type | Description |
|-------|------|-------------|
| `subject` | string | Opportunity subject (max 255) |
| `member_id` | integer\|null | Owning member — must be an Organisation member |
| `venue_id` | integer\|null | Venue member (null clears) |
| `owned_by` | integer\|null | Owner member |
| `store_id` | integer\|null | Store |
| `reference` | string\|null | External reference / PO number (null clears) |
| `description` | string\|null | Internal description (null clears) |
| `external_description` | string\|null | Customer-facing description (null clears) |
| `rating` | integer\|null | Sales priority rating 0–5 (null clears) |
| `tag_list` | string[]\|null | Replacement tag list (null clears all tags) |
| `custom_fields` | object | Custom field values to update |

### Hire & Charge Dates

| Field | Type | Description |
|-------|------|-------------|
| `starts_at` | date\|null | Hire start |
| `ends_at` | date\|null | Hire end (on/after `starts_at`) |
| `charge_starts_at` | date\|null | Chargeable period start |
| `charge_ends_at` | date\|null | Chargeable period end |

### Event Logistics Dates

All fields are optional ISO 8601 date strings. Providing a value sets or moves the date; omitting the key leaves it unchanged. Dates in this group are **not clearable** via null — use the Create path or contact the store's team if a date must be removed.

| Field pair | Description |
|------------|-------------|
| `prep_starts_at` / `prep_ends_at` | Warehouse preparation window |
| `load_starts_at` / `load_ends_at` | Loading window |
| `deliver_starts_at` / `deliver_ends_at` | Delivery window |
| `setup_starts_at` / `setup_ends_at` | Setup/rigging window |
| `show_starts_at` / `show_ends_at` | Show/event window |
| `takedown_starts_at` / `takedown_ends_at` | Takedown/de-rig window |
| `collect_starts_at` / `collect_ends_at` | Collection window |
| `unload_starts_at` / `unload_ends_at` | Unloading window |
| `deprep_starts_at` / `deprep_ends_at` | De-preparation window |
| `ordered_at` | Timestamp when converted to an order |
| `quote_invalid_at` | Quote expiry date/time |

### Fulfilment Flags

| Field | Type | Description |
|-------|------|-------------|
| `use_chargeable_days` | boolean | Toggle chargeable-days override |
| `chargeable_days` | numeric string\|null | Override chargeable day count (null clears) |
| `open_ended_rental` | boolean | Toggle open-ended rental |
| `customer_collecting` | boolean | Toggle customer-collects flag |
| `customer_returning` | boolean | Toggle customer-returns flag |
| `invoiced` | boolean | Mark the opportunity as invoiced |

### Delivery & Collection

| Field | Type | Description |
|-------|------|-------------|
| `delivery_address_id` | integer\|null | Member address id for delivery (null clears) |
| `collection_address_id` | integer\|null | Member address id for collection (null clears) |
| `delivery_instructions` | string\|null | Free-text delivery instructions (null clears) |
| `collection_instructions` | string\|null | Free-text collection instructions (null clears) |

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

## Available Actions

```
GET /api/v1/opportunities/{id}/available_actions
```

Returns the set of legal lifecycle actions for the current state/status combination, resolving each action's feasibility given the current state, the actor's permissions, and system checks (shortage gate, lock state, dispatch state). The UI uses this to show/hide action buttons without needing to reproduce the guard logic.

Requires `opportunities.view` / `opportunities:read`.

### Response

```json
{
    "available_actions": [
        {
            "action": "convert_to_order",
            "label": "Convert to Order",
            "available": true,
            "disabled": false,
            "reason": null,
            "reason_code": null
        },
        {
            "action": "revert_to_draft",
            "label": "Revert to Draft",
            "available": false,
            "disabled": true,
            "reason": "Only an open, provisional quotation can be reverted to a draft.",
            "reason_code": "invalid_state"
        }
    ]
}
```

Each entry has `action` (the endpoint slug), `label`, `available` (boolean — the actor has permission and the state allows it), `disabled`, `reason` (human-readable explanation when not available), and `reason_code` (machine-readable, e.g. `invalid_state`, `nothing_to_unlock`, `dispatched`, `permission`).

## Restore Opportunity

```
POST /api/v1/opportunities/{id}/restore
```

Restores a soft-deleted opportunity. The `{id}` must identify a soft-deleted record (use `trashed=true` on the list or the Delete/Restore flow from the UI). Returns the restored opportunity under the `opportunity` key (`200 OK`).

Requires `opportunities.delete` / `opportunities:write`.

## Clone Opportunity

```
POST /api/v1/opportunities/{id}/clone
```

Creates a copy of the opportunity as a new **Draft**, including its line items, costs, and custom fields. The clone gets a fresh RMS number and is otherwise independent of the source; its `source_opportunity_id` points back to the original. Returns the new opportunity under the `opportunity` key (`201 Created`).

Requires `opportunities.create` / `opportunities:write`.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `subject` | string | No | Subject for the clone (defaults to `"Copy of [original subject]"`) |

## Reinstate

```
POST /api/v1/opportunities/{id}/reinstate
```

Reinstates a **Lost**, **Dead**, **Postponed**, or **Cancelled** opportunity back to its default open status (`Quotation/Provisional` or `Order/Active`). Returns the updated opportunity. A `422` is returned for any other state/status.

Requires `opportunities.edit` / `opportunities:write`.

## Revert to Quotation

```
POST /api/v1/opportunities/{id}/revert_to_quotation
```

Reverts an **open Order** (Active) back to a **Quotation / Provisional**. The opportunity must not have any dispatched assets — if assets have been dispatched, the transition is rejected (`422`) until they are returned and all quantity demand is unwound.

Requires `opportunities.edit` / `opportunities:write`.

## Revert to Draft

```
POST /api/v1/opportunities/{id}/revert_to_draft
```

Reverts an **open, provisional Quotation** back to a **Draft / Open**. Only allowed while the quotation is in the Provisional (open) status — a Reserved or closed quotation cannot be reverted. Returns the updated opportunity.

Requires `opportunities.edit` / `opportunities:write`.

## Reopen

```
POST /api/v1/opportunities/{id}/reopen
```

Re-opens a **Completed** order back to **Order / Active**. `Complete` is the only terminal order status that can be re-opened (Cancelled orders use `reinstate`). Returns the updated opportunity.

Requires `opportunities.edit` / `opportunities:write`.

## Unlock Locks

```
POST /api/v1/opportunities/{id}/unlock_locks
```

Releases any FX rate lock and/or tax lock currently in place on an order. Once unlocked, the next write (line edit, cost add, deal-price change) will re-snapshot the current exchange rates and tax rules. Returns the updated opportunity.

Requires the `opportunities.unlock_rates` permission and `opportunities:write` ability. Returns `422` when neither lock is set.

## Opportunity Participants

Participants are members (contacts or staff) attached to an opportunity in a named role — for example, a site contact, a crew chief, or an on-site supervisor. They are RMS-compatible (`participants[]` in the field set) and returned via `?include=participants`.

### List (via include)

```
GET /api/v1/opportunities/{id}?include=participants
```

Participants are returned in the `participants` array on the opportunity. Each entry:

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Participant record id |
| `opportunity_id` | integer | Parent opportunity id |
| `member_id` | integer | The member record attached |
| `role` | string\|null | Named role (free text, e.g. `"Site Contact"`) |
| `mute` | boolean | Whether notifications are suppressed for this participant |
| `created_at` / `updated_at` | string | ISO 8601 UTC timestamps |
| `member` | object\|null | `{id, name}` reference when `include=participants.member` is also requested |

### Add Participant

```
POST /api/v1/opportunities/{id}/participants
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `member_id` | integer | Yes | The member to attach (must exist) |
| `role` | string | No | Named role label |
| `mute` | boolean | No | Suppress notifications (default `false`) |

Returns the new participant under the `participant` key (`201 Created`).

Requires `opportunities.edit` / `opportunities:write`.

### Update Participant

```
PATCH /api/v1/opportunities/{id}/participants/{participant}
```

| Field | Type | Description |
|-------|------|-------------|
| `role` | string\|null | New role (set `null` to clear) |
| `mute` | boolean | Updated notification-mute flag |

Returns the updated participant under the `participant` key. The `{participant}` must belong to the opportunity (`404` otherwise).

### Remove Participant

```
DELETE /api/v1/opportunities/{id}/participants/{participant}
```

Removes the participant. Returns `204 No Content`. The `{participant}` must belong to the opportunity.

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

Each serialised line also reports its bulk-line fulfilment progress via `dispatched_quantity`
and `returned_quantity` (decimal strings) — the quantities physically booked out and
checked back in — so the UI can show dispatch progress without loading the asset rows.

A quantity reduction is rejected (`422`) when the new quantity would drop **below** the
number of serialised assets already allocated to the line, or below the line's
`dispatched_quantity`. Deallocate or return units first.

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

## Asset Allocation

Serialised line items track which specific physical assets (stock levels) are
committed to them. Each allocation pins one asset to a line, increments the stock
level's allocated quantity, and transitions the line's availability demand from a
single quantity-based demand to one asset-specific demand per allocated unit (the
quantity-based demand shrinks by the number allocated). Deallocation reverses this.

Allocation is permitted while the opportunity is open (a Reserved quotation or an
Order). The asset must belong to the line's product, be serialised, and be free for
the line's window — otherwise a `422` is returned. Allocations carry no body beyond
the asset id; the asset assignment is returned under the singular `asset` key.

### Allocate Asset

```
POST /api/v1/opportunities/{id}/items/{item}/assets
```

Body: `stock_level_id` (required). Fires `AssetAllocated`. Returns the new
assignment (`201 Created`).

### Update Asset

```
PATCH /api/v1/opportunities/{id}/items/{item}/assets/{asset}
```

Mutates an existing assignment. The `action` field (required) selects the
operation:

| `action` | Effect | Extra body |
|----------|--------|------------|
| `prepare` | Allocated → Prepared (picked/packed) | — |
| `revert` | Prepared → Allocated | — |
| `set_container` | Nest the asset inside a kit/case | `container_stock_level_id` |
| `clear_container` | Remove from its container | — |
| `substitute` | Swap the physical asset, preserving status | `new_stock_level_id`, optional `reason` |
| `dispatch` | Allocated/Prepared → Dispatched (**order only**) | optional `dispatched_by`, `vehicle_id`, `notes`, `dispatched_at` |
| `on_hire` | Dispatched → On Hire | — |
| `return` | Dispatched/On Hire → Checked In | optional `received_by`, `return_store_id`, `returned_at` |
| `check` | Checked In → Finalised (condition assessment) | `condition` (0=Good, 1=Damaged, 2=Missing), optional `checked_by`, `damage_notes`, `checked_at` |
| `revert_status` | Step the asset back to an earlier status | `revert_to`, optional `reason` |

An invalid status transition (e.g. preparing an already-prepared asset, dispatching
an unallocated asset, or dispatching on a quote rather than an order) yields a `422`.
Returns the updated assignment (`200 OK`).

The fulfilment actions auto-promote the parent opportunity's aggregate status — see
[Dispatch & Return](#dispatch--return).

### Deallocate Asset

```
DELETE /api/v1/opportunities/{id}/items/{item}/assets/{asset}
```

Optional body: `reason`. Fires `AssetDeallocated` — removes the assignment row,
decrements the stock level's allocated quantity, and reverts the freed unit to a
quantity-based demand. Allowed only while the asset is Allocated or Prepared;
otherwise `422`. Returns `204 No Content`.

### Quick Allocate (batch)

```
POST /api/v1/opportunities/{id}/quick_allocate
```

Body: `allocations` — a non-empty array of `{opportunity_item_id, stock_level_id}`
pairs. Every allocation fires inside a single atomic commit, so a failure on any one
(asset unavailable, wrong product, or allocating beyond the line's quantity) rolls
back the whole batch. All line items must belong to the opportunity. Returns the
opportunity with its items + assets (`200 OK`).

### Quick Prepare (batch)

```
POST /api/v1/opportunities/{id}/quick_prepare
```

Body: `asset_ids` — a non-empty array of asset-assignment ids, plus optional
`prepared_at`. Every asset is prepared (Allocated → Prepared) inside a single atomic
commit, so a failure on any one (an asset not in the Allocated status) rolls back the
whole batch. All assets must belong to the opportunity. Returns the opportunity with
its items + assets (`200 OK`).

## Dispatch & Return

Once an opportunity is an **order**, its line items move through the fulfilment
cycle — out of the warehouse on dispatch, back on return, then condition-checked.
Serialised lines track this per physical asset (via the asset `action`s above);
bulk (non-serialised) lines track it as aggregate quantities on the line.

### Aggregate auto-promotion

Every dispatch/return/check re-derives the opportunity's order sub-status from the
state of all its items (the "lowest common denominator") and, when it changes, fires
a distinct `OpportunityStatusPromoted` event — a real, audited status change with its
own timestamp, not a silent column write:

| Condition across all items | Order status |
|----------------------------|--------------|
| Nothing dispatched yet | Active |
| Some allocated assets still undispatched | Dispatched |
| Everything out, nothing returned | On Hire |
| Some returned but not yet checked | Returned |
| Everything dispatched is checked | Checked |

A dispatch before the line's planned start pulls that asset's availability demand
start back to the actual dispatch time; a return moves the demand end to the actual
return time (with turnaround applied off the real return) and releases availability
immediately on scan — there is no finalisation gate.

### Bulk-line fulfilment

```
PATCH /api/v1/opportunities/{id}/items/{item}/fulfilment
```

Dispatch, return, or adjust a non-serialised line. The `action` field (required)
selects the operation:

| `action` | Effect | Extra body |
|----------|--------|------------|
| `dispatch` | Record a (partial) dispatch (**order only**) | `quantity`, optional `dispatched_by` |
| `return` | Record a (partial) return | `quantity`, optional `received_by`, `condition` |
| `adjust` | Change the requested quantity mid-cycle | `new_quantity`, optional `reason` |

Partial dispatch/return is first-class: 60 m of a 100 m cable line can go out now and
40 m later. Over-dispatch (beyond the requested quantity), over-return (beyond what is
out), or adjusting below the dispatched quantity each yield a `422`. The line's
effective demand is `quantity - returned_quantity`. Returns the updated line (`200 OK`).

### Quick Book-Out (batch)

```
POST /api/v1/opportunities/{id}/quick_book_out
```

Body: `asset_ids` — a non-empty array of asset-assignment ids, plus optional
`dispatched_by`, `vehicle_id`, `dispatched_at`. Every asset is dispatched inside a
single atomic commit (a failure on any one rolls back the batch), and the order's
aggregate status promotes once consistently. All assets must belong to the
opportunity. Returns the opportunity with its items + assets (`200 OK`).

### Quick Check-In (batch)

```
POST /api/v1/opportunities/{id}/quick_check_in
```

Body: `asset_ids` (required array), optional `received_by`, `return_store_id`,
`returned_at`, and `finalise` (boolean). Each asset is returned in one atomic commit;
when `finalise` is true each return is immediately condition-checked (Good), clearing
the check-in queue in a single pass. Returns the opportunity with its items + assets
(`200 OK`).

## Opportunity Costs

Costs are ad-hoc charges that sit alongside the priced line items — delivery, crew
labour, surcharges, insurance, loss/damage recovery, etc. Unlike line items they are
**not** priced by the rate engine: each carries its own `amount` (per unit). Costs are
taxed exactly like line items (inclusive/exclusive, line-level rounding) and rolled into
the opportunity totals.

Each cost's net is routed into an RMS category bucket by `cost_type`:

| `cost_type` | Value | Total bucket |
|-------------|-------|--------------|
| Delivery | `0` | `transit_charge_total` |
| Labour | `1` | `service_charge_total` |
| Surcharge | `2` | `service_charge_total` |
| Insurance | `3` | `service_charge_total` |
| Loss / Damage | `4` | `loss_damage_charge_total` |
| Miscellaneous | `5` | `service_charge_total` |

Regardless of type, every non-optional cost also feeds `charge_excluding_tax_total`,
`tax_total`, `charge_including_tax_total`, and the headline `charge_total`. Optional
costs (`is_optional = true`) are excluded from all totals. Every cost endpoint returns
the **parent opportunity** (under the `opportunity` key) with its refreshed totals;
include `?include=costs` when reading to see the cost rows.

### Add Cost

```
POST /api/v1/opportunities/{id}/costs
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `description` | string | Yes | Cost description |
| `cost_type` | integer | No | `0` Delivery, `1` Labour, `2` Surcharge, `3` Insurance, `4` Loss/Damage, `5` Misc (default `5`) |
| `transaction_type` | integer | No | `0` Rental, `1` Sale, `2` Service, `3` Sub-rental (default `2` Service) |
| `amount` | money | No | Per-unit charge (int = minor units, decimal string/float = major units against `currency`) |
| `currency` | string | No | Currency scale for `amount` (default base currency) |
| `quantity` | numeric | No | Quantity (default `1`) |
| `is_optional` | boolean | No | Exclude from charge totals (default `false`) |
| `sort_order` | integer | No | Display ordering |
| `notes` | string | No | Free-form notes |

Returns the opportunity with refreshed totals under the `opportunity` key (`201 Created`).

### Update Cost

```
PATCH /api/v1/opportunities/{id}/costs/{cost}
```

Accepts any subset of the fields below; omitted fields are left untouched.

| Field | Type | Description |
|-------|------|-------------|
| `description` | string | New description |
| `cost_type` | integer | New cost type |
| `transaction_type` | integer | New transaction type |
| `amount` | money | New per-unit charge |
| `currency` | string | Currency scale for `amount` |
| `quantity` | numeric | New quantity |
| `is_optional` | boolean | Toggle whether the cost counts toward totals |
| `sort_order` | integer | Display ordering |
| `notes` | string | Free-form notes |

Returns the opportunity with refreshed totals.

### Remove Cost

```
DELETE /api/v1/opportunities/{id}/costs/{cost}
```

Removes the cost row and rolls the totals back down. Returns the opportunity with
refreshed totals (`200 OK`).

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

Rejected (`422`) on a **locked order** — like a line price override or discount, setting a
deal price changes the effective charge and is blocked while the order's FX/tax locks are
in place. Release the locks (`POST /api/v1/opportunities/{id}/unlock_locks`) first.

### Clear Deal Price

```
DELETE /api/v1/opportunities/{id}/deal_price
```

Clears the manual override, reverting `charge_total` to the engine-computed gross total.
Returns the opportunity. Also rejected (`422`) on a locked order.

## Quote Versions

A quotation can carry multiple **versions** — sequential **revisions** (each new
revision supersedes its parent) and parallel **alternatives** (concurrent options the
customer chooses between). Exactly one version is **active** at any time: the
opportunity's totals, line-item scope, and availability demand all follow the active
version. Versions are a sub-resource of an opportunity — there is no top-level
versions endpoint. They reuse the `opportunities:read` / `opportunities:write`
abilities and the `opportunities.view` / `opportunities.edit` permissions.

A version's `status` is one of `0` Draft, `1` Sent, `2` Accepted, `3` Declined,
`4` Superseded. Its `version_type` is `0` Revision or `1` Alternative. Money totals
are NET (tax-exclusive) decimal strings.

### List Versions

```
GET /api/v1/opportunities/{id}/versions
```

Returns the opportunity's versions (oldest first) in a `versions` collection. Add
`?include=items` to embed each version's line items.

### Show Version

```
GET /api/v1/opportunities/{id}/versions/{version}
```

Returns a single `version`. Add `?include=items` to embed its line items.

### Create Version

```
POST /api/v1/opportunities/{id}/versions
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `version_type` | int | No | `0` Revision (default), `1` Alternative |
| `label` | string | No | Display label for the version |
| `source_version_id` | int | No | Version whose items are cloned (defaults to the active version) |
| `notes` | string | No | Free-text notes |

Clones the source version's line items into the new version, which becomes active. A
revision supersedes its parent; an alternative coexists. Valid only while the
opportunity is a Quotation and within the `opportunities.max_versions` (20) and
`opportunities.max_alternatives` (5) caps. Returns the new `version` (`201 Created`).

### Activate Version

```
POST /api/v1/opportunities/{id}/versions/{version}/activate
```

Makes the version active. The opportunity's totals and item scope switch to it, and
availability demand swaps from the previously-active version's items to this one's.

### Send / Accept / Decline Version

```
POST /api/v1/opportunities/{id}/versions/{version}/send
POST /api/v1/opportunities/{id}/versions/{version}/accept
POST /api/v1/opportunities/{id}/versions/{version}/decline
```

Advance the version through its customer workflow. `send` (Draft → Sent) stamps
`sent_at`; `accept` (Draft/Sent → Accepted) stamps `accepted_at`; `decline`
(Draft/Sent → Declined) stamps `declined_at`. An **accepted** version takes priority
when the quotation is converted to an order (an accepted version is confirmed over the
active one, and every other version is superseded).

### Rename Version

```
PATCH /api/v1/opportunities/{id}/versions/{version}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `label` | string\|null | Yes | New label (null clears it) |

### Diff Versions

```
GET /api/v1/opportunities/{id}/versions/{from}/diff/{to}
```

Returns a `diff` of the item-level content delta between two versions of the same
opportunity: `added` (in target, not source), `removed` (in source, not target),
`changed` (quantity / unit price / discount differ), and `net_change` (signed total
delta). Lines are matched by product. Both versions must belong to the opportunity.

### Delete Version

```
DELETE /api/v1/opportunities/{id}/versions/{version}
```

Removes a version and its line items (`204 No Content`). The active version and the
only remaining version cannot be deleted, and deletion is allowed only while the
opportunity is a Quotation.

## Error Cases

| Status | Condition |
|--------|-----------|
| 401 | No valid Sanctum token |
| 403 | Token lacks `opportunities:write`, or the user lacks the `opportunities.edit` permission |
| 404 | Opportunity not found, or the line item / cost / version does not belong to the opportunity |
| 422 | Validation failure, a write against a closed/terminal opportunity, a version cap breach, or an invalid version transition (e.g. sending an already-sent version, deleting the active or only version) |
