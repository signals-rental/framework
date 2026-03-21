# Stock Levels API

CRUD endpoints for managing stock levels (inventory items).

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/stock_levels` | List stock levels |
| GET | `/api/v1/stock_levels/{id}` | Show a stock level |
| POST | `/api/v1/stock_levels` | Create a stock level |
| PUT | `/api/v1/stock_levels/{id}` | Update a stock level |
| DELETE | `/api/v1/stock_levels/{id}` | Delete a stock level |

## Authentication

Requires a Sanctum bearer token with `stock:read` (GET) or `stock:write` (POST/PUT/DELETE) ability.

## List Stock Levels

```
GET /api/v1/stock_levels
```

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[product_id_eq]=5` | Filter by product |
| `q[store_id_eq]=1` | Filter by store |
| `q[stock_category_eq]=50` | Serialised stock only |
| `q[serial_number_cont]=SN` | Serial number contains |
| `q[asset_number_eq]=18670` | Exact asset number |
| `q[barcode_eq]=123` | Exact barcode |

### Sorts

| Parameter | Description |
|-----------|-------------|
| `sort=quantity_held` | Sort by quantity held |
| `sort=product_id` | Sort by product |
| `sort=created_at` | Sort by creation date |

### Includes

Eager-load relationships with `?include=product,store,member,customFieldValues`

### Custom Views

Apply a saved custom view: `?view_id=8`

### Response

```json
{
    "stock_levels": [
        {
            "id": 1,
            "product_id": 5,
            "store_id": 1,
            "item_name": "LED Wash #001",
            "asset_number": "18670",
            "serial_number": "SN-2026-001",
            "barcode": "5060001234567",
            "location": "Bay A, Shelf 3",
            "stock_type": 1,
            "stock_category": 50,
            "quantity_held": "1.00",
            "quantity_allocated": "0.00",
            "quantity_unavailable": "0.00",
            "custom_fields": {},
            "created_at": "2026-01-15T14:30:00Z",
            "updated_at": "2026-01-15T14:30:00Z"
        }
    ],
    "meta": {
        "total": 156,
        "per_page": 20,
        "page": 1
    }
}
```

## Show Stock Level

```
GET /api/v1/stock_levels/{id}
```

Returns the stock level wrapped in `{"stock_level": {...}}`.

## Create Stock Level

```
POST /api/v1/stock_levels
```

### Request Body

```json
{
    "product_id": 5,
    "store_id": 1,
    "item_name": "LED Wash #002",
    "asset_number": "18671",
    "serial_number": "SN-2026-002",
    "stock_type": 1,
    "stock_category": 50,
    "quantity_held": "1.00"
}
```

Returns `201 Created`.

## Update / Delete

Standard update (PUT, 200) and delete (DELETE, 204) patterns.
