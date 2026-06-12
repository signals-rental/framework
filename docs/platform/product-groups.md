---
title: Product Groups
description: Organise your product catalogue into a hierarchy of groups and subgroups with icons and custom fields.
---

## Overview

Product groups are the organisational backbone of the Signals product catalogue. They act as categories — grouping related products together for browsing, filtering, and reporting. Groups support a two-level parent/child hierarchy so you can structure your catalogue (e.g. "Lighting" with subgroups "Moving Heads" and "LED Bars"), attach an icon for visual identification, and carry custom fields.

## Product Groups List

**Route:** `/product-groups`

Browse all product groups with search, sorting, and pagination.

- **Search** — filter by group name
- **Column sorting** — sortable columns including Name and Sort Order
- **Pagination** — configurable items per page

| Column | Description |
|--------|-------------|
| Name | Group name with optional icon |
| Parent Group | Parent group if this is a subgroup |
| Sort Order | Display order within sibling groups |
| Products | Count of products assigned to this group |
| Created | Creation date |

### Row Actions

Each row has a dropdown menu with View, Edit, and Delete actions.

## Product Group Detail

**Route:** `/product-groups/{id}`

View the group's details including description, icon, hierarchy information, and the list of products assigned to it.

## Create / Edit Product Group

**Route:** `/product-groups/create` or `/product-groups/{id}/edit`

| Field | Description |
|-------|-------------|
| Name | Group name (required) |
| Description | Free-text description |
| Parent Group | Optional parent group — making this a subgroup |
| Sort Order | Integer controlling display order among siblings |

## Parent/Child Hierarchy

Product groups support one level of nesting. A group with no parent is a **root group**; a group with a `parent_id` is a **subgroup**.

- Root groups appear at the top level of catalogue navigation.
- Subgroups are nested under their parent in the catalogue tree.
- A subgroup cannot itself be a parent — the hierarchy is limited to two levels.
- Products are assigned to a specific group (root or subgroup); they are not automatically rolled up to the parent.

When using the API, the `parent_id` field identifies the parent group. Use `?include=parent` to get the parent's `id` and `name` inline, or `?include=children` to get an array of immediate child groups.

## Icons

Each group can have an **icon** — a small image shown alongside the group name in the catalogue and product lists.

Icons are uploaded via the `IconUpload` component on the group create/edit form:

- Supported formats: JPEG, PNG, WebP, GIF
- A full-size URL (`icon_url`) and a 150×150 thumbnail (`icon_thumb_url`) are stored
- Removing the icon clears both URLs

> **Note:** Icon uploads are managed through the UI form only; the REST API returns the icon URLs in the response but does not accept file uploads directly on the group endpoint. Use the attachments endpoint for file uploads.

## Merge Groups

Product groups can be merged from the group detail page. Merging transfers all products and custom field values from the secondary group to the primary group. The secondary group is deleted after the merge.

## Custom Fields

Custom fields configured for the "Product Group" module appear on the Custom Fields tab, grouped by their custom field group. Fields display their current value or "No value set" for empty fields.

## Custom Views

Saved list configurations allow customising the product groups list with specific columns, filters, and sort orders. System views are provided by default:

- All Product Groups
- Root Groups (groups with no parent)
- Subgroups (groups with a parent)
