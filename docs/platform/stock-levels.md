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

### Transactions Panel

The stock level detail page includes an inline "Add Transaction" form for recording stock movements (adjustments, transfers, write-offs). Transactions are displayed in a chronological list below the form.

### Activities Tab

Stock levels have an Activities tab showing all activities linked to this stock level via the regarding association.

## Creating Stock Levels

**Route:** `/stock-levels/create`

When creating a new stock level, select a product first. The form detects the product's stock method and adjusts the available options accordingly.

- **Bulk products** — a single stock level tracks the total quantity held. Only one stock level per bulk product is allowed.
- **Serialised products** — each individual unit gets its own stock level with a unique asset number and serial number. See [Bulk Serialised Entry](#bulk-serialised-entry) below for entering multiple units at once.

### Bulk Serialised Entry

When a **serialised** product is selected on the create form, a mode switcher appears between **Single** and **Bulk** entry.

- **Single** — the default mode. Enter one asset number and serial number to create one stock level record.
- **Bulk** — enter multiple serialised units in a table of rows. Each row has an Asset Number / Barcode and a Serial Number field. All rows are created in a single database transaction when you save.

#### Adding rows

- Click **Add row** to append a new empty row.
- Press **Enter** inside any field to add a new row and move focus to it automatically.
- Click the **×** button on a row to remove it. At least one row must remain.

#### Uniqueness validation

Each field is validated live against existing stock levels in the database and against other rows in the form:

| Status indicator | Meaning |
|-----------------|---------|
| Green checkmark | Value is unique — no conflicts |
| Red ✕ | Duplicate — already exists in the database, or entered more than once in this form |
| Empty | Field is blank — no validation applied |

The **Create Stock Levels** button is disabled until all rows are either complete (both fields filled) and unique, or empty (trailing rows left after pressing Enter are ignored). The button label shows the count of complete rows that will be created, e.g. "Create 3 Stock Levels".

Partial rows (one field filled, one blank) also block submission.

#### After saving

After a successful bulk save, you are redirected to the product's **Stock** tab rather than an individual stock level, so you can see all the newly created records together.

## Custom Views

System views for stock levels:

- All Stock Levels
- Serialised Stock (stock_category = 50)
- Bulk Stock (stock_category = 10)
