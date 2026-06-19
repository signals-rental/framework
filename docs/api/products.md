# Products API

CRUD endpoints for managing products in the catalogue.

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/products` | List products |
| GET | `/api/v1/products/{id}` | Show a product |
| POST | `/api/v1/products` | Create a product |
| PUT | `/api/v1/products/{id}` | Update a product |
| DELETE | `/api/v1/products/{id}` | Delete (soft-delete) a product |
| POST | `/api/v1/products/{id}/merge` | Merge another product into this one |

### Nested Resources

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/products/{id}/accessories` | List accessories |
| POST | `/api/v1/products/{id}/accessories` | Add an accessory |
| PUT | `/api/v1/products/{id}/accessories/{accessory_id}` | Update an accessory |
| DELETE | `/api/v1/products/{id}/accessories/{accessory_id}` | Remove an accessory |

## Authentication

Requires a Sanctum bearer token with `products:read` (GET) or `products:write` (POST/PUT/DELETE) ability.

## List Products

```
GET /api/v1/products
```

### Filters

| Parameter | Description |
|-----------|-------------|
| `q[name_cont]=LED` | Name contains |
| `q[product_type_eq]=rental` | Filter by type |
| `q[is_active_eq]=true` | Active products only |
| `q[product_group_id_eq]=5` | Filter by product group |
| `q[sku_cont]=SKU` | SKU contains |
| `q[barcode_eq]=123456` | Exact barcode match |
| `q[stock_method_eq]=2` | Filter by stock method |
| `q[cf.field_name_eq]=value` | Filter by custom field |

### Sorts

| Parameter | Description |
|-----------|-------------|
| `sort=name` | Sort by name ascending |
| `sort=-name` | Sort by name descending |
| `sort=product_type` | Sort by product type |
| `sort=sku` | Sort by SKU |
| `sort=created_at` | Sort by creation date |

### Includes

Eager-load relationships with `?include=productGroup,taxClass,stockLevels,accessories,customFieldValues`

### Custom Views

Apply a saved custom view: `?view_id=3`

View filters merge with explicit `q` params (explicit params take priority). View sort applies only when no explicit `sort` param is given.

### Response

```json
{
    "products": [
        {
            "id": 1,
            "name": "LED Wash Light",
            "description": "Professional LED wash fixture",
            "product_type": "rental",
            "product_group_id": 2,
            "product_group_name": "Lighting",
            "sku": "LED-WASH-001",
            "barcode": "5060001234567",
            "is_active": true,
            "stock_method": 2,
            "weight": "5.5000",
            "replacement_charge": "500.00",
            "custom_fields": {
                "power_rating": "150W"
            },
            "created_at": "2026-01-15T14:30:00Z",
            "updated_at": "2026-01-15T14:30:00Z"
        }
    ],
    "meta": {
        "total": 42,
        "per_page": 20,
        "page": 1
    }
}
```

## Show Product

```
GET /api/v1/products/{id}
```

### Response

```json
{
    "product": {
        "id": 1,
        "name": "LED Wash Light",
        "description": "Professional LED wash fixture",
        "product_type": "rental",
        "product_group_id": 2,
        "product_group_name": "Lighting",
        "sku": "LED-WASH-001",
        "barcode": "5060001234567",
        "is_active": true,
        "stock_method": 2,
        "allowed_stock_type": 1,
        "weight": "5.5000",
        "replacement_charge": "500.00",
        "buffer_percent": "0.00",
        "post_rent_unavailability": 0,
        "accessory_only": false,
        "system": false,
        "discountable": true,
        "tax_class_id": 1,
        "purchase_tax_class_id": null,
        "rental_revenue_group_id": 1,
        "sale_revenue_group_id": null,
        "sub_rental_cost_group_id": null,
        "sub_rental_price": "0.00",
        "purchase_cost_group_id": null,
        "purchase_price": "0.00",
        "country_of_origin_id": null,
        "tag_list": ["lighting", "led"],
        "custom_fields": {},
        "created_at": "2026-01-15T14:30:00Z",
        "updated_at": "2026-01-15T14:30:00Z"
    }
}
```

## Create Product

```
POST /api/v1/products
```

### Request Body

```json
{
    "name": "Moving Head Spot",
    "product_type": "rental",
    "product_group_id": 2,
    "sku": "MH-SPOT-001",
    "stock_method": 2,
    "is_active": true
}
```

### Response

Returns `201 Created` with the product object.

## Update Product

```
PUT /api/v1/products/{id}
```

Only include fields to update. Omitted fields remain unchanged.

> **Clearing nullable fields:** Omitting a field or sending `null` leaves it unchanged. To clear a nullable field to `null`, send an empty string `""` as its value.

### Response

Returns `200 OK` with the updated product object.

## Delete Product

```
DELETE /api/v1/products/{id}
```

Soft-deletes the product. Returns `204 No Content`.

## Merge Product

```
POST /api/v1/products/{id}/merge
```

Merges a secondary product into the product in the URL (the primary). Stock, accessories, custom-field values, and attachments transfer to the primary, and the secondary is soft-deleted. Requires the `products:write` ability.

### Request Body

```json
{
    "secondary_id": 42
}
```

The `secondary_id` must reference an existing, non-archived product and must differ from the primary. Attempting to merge a product into itself returns a `422` validation error.

### Response

Returns `200 OK` with the merged primary product object.

## Kit Components

A product can be a **kit** — composed of other products (its bill-of-materials) rather than tracked as stock directly. Kit components are managed as a sub-resource of the parent product. Adding the first component flips the product's `is_kit` flag to `true`; removing the last component flips it back to `false`.

Kit availability is composed read-time from component availability, so the composition must stay acyclic and within the configured nesting depth (`availability.kit_nesting_max_depth`).

### Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/products/{product}/components` | List a product's kit components |
| POST | `/api/v1/products/{product}/components` | Add a component to the kit |
| PATCH | `/api/v1/products/{product}/components/{component}` | Update a component line |
| DELETE | `/api/v1/products/{product}/components/{component}` | Remove a component |

### Authentication

Requires the `kits:read` ability (GET) or `kits:write` (POST/PATCH/DELETE), and the matching `kits.view` / `kits.manage` permission.

### List Components

```
GET /api/v1/products/{product}/components
```

```json
{
    "components": [
        {
            "id": 1,
            "product_id": 10,
            "component_product_id": 42,
            "component_name": "Par Can 64",
            "quantity": "4.0000",
            "binding": "pool",
            "sort_order": 0
        }
    ]
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Component line ID |
| `product_id` | int | Parent kit product ID |
| `component_product_id` | int | Component product ID |
| `component_name` | string | Name of the component product |
| `quantity` | string | Quantity of the component per single kit (decimal string) |
| `binding` | string | `pool` (drawn from general stock) or `fixed` (permanently container-assigned) |
| `sort_order` | int | Display order |

### Add Component

```
POST /api/v1/products/{product}/components
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `component_product_id` | int | Yes | Product to add as a component (must exist) |
| `quantity` | numeric | No | Quantity per kit (min 1, default 1) |
| `binding` | string | No | `pool` (default) or `fixed` |
| `sort_order` | int | No | Display order (min 0, default 0) |

The `product_id` is taken from the URL. Returns `201 Created` with the new component in a `component` key.

Rejected with `422` when the component is the product itself, would create a circular composition, would exceed the maximum kit nesting depth, or is already a component of the kit.

### Update Component

```
PATCH /api/v1/products/{product}/components/{component}
```

All fields optional (`quantity`, `binding`, `sort_order`); only those supplied change. The parent kit and component product cannot be re-pointed — remove and re-add instead. A component that does not belong to the product in the URL returns `404`. Returns `200 OK`.

### Remove Component

```
DELETE /api/v1/products/{product}/components/{component}
```

Removes the component. Returns `204 No Content`. Removing the last component flips the product's `is_kit` flag back to `false`.
