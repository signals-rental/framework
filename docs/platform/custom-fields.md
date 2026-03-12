---
title: Custom Fields
description: Add custom data fields to any entity using the EAV field system.
---

## Overview

Custom fields extend Signals entities with additional data fields without modifying the database schema. Fields are stored using an Entity-Attribute-Value (EAV) pattern — field definitions in the `custom_fields` table and values in the `custom_field_values` table.

Custom fields can be added to Members, Opportunities, Products, Invoices, and Stores.

## Custom Field Groups

**Route:** `/admin/settings/custom-field-groups`

Groups organise related custom fields together. Each group has a name, optional description, and sort order that controls display position.

| Action | Description |
|--------|-------------|
| Create | Add a new field group |
| Edit | Update group name, description, or sort order |
| Delete | Remove an empty group (groups with fields cannot be deleted) |

## Managing Custom Fields

**Route:** `/admin/settings/custom-fields`

The custom fields list can be filtered by module type. Each field definition includes:

| Setting | Description |
|---------|-------------|
| Name | Internal identifier (lowercase, underscores) |
| Display Name | Human-readable label shown in the UI |
| Description | Help text shown to users |
| Module | Which entity type this field applies to |
| Field Type | Data type and input control |
| Group | Custom field group for organisation |
| List | Source list for Select/MultiSelect types |
| Sort Order | Display position within its group |
| Required | Whether a value must be provided |
| Searchable | Whether the field is included in search |
| Default Value | Pre-populated value for new records |
| Active | Whether the field is currently in use |

## Field Types

| Type | Description | Storage |
|------|-------------|---------|
| Text | Single-line text input | `value_string` |
| TextArea | Multi-line text input | `value_text` |
| Integer | Whole number | `value_integer` |
| Decimal | Decimal number | `value_decimal` |
| Boolean | Yes/No checkbox | `value_boolean` |
| Date | Date picker | `value_date` |
| DateTime | Date and time picker | `value_datetime` |
| Time | Time picker | `value_time` |
| Select | Dropdown from a list | `value_string` |
| MultiSelect | Multiple choices from a list | `value_json` |
| URL | Web address | `value_string` |
| Email | Email address | `value_string` |
| Phone | Phone number | `value_string` |
| Colour | Colour picker | `value_string` |
| Currency | Monetary amount | `value_decimal` |
| Percentage | Percentage value | `value_decimal` |
| RichText | Formatted text editor | `value_text` |

## API Access

Custom fields appear in API responses as a flat JSON object:

```json
{
  "custom_fields": {
    "po_reference": "PO-12345",
    "special_requirements": "Requires forklift access"
  }
}
```

Query custom fields using Ransack syntax: `?q[cf.field_name_eq]=value`.
