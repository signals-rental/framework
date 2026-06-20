---
title: Shortages
description: Detect inventory shortfalls on an opportunity, gate conversion with Block/Warn/Allow, and resolve shortages through six resolvers.
---

## Overview

A **shortage** is the gap between what an opportunity's line items need and what the [availability engine](/docs/platform/availability) reports as free for those products, at that store, over those dates. The **Shortages** tab on an opportunity surfaces every short line, gates the conversion to an order according to your policy, and gives you a set of resolvers to close the gap.

Shortages are **computed, never stored** — they are recalculated live every time the tab is read, and change as other bookings, returns, stock movements, and resolutions happen. Only the *resolutions* and *acknowledgements* you record are persisted.

**Route:** `/opportunities/{id}/shortages`

Reading shortages requires the `shortages.view` permission; applying and transitioning resolutions requires `shortages.resolve`. Users without the resolve permission see the panel read-only.

## How Shortages Are Detected

For each line item, the engine compares the **requested quantity** against the **available quantity** for the product, store, and the line's dates. Where requested exceeds available, the line is short. The panel shows, per short line, the requested and available quantities, the **shortfall** (the gross gap), and the **remaining shortfall** (the gap after netting off any active resolutions — the figure the resolvers act on).

A shortage on a confirmed **order** is treated as **critical**; the same gap on a **quotation** is informational until you commit.

## The Confirmation Gate

When you **Convert to Order**, the store's **shortage policy** decides whether unresolved shortages may pass:

| Policy | Behaviour |
|--------|-----------|
| **Block** | Conversion is rejected while unresolved shortages exist. You must resolve or reduce them first. |
| **Warn** | Conversion proceeds, but an acknowledgement is recorded. |
| **Allow** | Conversion proceeds freely; shortages stay visible but never gate it. |

The Shortages tab shows the gate's current decision ahead of time, so you know before you click Convert to Order whether the job will be blocked, warned, or allowed.

### Overriding the gate

A user with the `shortages.ignore` permission relaxes the gate **one level** for themselves — **Block** becomes **Warn**, and **Warn** becomes **Allow** — so a senior operator can knowingly push a short order through. The shortages stay visible either way; only the hard block is lifted.

> **Note:** Proceeding past a warning records a **shortage acknowledgement** with a frozen snapshot of what the shortage looked like and who confirmed it — the audit trail for a short order. You can also acknowledge shortages explicitly from the tab ahead of conversion using the **Acknowledge** action, which captures an optional note.

## The Dispatch Policy

Alongside the conversion gate, each store carries a **dispatch policy** governing whether short equipment may be physically booked out at fulfilment time. The panel surfaces the store's dispatch policy so the operator dispatching the order knows whether a remaining shortfall will block the book-out.

## The Resolvers

For each short line, the panel lists the **resolvers** that apply, each offering one or more concrete options. Six built-in (non-purchase-order) resolvers ship with the platform, ordered by display priority:

| Resolver | When it applies / what it does |
|----------|--------------------------------|
| **Reallocate** | Pull free units of the same product from a competing, lower-priority booking. Surfaced when another booking is holding stock that could be reassigned. |
| **Substitute** | Fulfil the line with an equivalent alternative product. |
| **Transfer** | Bring stock in from another store that has it free. |
| **Date shift** | Move the line's dates to a window where the product is available. |
| **Partial** | Fulfil the available quantity now and leave the remainder unfulfilled (for example "fulfil 4 of 6"). |
| **Waitlist** | Record the shortfall and monitor availability, so the line can be filled if stock frees up. |

Some resolvers are **self-contained** and take effect immediately — Partial, Date shift, and Waitlist record a resolution straight away (`confirmed`, or `monitoring` for a waitlist). Others — Reallocate, Substitute, and Transfer — depend on a downstream action and record the resolution **intent** as pending, flagging that follow-up is required.

To resolve a line, pick a resolver option, optionally add a note, and apply it. The shortage is recomputed fresh as the resolution is applied, and the line's remaining shortfall drops accordingly.

## The Resolution Lifecycle

A persisted resolution moves through a defined lifecycle, and the panel only offers the transitions that are legal from a resolution's current state:

| Transition | Meaning |
|------------|---------|
| **Confirm** | Lock in a pending resolution as the agreed plan. |
| **Start** | Mark the resolution as in progress (for example the transfer is on its way). |
| **Fulfill** | Complete the resolution — the stock is now in hand. |
| **Cancel** | Abandon the resolution (captures an optional reason). |
| **Fail** | Record that the resolution could not be completed (captures an optional reason). |

Cancel and fail capture a reason in a short modal; the others are single-click. An illegal transition (one not permitted from the current state) is rejected with a clear message rather than an error, so a raced or stale click never breaks the panel.

## Live Updates

The Shortages tab is **live**. It subscribes to the opportunity's availability broadcast, so when the shortage picture changes — because of a booking, return, stock movement, or a resolution applied elsewhere — the panel re-reads the engine and refreshes the short lines, resolver options, gate decision, and resolution list without a manual reload.

## Related

- [Shortages API](/docs/api/shortages) — the REST endpoints for detecting and resolving shortages.
- [Opportunities](/docs/platform/opportunities) — where shortages arise and where Convert to Order is gated.
- [Equipment Availability](/docs/platform/availability) — spotting shortages across the wider plan.
