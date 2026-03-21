---
title: Products
description: Manage the product catalogue including rental, sale, and service products.
---

## Overview

Products form the core of the equipment catalogue in Signals. Every item that can be rented, sold, or offered as a service is represented as a product. Products are grouped into product groups for organisation and can have stock levels, accessories, custom fields, and file attachments.

| Type | Description |
|------|-------------|
| Rental | Equipment available for hire |
| Sale | Items for outright purchase |
| Service | Labour, transport, or other services |
| Loss & Damage | Charges for damaged or lost items |

## Products List

**Route:** `/products`

Browse all products with search, type filtering, sorting, and bulk operations.

- **Search** — filter by name, SKU, or barcode (debounced, case-insensitive)
- **Type filter** — chip buttons to show All, Rental, Sale, or Service with counts
- **Archive filter** — toggle between Active, Archived, and All products
- **Column sorting** — click column headers to sort
- **Pagination** — configurable items per page

| Column | Description |
|--------|-------------|
| Name | Product name with icon/avatar |
| Type | Product type badge (Rental, Sale, Service) |
| Group | Associated product group |
| SKU | Stock keeping unit code |
| Stock | Count of stock levels |
| Status | Active or Inactive badge |
| Created | Creation date |

### Row Actions

Each row has a dropdown menu with View, Edit, Archive, and Restore actions.

## Product Detail

**Route:** `/products/{id}`

View complete product information organised across tabs.

### Tabs

| Tab | Description |
|-----|-------------|
| Overview | Core product details, type, status, pricing |
| Stock | Associated stock levels with quantities |
| Accessories | Linked accessory products |
| Custom Fields | User-defined custom field values |
| Files | Attached documents and images |
| Activities | /products/{id}/activities | Activity log for this product |

### Product Header

The product header displays the product name, type badge, active status, and action buttons (Edit, New Stock Level, New Accessory).

## Create / Edit Product

**Route:** `/products/create` or `/products/{id}/edit`

Two-column form layout:
- **Left column** — core fields grouped into sections (Basic Info, Identification, Stock, Pricing, Tax & Revenue, Other, Status flags)
- **Right column** — custom fields grouped by custom field group

### Fields

| Field | Description |
|-------|-------------|
| Name | Product name (required, unique) |
| Description | Free-text description |
| Product Type | Rental, Sale, Service, or Loss & Damage |
| Product Group | Category grouping |
| SKU | Stock keeping unit identifier |
| Barcode | Barcode value |
| Weight | Product weight |
| Stock Method | Bulk or Serialised tracking |
| Allowed Stock Type | Rental, Sale, or Both |
| Replacement Charge | Cost to replace (minor units) |
| Buffer Percent | Availability buffer percentage |
| Post-Rent Unavailability | Hours unavailable after rental return |
| Tax Class | Sale tax classification |
| Purchase Tax Class | Purchase tax classification |
| Revenue Groups | Rental and sale revenue groups |
| Cost Groups | Sub-rental and purchase cost groups |
| Country of Origin | Manufacturing origin |
| Tags | Comma-separated tag list |
| Active | Whether the product is active |
| Accessory Only | Product only available as accessory |
| System | System-managed product |
| Discountable | Whether discounts can apply |

## Merge Products

Products can be merged from the product detail page using the split button menu. Merging transfers all stock levels, accessories, attachments, and custom fields from the secondary product to the primary product. The secondary product is soft-deleted after merge.

## Custom Views

Saved list configurations allow users to customise the products list with specific columns, filters, and sort orders. System views are provided by default:

- All Products
- Rental Products
- Sale Products
- Active Products
- Inactive Products
