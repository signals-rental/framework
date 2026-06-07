---
title: Rate Definitions
description: Configure how products are priced over time using composable rate definitions, calculation strategies, and modifiers.
---

## Overview

A **rate definition** describes *how* a charge is calculated over a rental window — it does not hold a price itself. Prices live on **product rates**, which attach a rate definition (and an amount) to a product. This separation lets one definition such as "Daily Rate" drive pricing for thousands of products, each at its own price.

Every rate definition is composed from three parts:

| Part | Description |
|------|-------------|
| **Calculation strategy** | How elapsed time becomes chargeable units — period-based, fixed, or hybrid |
| **Base period** | The granularity of a chargeable unit (half-hourly, hourly, daily, weekly, monthly) |
| **Modifiers** | Optional adjustments applied after the base charge — duration multipliers and quantity factors |

Signals ships **11 presets** that replicate common industry-standard RMS engine types so migrating users see familiar names. Presets are just pre-filled, fully-editable configurations — there is no special preset behaviour beyond a starting point.

> **Note:** Rate definitions are managed in the **admin settings area**, not the main catalogue. They are configuration intended for a small number of administrators, not a daily tool. Assigning rates to individual products happens on the product detail page.

## Calculation Strategies

| Strategy | Behaviour | Base period |
|----------|-----------|-------------|
| **Period-based** | Charges the unit price for each chargeable unit in the rental window | Required (any) |
| **Fixed** | A single flat charge regardless of duration | None |
| **Hybrid** | A fixed charge for an initial number of units, then a per-unit charge thereafter | Required (daily, weekly, monthly) |

### Base Periods

A base period defines the length of one chargeable unit.

| Period | Unit length |
|--------|-------------|
| Half-Hourly | 30 minutes |
| Hourly | 60 minutes |
| Daily | 1 day |
| Weekly | 7 days |
| Monthly | 30 days |

> **Note:** Monthly is simplified to a fixed 30-day month in this version; calendar-month length is a planned refinement. Half-hourly and hourly periods are measured against the wall clock, while daily and longer periods are counted as whole calendar days.

## Modifiers

Modifiers adjust the base charge after units are counted. They are applied in priority order: multipliers first, then factors.

| Modifier | Configuration | Effect |
|----------|---------------|--------|
| **Multiplier** | A `tiers` table of decimal multipliers, one row per period position | Scales the unit price by tier as the rental lengthens. The last row inherits forward, so longer rentals keep the final tier's multiplier |
| **Factor** | A `ranges` table of `{from, to, factor}` rows | Scales the per-unit subtotal based on the order quantity. An open-ended final range (`to` = null) covers everything above |

Multiplier tiers and factor ranges are stored as decimal strings to preserve precision; the engine performs all intermediate arithmetic losslessly and rounds only at final assembly.

## Time Options

Period and hybrid strategies expose options that refine how elapsed time is counted:

| Option | Description |
|--------|-------------|
| **Day type** | Count elapsed clock time, or only minutes within configured business hours |
| **Business hours** | Daily start/end times used when day type is Business Hours |
| **Rental days per week** | Treats a "week" as this many chargeable days (e.g. a 5-day working week) |
| **Leeway minutes** | Grace period before an extra unit is charged |
| **First day cutoff** | A pickup later than this time still bills the first day in full |
| **Last day cutoff** | A return earlier than this time drops the final partial day |

## Presets

| Preset | Strategy | Base period | Modifiers |
|--------|----------|-------------|-----------|
| Daily Rate | Period-based | Daily | — |
| Daily Multiplier and Factor | Period-based | Daily | Multiplier, Factor |
| Hourly Rate | Period-based | Hourly | — |
| Hourly Multiplier and Factor | Period-based | Hourly | Multiplier, Factor |
| Half Hourly Rate | Period-based | Half-Hourly | — |
| Weekly Rate | Period-based | Weekly | — |
| Monthly Rate | Period-based | Monthly | — |
| Monthly Multiplier and Factor | Period-based | Monthly | Multiplier, Factor |
| Fixed Rate | Fixed | — | — |
| Fixed Rate and Factor | Fixed | — | Factor |
| Fixed Rate and Subs Days | Hybrid | Daily | — |

Presets are seeded on install. They can also be re-seeded from **Admin → Database Seeders**.

## Rate Definitions Admin

**Route:** `/admin/settings/rate-definitions`

Browse all rate definitions in a table that distinguishes presets from custom definitions and shows how many product rates use each one.

- **Presets** are read-only starting points and cannot be deleted.
- **Custom definitions** can be edited and deleted (deletion is blocked while product rates still reference them).

### Create / Edit

**Route:** `/admin/settings/rate-definitions/create` or `/admin/settings/rate-definitions/{id}/edit`

Creating a definition starts with a choice: pick a preset to pre-fill the form, or start **From Scratch**. The form is schema-driven — choosing a strategy constrains the available base periods, and enabling a modifier reveals its configuration table (tier or range rows you can add, remove, and reorder). Fields appear and hide based on other field values (for example, business-hours fields only show when day type is Business Hours).

### Duplicate

Any definition — including a preset — can be duplicated to produce an editable custom copy named "{name} (Copy)".

## Product Rates

**Route:** `/products/{id}/rates`

Each product has a **Rates** tab listing the rate assignments for that product. A product rate combines:

| Field | Description |
|-------|-------------|
| Rate definition | The calculation behaviour to apply |
| Transaction type | Rental, Sale, or Service |
| Unit price | The amount, in the rate's currency |
| Currency | ISO 4217 currency code |
| Store | Optional store scope (blank = all stores) |
| Valid from / to | Optional date window the rate applies within |
| Priority | Higher priority wins when multiple rates overlap |

Rates are added and edited on a dedicated form page (`/products/{id}/rates/create`); removal uses a confirmation modal. When a saved rate overlaps another rate of the same priority, type, and date window, a non-blocking warning banner is shown — overlapping rates are allowed and resolved by priority at calculation time.

## Permissions

Rate definitions and product rates are governed by the `rates.*` permission group: `rates.access`, `rates.view`, `rates.create`, `rates.edit`, and `rates.delete`. The admin area additionally requires admin access. The corresponding API abilities are `rates:read` and `rates:write`.
