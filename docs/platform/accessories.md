---
title: Accessories
description: Link related products together so accessories are suggested and tracked alongside a parent product.
---

## Overview

**Accessories** let you associate one product with others that typically go out together — for example, a moving-head fixture with its clamp, safety bond, and power cable. Each accessory is a link between a **parent product** and an **accessory product** (both are ordinary products in your catalogue), carrying a quantity and a couple of pricing flags. Accessories are managed per-product from the product detail page and exposed over the REST API as a nested resource.

## Accessories Tab

**Route:** `/products/{id}/accessories`

The **Accessories** tab on the product detail page lists every product currently linked as an accessory of the product you are viewing.

| Column | Description |
|--------|-------------|
| Accessory | The linked accessory product (links through to that product) |
| Type | The accessory product's type badge (Rental, Sale, Service, Loss & Damage) |
| Quantity | How many of the accessory go with one of the parent product |

The tab header shows the total accessory count. Each row has a **Remove** action that unlinks the accessory (with a confirmation prompt).

## Adding an Accessory

Click **Add Accessory** to open the picker modal:

- **Search Product** — type at least two characters to search the catalogue by name. The product you are editing and any products already linked are excluded from the results.
- **Quantity** — how many of the accessory accompany one parent product (defaults to 1, minimum 1).

Select a product from the search results and click **Add** to create the link.

> **Note:** A product cannot be an accessory of itself, and the same accessory product cannot be linked to the same parent twice — both are enforced when you add an accessory.

## Accessory Fields

Each accessory link stores the following:

| Field | Description |
|-------|-------------|
| `product_id` | The parent product |
| `accessory_product_id` | The linked accessory product |
| `quantity` | How many accessories per parent product |
| `included` | Whether the accessory is included by default when the parent is added to an order |
| `zero_priced` | Whether the accessory is charged at zero when included |
| `sort_order` | Display order among a product's accessories |

> **Note:** The `included` and `zero_priced` flags are stored on every accessory link and surfaced through the API for forward compatibility. They feed the order/quote pricing behaviour that is built in a later phase — the platform UI currently manages the link and quantity only.

## Deletion Behaviour

Accessory links are scoped to their parent product. Deleting either the parent product or the accessory product removes the link automatically (the database cascades on delete), so there are never orphaned accessory rows.

## API Access

Accessories are fully manageable over the REST API as a resource nested under products. See the [Accessories API](/docs/api/accessories) reference for endpoints, request bodies, and response shapes.
