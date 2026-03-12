---
title: Countries
description: Browse and manage which countries are active for use across the platform.
---

## Overview

The countries table provides a pre-seeded reference list of countries used throughout Signals for address forms, phone prefixes, timezone defaults, and currency selection. Countries are read-only — you cannot add or remove them — but you can toggle which countries are active.

**Route:** `/admin/settings/countries`

## Country Data

Each country record includes:

| Field | Description |
|-------|-------------|
| Code | ISO 3166-1 alpha-2 country code (e.g. GB, US, AU) |
| Name | Full country name |
| Currency Code | Default ISO 4217 currency code for the country |
| Phone Prefix | International dialling prefix (e.g. +44, +1) |
| Default Timezone | Default timezone identifier (e.g. Europe/London) |
| Active | Whether the country appears in dropdown menus |

## Managing Active Countries

Click the status badge on any country row to toggle it between Active and Inactive. Inactive countries are hidden from address and country dropdown menus but remain in the database for existing records that reference them.

The list supports search by country name or code, and paginates at 50 countries per page.

## API Access

Countries are available as a read-only API resource:

- `GET /api/v1/countries` — list all countries
- `GET /api/v1/countries/{id}` — get a single country

## Permissions

Managing country active status requires the `settings.manage` permission.
