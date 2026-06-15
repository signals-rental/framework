---
title: Stock Transactions
description: The append-only ledger that records every stock movement and keeps quantity held accurate.
---

## Overview

Stock transactions are the **append-only ledger** behind every stock level. Each transaction records a single stock movement — a purchase, a sale, a write-off, a transfer, and so on — and adjusts the parent stock level's quantity held by a signed amount. Because the ledger is append-only, the running balance shown on a stock level is always reconstructable from its transactions, and corrections are made by reversing a transaction rather than editing it.

Transactions are viewed and created from the **Transactions** panel on the [stock level detail page](/docs/platform/stock-levels) (`/stock-levels/{id}`). There is no standalone transactions list — a transaction always belongs to exactly one stock level.

## Transaction Types

Every transaction has a type that determines whether it **adds to** or **removes from** held stock. The sign is applied automatically when the transaction is created and reversed when it is deleted.

| Code | Name | Direction | Source |
|------|------|-----------|--------|
| 1 | Opening Balance | + | System |
| 2 | Increase | + | System |
| 3 | Decrease | − | System |
| 4 | Buy | + | Manual |
| 5 | Find | + | Manual |
| 6 | Write Off | − | Manual |
| 7 | Sell | − | Manual |
| 8 | Return | + | System |
| 9 | Make | + | Manual |
| 10 | Transfer Out | − | System |
| 11 | Transfer In | + | System |

Only the five **Manual** types — Buy, Find, Write Off, Sell, and Make — can be created by a user through the Add Transaction form (or the API). The remaining types are produced by the system as a side effect of other operations such as opening balances and stock transfers.

## The Signed-Quantity Ledger

A transaction stores a positive `quantity`. Its **move** is that quantity with the type's direction applied — positive for additions, negative for reductions. When a transaction is created, the move is added to the stock level's `quantity_held`. The Transactions panel shows, for each row in chronological order:

| Column | Meaning |
|--------|---------|
| Opening b/fwd | The held quantity before this transaction |
| Quantity | The positive quantity recorded |
| Move | The signed change (+ green, − red) |
| Closing c/fwd | The held quantity after this transaction |

The newest transaction's closing balance always equals the stock level's current quantity held. This running balance is computed from the ledger, never stored on each row, so the figures stay internally consistent.

## Adding a Transaction

From the stock level detail page, click **Add Transaction** and choose a type, quantity, date, and optional description. The held quantity updates immediately. A few rules apply:

- The quantity must be greater than zero.
- Serialised stock can only move one unit at a time, so a serialised stock level rejects quantities above one.
- The quantity update and the ledger row are written together in a single database transaction, so a failure leaves both untouched.

## Reversing a Transaction (Delete)

The ledger is immutable — transactions cannot be edited. To correct a mistake, **delete** the transaction. Deleting reverses the exact signed quantity that creation applied, restoring the held quantity to what it would have been. This is the only way to undo a movement, and it keeps the audit trail intact: the deletion itself is recorded.

Deleting a transaction from the panel:

- Decrements (or increments) the parent stock level by the reversed move.
- Records the change in the audit log.
- Fires the `stock_transaction.deleted` webhook event so integrators can react to reversals.

> **Note:** Deletion is available in the web UI only. The API exposes list, show, and create operations for transactions but does not provide a delete endpoint. See the [Stock Transactions API](/docs/api/stock-transactions) for the available endpoints.

## API Access

Stock transactions are exposed as a nested API resource under their stock level:

```
GET  /api/v1/products/{product}/stock_levels/{stock_level}/stock_transactions
POST /api/v1/products/{product}/stock_levels/{stock_level}/stock_transactions
```

See the [Stock Transactions API](/docs/api/stock-transactions) page for full request and response details, including the `quantity_move` signed field and the manual-only type restriction.
