# Accessories API

CRUD endpoints for managing the accessories linked to a product. Accessories are a resource nested under products — every accessory belongs to a parent product and points at another product as the accessory item.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/products/{product}/accessories` | List a product's accessories |
| POST | `/api/v1/products/{product}/accessories` | Link an accessory to the product |
| PUT | `/api/v1/products/{product}/accessories/{accessory}` | Update a linked accessory |
| DELETE | `/api/v1/products/{product}/accessories/{accessory}` | Unlink an accessory |

> **Note:** There is no `show` endpoint for a single accessory — list the parent product's accessories instead.

## Authentication

Requires a Sanctum bearer token with `products:read` (GET) or `products:write` (POST/PUT/DELETE) ability.

## List Accessories

```
GET /api/v1/products/{product}/accessories
```

Returns every accessory linked to the product, ordered by the database default. The accessory product's name is resolved into `related_name`.

### Response

```json
{
    "accessories": [
        {
            "id": 1,
            "product_id": 10,
            "accessory_product_id": 42,
            "related_name": "Half-Coupler Clamp",
            "quantity": "2.0",
            "included": true,
            "zero_priced": true,
            "sort_order": 0
        }
    ]
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Accessory link ID |
| `product_id` | int | Parent product ID |
| `accessory_product_id` | int | Linked accessory product ID |
| `related_name` | string | Name of the accessory product |
| `quantity` | string | Quantity per parent product (decimal string, e.g. `"2.0"`) |
| `included` | bool | Whether included by default on an order |
| `zero_priced` | bool | Whether charged at zero when included |
| `sort_order` | int | Display order among the product's accessories |

## Create Accessory

```
POST /api/v1/products/{product}/accessories
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `accessory_product_id` | int | Yes | Product to link (must exist and not be soft-deleted) |
| `quantity` | numeric | No | Quantity per parent product (min 0, default 1) |
| `included` | bool | No | Included by default (default false) |
| `zero_priced` | bool | No | Charged at zero when included (default false) |
| `sort_order` | int | No | Display order (min 0, default 0) |

The `product_id` is taken from the URL — it is not accepted in the body.

```json
{
    "accessory_product_id": 42,
    "quantity": 2,
    "included": true,
    "zero_priced": true
}
```

Returns `201 Created` with the new accessory in an `accessory` key.

> **Note:** A product/accessory pair is unique — attempting to link the same accessory product to the same parent twice is rejected.

## Update Accessory

```
PUT /api/v1/products/{product}/accessories/{accessory}
```

All fields are optional; only the fields you send are changed. If the accessory does not belong to the product in the URL, the request returns `404 Not Found`. Returns `200 OK` with the updated accessory.

## Delete Accessory

```
DELETE /api/v1/products/{product}/accessories/{accessory}
```

Unlinks the accessory from the product. If the accessory does not belong to the product in the URL, the request returns `404 Not Found`. Returns `204 No Content`.

## Webhooks

Creating, updating, or deleting an accessory dispatches a `product.updated` webhook event for the parent product, since a product's accessory list is part of its definition.
