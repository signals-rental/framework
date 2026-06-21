---
title: Equipment Availability
description: Read equipment availability over time with a by-product-group grid, a products-by-days calendar, and a per-product demand-bar Gantt timeline.
---

## Overview

The **Equipment Availability** page shows, at a glance, how much of each product is free to book at a store over a span of dates. It is a planning view over the rental [availability engine](/docs/api/availability): availability is the quantity of a product that is free at a store at a given time, where `available = total stock − total demanded`, and may go **negative** when demand exceeds stock — a shortage.

**Route:** `/availability`

The page lives under the **Job Planning** section of the navigation (the "Equipment Availability" link) and is gated on the `availability.view` permission. It is read-only — it reflects bookings made on opportunities elsewhere; it does not itself create demand.

Three view modes are available, switched from the top-right toolbar: **By Group** (the default), **Calendar**, and **Timeline** (Gantt). All state — store, dates, view, and filters — is persisted in the URL so a link reopens exactly as shared.

## By Product Group

The **By Group** view is the default landing view. It shows a **products-by-days grid scoped to a single product group**, making it the fastest way to check whether your lighting stock, audio stock, or any other category has room in a window.

### Controls

| Control | Effect |
|---------|--------|
| **Product Group** | The group whose products fill the rows. Required — select a group to populate the grid. |
| **Store** | The store to read availability for. |
| **Product type** | Filter rows to Rental or Sale products, or show all. |
| **Start date** | The first day of the window. |
| **Days** | The window length: 7, 14, 28, 30, or 60 days. Default 30. |
| **Shortages only** | Hide rows with no shortage cell in the window. |
| **Warnings only** | Hide rows with no warning (low-but-positive) cell in the window. |
| **Include quotes** | Whether provisional (quotation-stage) demand counts towards held. |

### Reading the grid

Each row is a product in the group; each column is a day. Cells are colour-coded by availability state:

| Colour | Meaning |
|--------|---------|
| Green | Available — comfortably in stock for this day. |
| Amber | Warning — low but still positive (2 units or fewer remaining). |
| Red | Shortage — demand exceeds stock (`available ≤ 0` with a shortage). |
| Cyan | Booked — every unit is committed but nothing is oversold (available = 0, no shortage). |

Each cell shows **`available (held)`** — the free quantity and, in parentheses, how much of the total stock is committed. Weekend columns are shaded to help read multi-week windows at a glance.

Clicking a product name opens its **per-product timeline** (Gantt view) so you can drill from a shortage cell directly into the demand breakdown.

Rows are paginated (20 per page) for large groups.

## Choosing What to Read (Calendar & Timeline)

When switching to the Calendar or Timeline view, the toolbar changes to a date-range and product-filter form:

| Control | Effect |
|---------|--------|
| **Store** | The store to read availability for. |
| **From / To** | The date range. Defaults to today through four weeks out. |
| **Products** | An optional filter to narrow the calendar grid to specific products. |

The previous/next buttons step the range by one week.

## Calendar View

The calendar is a grid of **products (rows) against days (columns)**. Each cell shows the available quantity for that product on that day, colour-weighted so tight and negative days stand out. This is the fastest way to scan a catalogue for a window and spot where stock is committed.

The calendar reads a **pre-calculated daily-summary** of availability, so it stays fast across large catalogues and long ranges.

> **Note:** Kit and composed products do not hold their own daily summary rows — their availability is derived at read time from their components. They therefore do not appear in the calendar grid; query a kit's components directly, or use the [availability API](/docs/api/availability) for a live read.

## Gantt View

The Gantt view focuses on a **single product** and plots its demand as bars on a timeline. Each booking is decomposed into its zones so you can see not just *when* a unit is on hire but the surrounding preparation and turnaround time that also makes it unavailable:

- **Prep** — the lead time before the hire starts.
- **On hire** — the billable hire window itself.
- **Turnaround** — the post-rent unavailability after return (cleaning, testing, buffer).

Reading the demand bars makes it obvious why a product can be unavailable on a day even though no hire physically spans it — the prep or turnaround window overlaps it.

## Shortages on the Calendar

Where committed demand exceeds stock, the availability dips below zero. The page surfaces a **shortage count** for the range so you can see immediately that the current view contains shortfalls. Shortages are detected and resolved per opportunity on the [Shortages panel](/docs/platform/shortages); the availability page is where you spot them across the wider plan.

## Live Updates

The page is **live**. It subscribes to the store's availability broadcast (and, in Gantt view, the selected product's channel), so when a booking, return, resolution, or stock movement changes the picture, the grid or timeline re-reads and updates without a manual refresh. The broadcast is only a signal — the page always re-reads the authoritative figures from the read model rather than trusting the pushed payload.

## The Resolution Setting

Availability is bucketed into time slots whose granularity is set by the **availability resolution** setting — **daily**, **half-daily**, or **hourly**. The resolution determines how finely the calendar and the underlying snapshots divide time, and dates are aligned to the slot boundary in the store's local timezone. Daily resolution suits most dry-hire operations; finer resolutions suit same-day turnarounds where intra-day timing matters.

## Related

- [Availability API](/docs/api/availability) — point and range availability reads, and the available-assets endpoint.
- [Opportunities](/docs/platform/opportunities) — where demand is created.
- [Shortages](/docs/platform/shortages) — detecting and resolving shortfalls.
