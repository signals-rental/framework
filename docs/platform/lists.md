---
title: Lists
description: Manage configurable dropdown lists used across the platform.
---

## Overview

Lists provide configurable sets of values for dropdown menus throughout Signals. They replace hard-coded enums with user-manageable options, allowing each installation to customise categories, types, and classifications.

## List Names

**Route:** `/admin/settings/list-names`

Each list has a unique name, optional description, and two flags:

| Property | Description |
|----------|-------------|
| Name | Unique identifier for the list |
| Description | What the list is used for |
| Hierarchical | Whether values can have parent-child relationships |
| System | Whether the list is built-in (cannot be deleted) |

System lists are seeded during installation and provide the foundation for contact detail types and other core features.

## Managing List Values

**Route:** `/admin/settings/lists/{listName}`

Each list contains values that appear as dropdown options. Values have:

| Property | Description |
|----------|-------------|
| Name | The display text |
| Parent | Parent value (hierarchical lists only) |
| Sort Order | Display position |
| System | Whether the value is built-in |
| Active | Whether the value appears in dropdowns |

System values cannot be edited or deleted. Non-system values can be deactivated (hidden from dropdowns while preserving existing references).

## Built-in Lists

| List Name | Used For |
|-----------|----------|
| AddressType | Address classification (e.g. Head Office, Branch, Billing) |
| EmailType | Email classification (e.g. Work, Personal, Support) |
| PhoneType | Phone classification (e.g. Mobile, Landline, Fax) |
| LinkType | Link classification (e.g. Website, LinkedIn, Twitter) |

## Using Lists in Custom Fields

Select and MultiSelect custom field types reference a list as their value source. When creating a custom field with these types, you select which list provides the dropdown options.
