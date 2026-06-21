---
title: Job Planner
description: A Gantt and overlap timeline of quotes and orders across a configurable planning window, coloured by workflow status.
---

## Overview

The **Job Planner** is a visual timeline of all active quotes and orders plotted across a date window, so you can see at a glance when jobs run, how they overlap, and which customer is taking delivery or returning equipment. It is a planning and coordination view — it does not itself create or modify jobs; those are managed on the [Opportunities](/docs/platform/opportunities) pages.

**Route:** `/planner`

The planner lives under the **Job Planning** section of the main navigation and is gated on the `opportunities.access` permission.

## The Timeline

Each job appears as a **coloured bar** spanning its hire window. The bar colour reflects the opportunity's current workflow status so you can scan the whole plan and immediately tell quotations from live orders from completed jobs:

| Colour | Status |
|--------|--------|
| Grey | Draft / Open |
| Amber | Quotation / Provisional |
| Violet | Quotation / Reserved |
| Blue | Order / Active, Dispatched, or On Hire |
| Green | Order / Returned, Checked, or Complete |
| Red | Lost, Dead, or Cancelled |

A **colour key / legend** below the toolbar identifies each colour.

### Sub-bands

Where an opportunity carries RMS lifecycle dates, the bar is subdivided into up to three **sub-bands** that appear as lighter tinted zones within the bar:

- **Delivery** — the delivery window (`deliver_starts_at` to `deliver_ends_at`).
- **In Use** — the show/event window (`show_starts_at` to `show_ends_at`), or the full hire span when no show dates are set.
- **Collection** — the collection window (`collect_starts_at` to `collect_ends_at`).

Sub-bands make it easy to see not just when a job is on hire but when your crew is on site.

### Customer flags

Bars that have **Customer Collecting** or **Customer Returning** set carry small badges to distinguish self-service jobs from those requiring crew.

## View Modes

A **Layout** toggle in the toolbar switches between two render modes:

### Gantt

Each job occupies its own full-height row with a fixed left-hand label showing the opportunity number and member name. This is the clearest view for a moderate number of jobs: every row is legible and rows link to the opportunity detail page.

### Overlap

All jobs are packed into a single shared track. Concurrent jobs are stacked into horizontal **lanes** by the EventLaneAllocator, so jobs that genuinely run at the same time sit side-by-side rather than hiding each other. The bar label is embedded in the bar itself. This gives the most compact view for a busy week.

## Filters & Controls

The toolbar exposes the following controls; all settings are reflected in the URL so a planner link re-opens exactly as shared:

| Control | Effect |
|---------|--------|
| **Date** | The start date of the planning window. Defaults to today. |
| **View** | The window width: 1 Week (7 days), 2 Weeks (14 days), or 1 Month / Monthly (30 days). |
| **Store** | Filter to a single store's jobs ("All stores" shows every store). |
| **Include — Quotations** | Whether to show quotation-state jobs. |
| **Include — Orders** | Whether to show order-state jobs. |
| **Search** | Free-text filter across the job subject, opportunity number, and member name. |
| **Layout** | Gantt or Overlap render mode (see above). |

## Date Navigation

The header carries **previous / today / next** navigation buttons that shift the window backwards or forwards by one full period (a 2-week view steps two weeks at a time), so you can scroll the plan without resetting the filter state.

## Counts

Three badge counters above the planner show the total **Jobs**, **Orders**, and **Quotes** visible in the current window after filters are applied.

## Related

- [Opportunities](/docs/platform/opportunities) — create and manage the jobs shown on the planner.
- [Equipment Availability](/docs/platform/availability) — see equipment stock across the same window.
