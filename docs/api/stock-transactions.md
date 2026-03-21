---
title: Stock Transactions
---

# Stock Transactions API

Stock transactions record stock movements against stock levels — purchases, sales, write-offs, and other inventory changes.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/products/{product}/stock_levels/{stock_level}/stock_transactions` | List transactions |
| GET | `/api/v1/products/{product}/stock_levels/{stock_level}/stock_transactions/{id}` | Show transaction |
| POST | `/api/v1/products/{product}/stock_levels/{stock_level}/stock_transactions` | Create transaction |

## Transaction Types

| Code | Name | Direction |
|------|------|-----------|
| 1 | Opening Balance | + (system only) |
| 2 | Increase | + (system only) |
| 3 | Decrease | − (system only) |
| 4 | Buy | + |
| 5 | Find | + |
| 6 | Write Off | − |
| 7 | Sell | − |
| 8 | Return | + (system only) |
| 9 | Make | + |
| 10 | Transfer Out | − (system only) |
| 11 | Transfer In | + (system only) |

Only types **4 (Buy), 5 (Find), 6 (Write Off), 7 (Sell), 9 (Make)** can be created via API.

## List Transactions

```
GET /api/v1/products/:product_id/stock_levels/:stock_level_id/stock_transactions
```

### Response

```json
{
  "stock_transactions": [
    {
      "id": 695,
      "stock_level_id": 626,
      "store_id": 1,
      "source_id": null,
      "source_type": null,
      "transaction_type": 4,
      "transaction_type_name": "Buy",
      "transaction_at": "2026-03-21T00:00:00.000Z",
      "quantity": "5.0",
      "quantity_move": "5.0",
      "description": "Purchased from supplier",
      "manual": true,
      "created_at": "2026-03-21T10:00:00.000Z",
      "updated_at": "2026-03-21T10:00:00.000Z"
    }
  ],
  "meta": {
    "total": 1,
    "per_page": 20,
    "page": 1
  }
}
```

## Create Transaction

```
POST /api/v1/products/:product_id/stock_levels/:stock_level_id/stock_transactions
```

### Request Body

```json
{
  "transaction_type": 4,
  "quantity": "5.0",
  "transaction_at": "2026-03-21T00:00:00.000Z",
  "description": "Purchased from supplier"
}
```

### Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| transaction_type | integer | Yes | Transaction type code (4, 5, 6, 7, or 9) |
| quantity | string | Yes | Positive quantity (min 0.01) |
| transaction_at | string | No | ISO 8601 date (defaults to now) |
| description | string | No | Description of the transaction |

### Response

```json
{
  "stock_transaction": {
    "id": 696,
    "stock_level_id": 626,
    "store_id": 1,
    "source_id": null,
    "source_type": null,
    "transaction_type": 4,
    "transaction_type_name": "Buy",
    "transaction_at": "2026-03-21T00:00:00.000Z",
    "quantity": "5.0",
    "quantity_move": "5.0",
    "description": "Purchased from supplier",
    "manual": true,
    "created_at": "2026-03-21T10:00:00.000Z",
    "updated_at": "2026-03-21T10:00:00.000Z"
  }
}
```

The `quantity_move` field shows the signed quantity — positive for stock additions, negative for reductions. The `quantity_held` on the parent stock level is automatically updated.

## Authentication

Requires a Sanctum token with `stock:read` (GET) or `stock:write` (POST) ability.
