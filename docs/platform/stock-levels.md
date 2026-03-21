---
title: Stock Levels
description: Track individual stock items, quantities, and locations across stores.
---

## Overview

Stock Levels represent the physical inventory in Signals. Each stock level record tracks a specific item (or bulk quantity) of a product at a particular store location. Stock can be serialised (individually tracked with asset/serial numbers) or bulk (tracked by quantity).

| Category | Value | Description |
|----------|-------|-------------|
| Bulk Stock | 10 | Tracked by quantity |
| Serialised Stock | 50 | Individually tracked items |

## Stock Levels List

**Route:** `/stock-levels`

Browse all stock levels with search, filtering, and sorting.

- **Search** — filter by item name, asset number, serial number
- **Column sorting** — sortable columns
- **Pagination** — configurable items per page

| Column | Description |
|--------|-------------|
| Item Name | Display name for the stock item |
| Asset Number | Unique asset identifier |
| Serial Number | Manufacturer serial number |
| Store | Location/warehouse |
| Product | Associated product |
| Quantity Held | Total quantity in stock |
| Quantity Allocated | Quantity reserved for orders |
| Created | Creation date |

## Stock Level Detail

**Route:** `/stock-levels/{id}`

View complete stock level information including quantities, location, and associated product details.

## Custom Views

System views for stock levels:

- All Stock Levels
- Serialised Stock (stock_category = 50)
- Bulk Stock (stock_category = 10)
