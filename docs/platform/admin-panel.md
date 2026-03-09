---
title: Admin Panel
description: Manage company settings, stores, branding, and modules from the system administration panel.
---

## Overview

The admin panel lets administrators and owners manage the settings established during the initial setup wizard. Access it at `/admin` or via the **Admin** link in the user menu or main sidebar.

The panel is restricted to users with the `is_admin` or `is_owner` flag. All other users receive a 403 response.

## Navigation

On admin pages, the main sidebar replaces the normal application navigation with a dedicated admin menu. A **Back to app** link at the top returns you to the dashboard.

The admin panel uses a two-column layout:

| Column | Purpose |
|--------|---------|
| Sub-sidebar (left) | Navigation between settings sub-groups |
| Main content (right) | The active settings form |

All navigation uses `wire:navigate` for instant SPA-like page transitions without full reloads.

## Accessing the Admin Panel

There are three ways to reach the admin panel:

- **User menu** — click your avatar in the top-right header, then click **Admin**
- **Sidebar link** — an **Admin** link appears at the bottom of the main sidebar for admin/owner users
- **Direct URL** — navigate to `/admin` (redirects to `/admin/settings/company`)

## Settings Group

The Settings group contains four sub-pages for managing your instance configuration.

### Company Details

**Route:** `/admin/settings/company`

Edit your core company information and regional settings. When you change the country, timezone, currency, tax, and date format fields auto-populate with sensible defaults for that region.

| Field | Description |
|-------|-------------|
| Company Name | Your business name |
| Country | ISO 3166-1 alpha-2 country code |
| Timezone | PHP timezone identifier |
| Currency | ISO 4217 currency code |
| Default Tax Rate | Percentage (0-100) |
| Tax Label | Display label (e.g. VAT, GST, Tax) |
| Date Format | How dates are displayed throughout the app |
| Time Format | 12-hour or 24-hour clock |
| Fiscal Year Start | Month your financial year begins |

### Stores

**Route:** `/admin/settings/stores`

Manage your physical locations or warehouses. Every Signals instance requires at least one store, and one store must be designated as the **default**.

| Action | Description |
|--------|-------------|
| Add Store | Opens a modal to create a new store with name, address, and country |
| Edit | Update an existing store's details |
| Set Default | Designate a store as the default (atomically clears the previous default) |
| Delete | Remove a non-default store (the default store cannot be deleted) |

The first store created is automatically set as the default. New stores default their country to your company's country setting.

### Branding

**Route:** `/admin/settings/branding`

Customise your company's visual identity with brand colours and a logo.

| Field | Description | Default |
|-------|-------------|---------|
| Primary Colour | Main brand colour (hex) | `#1e3a5f` |
| Accent Colour | Secondary brand colour (hex) | `#3b82f6` |
| Logo | PNG, JPG, SVG, or WebP up to 2MB | None |

You can upload a new logo, preview it, or remove the existing one. Removing a logo deletes the file from storage.

### Modules

**Route:** `/admin/settings/modules`

Enable or disable application modules to match your business needs. Modules are displayed as toggle cards in a grid layout. Changes take effect immediately.

| Module | Description | Can be disabled? |
|--------|-------------|-----------------|
| CRM | Contacts, organisations, and venues | No (always on) |
| Opportunities | Quotes, orders, and active jobs | Yes |
| Products | Product catalogue and rate cards | Yes |
| Stock | Inventory tracking and availability | Yes |
| Invoicing | Billing, payments, and credit notes | Yes |
| Crew | Staff scheduling and assignments | Yes |
| Services | Labour and service items | Yes |
| Projects | Multi-opportunity project management | Yes |
| Inspections | Equipment testing and certifications | Yes |

> **Tip:** You can also set modules via a feature profile during initial setup. See the [Configuration](/docs/getting-started/configuration) page for profile details.

## Access Control

The admin panel is protected by the `EnsureAdmin` middleware, which checks that the authenticated user has either `is_admin` or `is_owner` set to `true`. The middleware is registered as the `admin` alias and applied to all `/admin/*` routes alongside authentication and 2FA middleware.

```php
// Route middleware stack
['signals.setup-complete', 'auth', '2fa', 'admin']
```

## Settings Storage

All admin panel settings are stored in the database via the `settings()` helper and are cached indefinitely with automatic invalidation on write. Settings follow the `group.key` naming convention:

```php
// Reading settings
$companyName = settings('company.name');
$isStockEnabled = settings('modules.stock');

// Writing settings
settings()->set('modules.stock', true, 'boolean');
settings()->setMany([
    'company.name' => 'Acme Rentals',
    'company.timezone' => 'Europe/London',
]);
```

> **Note:** Never use `env()` or `config()` for user-configurable values. Always use `settings()`.
