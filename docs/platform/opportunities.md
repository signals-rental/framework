---
title: Opportunities
description: Quote, order, and fulfil rental jobs — the opportunity lifecycle, line-item editor, quote versioning, and state transitions.
---

## Overview

An **opportunity** is the central document of a rental job — it begins life as a quote and matures into a confirmed order that is allocated, dispatched, and returned. Every opportunity carries a customer, a store, dates, line items, costs, and a running set of totals, and records its full history as an event stream so nothing is ever lost.

Opportunities use a **two-axis model**: a high-level **state** (Draft → Quotation → Order) and a per-state **status** (for example Quotation/Reserved or Order/On Hire). The platform UI drives the same action classes the [Opportunities API](/docs/api/opportunities) uses, so the web and API paths stay in lock-step — validation, authorisation, audit logging, and `opportunity.*` webhooks apply identically wherever a change originates.

**Route:** `/opportunities`

Opportunities live under the **Job Planning** section of the main navigation, gated on the `opportunities.access` permission.

## The Lifecycle

An opportunity moves through three states, each with its own set of statuses:

| State | Statuses |
|-------|----------|
| **Draft** | Open |
| **Quotation** | Provisional, Reserved, Lost, Dead, Postponed |
| **Order** | Active, Dispatched, On Hire, Returned, Checked, Complete, Cancelled |

A new opportunity starts as a **Draft**. You build it up — add the customer, dates, and line items — then **convert it to a quotation** to send to the customer. Once accepted, you **convert it to an order**, at which point its equipment is committed and can be allocated, dispatched, and returned. The state and status are shown as badges in the page header throughout.

### State-transition actions

The transition controls live in the **split-button** beside the page header. Only the actions legal for the current state and status are offered (the page asks the same `available_actions` engine the API exposes, so an illegal move is never presented and a raced move surfaces a clear message rather than an error):

| Action | Moves | Notes |
|--------|-------|-------|
| **Convert to Quotation** | Draft → Quotation | Promotes a draft into a sendable quote. |
| **Convert to Order** | Quotation → Order | Commits the job. Gated by the store's [shortage policy](/docs/platform/shortages) — a short order may be blocked, warned, or allowed. |
| **Reinstate** | Lost / Dead / Postponed / Cancelled → re-opened | Brings a closed opportunity back to life. |
| **Revert to Quotation** | Order → Quotation | Only for an **open** order with **no dispatched assets** — an order that has equipment out cannot be reverted. |
| **Unlock Rates** | — | Releases the FX and tax locks (see [FX & tax locks](#fx-and-tax-locks)). |

Within a state you can also **change status** (for example mark a quotation Reserved or an order On Hire) without changing the overall state.

> **Note:** Allocation, dispatch, and return are driven from the order's line items and assets once it is an order — see the [Asset allocation & dispatch](#asset-allocation-and-dispatch) section below.

## Creating an Opportunity

**Route:** `/opportunities/create`

The create form captures the opportunity header:

| Field | Description |
|-------|-------------|
| Subject | A short title for the job (required). |
| Member | The customer the job is for (searchable picker). |
| Store | The store the job is booked against — drives availability and numbering. |
| Currency | The transaction currency; the exchange rate is snapshotted at creation. |
| Dates | The hire **start** and **end** dates. |
| Charge dates | The billable period, which may differ from the physical hire window. |
| Reference | A customer reference such as a PO number. |
| Tags | A free-text tag list. |
| Prices include tax | Whether entered prices are tax-inclusive (set at create time only). |
| Custom fields | Any custom fields defined for opportunities, grouped in the right-hand column. |

On save the form builds the same `CreateOpportunityData` DTO the API uses, runs it through the `CreateOpportunity` action, and redirects to the new opportunity's detail page. The opportunity number follows the format configured in **Opportunity Settings**.

## The Opportunity Detail Page

**Route:** `/opportunities/{id}`

The detail page presents the opportunity across tabs:

| Tab | Contents |
|-----|----------|
| Items | The line-item editor (see below). |
| Costs | Ad-hoc costs added to the job. |
| Versions | Quote revisions and alternatives. |
| Shortages | The shortage panel and resolvers. |
| Activities | The job's activity timeline. |
| Custom Fields | User-defined field values. |
| Files | Attached documents and images. |

The page header carries breadcrumbs, the state and status badges, an **Edit** button, and the split-button of transition actions. Availability badges on the page update live as bookings, returns, and stock movements occur elsewhere.

## The Line-Item Editor

The **Items** tab is the heart of an opportunity. It is an editable, grouped, sortable table of everything the customer is hiring or buying, with live totals.

### Adding products

Each add row carries a **two-tier product search**. As you type, an instant client-side index returns matches immediately; a debounced server search then fills in anything newer in the catalogue, with a **"new"** badge marking server-only hits. The server tier uses PostgreSQL trigram (`pg_trgm`) matching for fuzzy, typo-tolerant results.

A **quick-add bar** parses a quantity and a term together — typing `6 spiider` adds six of the matched product in one step. Keyboard navigation (up/down to move, Enter to add, Esc to dismiss) keeps the whole flow hands-on-keyboard.

### Grouping: custom sections and automatic nesting

Lines are organised into collapsible groups, with subtotals rolled up per group:

- **Custom sections** — you can create named sections (for example "Lighting" or "Day 1") and assign lines to them. Sections are managed per opportunity.
- **Automatic product grouping** — any line **not** assigned to a custom section is grouped automatically by its product group, following the product-group tree (parent group → the product's own group).

You can **reorder** lines by dragging the row handles (`wire:sort`), including dragging a line **between groups or sections** — dropping a line into a section assigns it there, and dropping it back into the automatic area clears the assignment.

### Accessories

Products that carry accessories show their accessories as **sub-rows** nested beneath the principal product line. Each accessory is a real, persisted line item (added via the same event-sourced `ItemAdded` path as any product line) that hangs under its principal and inherits the principal's quote-version scope. Accessory quantities **ripple** from the parent line's quantity by their configured ratio. Accessories are **priced through the rate engine** and **generate availability demand** in their own right — they are not free or presentational, though an accessory configured at a zero rate naturally totals to nil.

### Per-line edits

Each line has an edit action exposing:

- **Quantity** — changing it re-rolls the accessory ripple and recomputes totals.
- **Price override** — set a manual unit price for the line.
- **Discount** — apply a per-line discount.
- **Dates** — override the line's own charge dates where they differ from the header.
- **Optional** — flag a line as optional (quoted but not committed).
- **Substitute / Remove** — swap the line's product or remove the line entirely.

### Live totals

Every edit flows through the event-sourced line-item actions (add, change quantity, override price, set discount, toggle optional, substitute, remove), and the editor then re-reads the opportunity so the line, sub-group, group, and grand totals recompute live. Totals follow the framework's **ex-tax** model: line and group totals are net, and tax is computed finally, grouped by tax class, via the rate and tax engines.

### Status chips

Each line shows an availability **status chip** (available / reserved / booked-out) drawn from the [availability engine](/docs/platform/availability), so you can see at a glance whether the equipment on a line is free for the job's dates.

## Quote Versioning

The **Versions** tab lets you maintain multiple versions of a quote and pick the one the customer accepts. Two kinds of version exist:

- **Revisions** — a successive iteration of the quote; activating a revision supersedes its parent.
- **Alternatives** — a parallel option presented alongside others (for example a budget vs premium package).

Versions are shown as a **version tree**, with the active version marked and each node showing its type, status, total (net), author, and what it revises. The per-version actions mirror the version lifecycle and are only offered when legal:

| Action | Description |
|--------|-------------|
| **Create version** | Start a revision or alternative, optionally cloning an existing version's items. |
| **Activate** | Make a version the active one. Confirm-gated, because activating swaps the engine's reserved demand to the newly active version's lines. |
| **Send** | Mark a version as sent to the customer. |
| **Accept / Decline** | Record the customer's decision (decline captures a reason). |
| **Rename** | Change a version's label. |
| **Delete** | Remove a version (not allowed for the active version while others depend on it). |

Select any two versions to produce a **diff** — a table of added, removed, and changed lines with the net total delta between them.

When you convert to an order, the **accepted** version (or the active one if none is explicitly accepted) becomes the order, and the rest are superseded; only the confirmed version's demand carries into the order.

## FX and Tax Locks

When an opportunity is committed, its **exchange rate** and **tax** treatment are locked so later rate or tax-rule changes don't silently alter an agreed deal. The page header shows when locks are active, and a user with the `opportunities.unlock_rates` permission can release them with the **Unlock Rates** action — for example to re-rate an order after a deliberate FX or tax change. Releasing the locks lets subsequent edits pick up current rates again.

The locks freeze the **rate and tax treatment, not the line set**. You can still add, remove, or re-date lines on a committed order — the totals recompute, but always at the frozen exchange rate (and tax stays fixed while the tax lock is active). So a locked order's total can still change when its lines genuinely change; what the locks prevent is a later FX-rate or tax-rule change re-pricing the deal you already agreed.

## Asset Allocation & Dispatch

Once an opportunity is an **order**, its line items are allocated, dispatched, and returned:

- **Serialised** products (one physical unit per stock level) are allocated to specific assets, then booked out and checked back in. Batch actions let you allocate, prepare, book out, and check in many assets at once.
- **Bulk** products are dispatched and returned by quantity rather than by individual asset.

These movements drive the order's statuses (Active → Dispatched → On Hire → Returned → Checked → Complete) and feed the availability and shortage engines in real time.

## Related

- [Opportunities API](/docs/api/opportunities) — the REST endpoints behind this UI.
- [Equipment Availability](/docs/platform/availability) — the availability calendar and demand timeline.
- [Shortages](/docs/platform/shortages) — detecting and resolving inventory shortfalls.
