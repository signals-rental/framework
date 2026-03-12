---
title: Admin Panel
description: Manage company settings, users, security, email, notifications, system health, and infrastructure.
---

## Overview

The admin panel lets administrators and owners manage system configuration, users, and access control. Access it at `/admin` or via the **Admin** link in the user menu or main sidebar.

The panel is restricted to users with the `is_admin` flag, `is_owner` flag, or the `Admin` role. All other users receive a 403 response.

## Navigation

On admin pages, the main sidebar replaces the normal application navigation with a dedicated admin menu. A **Back to app** link at the top returns you to the dashboard.

The admin panel uses a two-column layout:

| Column | Purpose |
|--------|---------|
| Sub-sidebar (left) | Navigation between settings sub-groups |
| Main content (right) | The active settings form |

The sidebar is organised into six navigation groups: **Setup**, **Users & Security**, **Preferences**, **System**, **Data**, and **Tax**. A top-level navigation bar lets you switch between groups. All navigation uses `wire:navigate` for instant SPA-like page transitions without full reloads.

## Accessing the Admin Panel

There are three ways to reach the admin panel:

- **User menu** — click your avatar in the top-right header, then click **Admin**
- **Sidebar link** — an **Admin** link appears at the bottom of the main sidebar for admin/owner users
- **Direct URL** — navigate to `/admin` (redirects to `/admin/settings/company`)

## Setup

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

## Users & Security

### Users

**Route:** `/admin/settings/users`

Manage user accounts, send invitations, and control access. The users table shows each user's name, email, roles, status, and last login time.

| Action | Description |
|--------|-------------|
| Invite User | Send an email invitation with assigned roles |
| Edit | Open the user's detail form |
| Resend Invitation | Re-send a pending invitation email |
| Send Password Reset | Trigger a password reset email |
| Deactivate | Disable the user's access (revokes API tokens) |
| Reactivate | Restore a deactivated user's access |
| Transfer Ownership | Transfer account ownership to another user (owner only) |

User statuses: **Active**, **Invited** (pending acceptance), **Deactivated**.

### User Edit Form

**Route:** `/admin/settings/users/{user}/edit`

Edit an individual user's name, email, admin flag, and role assignments. The form also displays the user's invitation and login history.

### Roles

**Route:** `/admin/settings/roles`

Create and manage roles for permission-based access control. Each role has a name, description, and a set of permissions.

| Action | Description |
|--------|-------------|
| Create Role | Opens a form to define a new role with permissions |
| Edit | Modify an existing role's name, description, and permissions |
| Delete | Remove a non-system role (only if no users are assigned) |

System roles cannot be deleted. Roles are displayed in sort order.

### Permissions

**Route:** `/admin/settings/permissions`

Read-only reference of all registered permissions grouped by category. Permissions follow the `resource.action` naming convention (e.g. `opportunities.create`, `invoices.issue`). Plugins can register additional permissions.

### Security

**Route:** `/admin/settings/security`

Configure password policies and two-factor authentication requirements.

| Setting | Description |
|---------|-------------|
| Minimum Password Length | Minimum characters required (default: 8) |
| Require Uppercase | Require at least one uppercase letter |
| Require Numeric | Require at least one number |
| Require Special Character | Require at least one special character |
| 2FA for Admins | Require two-factor authentication for admin users |
| 2FA for All Users | Require two-factor authentication for all users |

### API Tokens

**Route:** `/admin/settings/api`

Manage API tokens for programmatic access to the Signals API. Each token has a name, a set of abilities controlling which endpoints it can access, and usage tracking.

| Action | Description |
|--------|-------------|
| Create Token | Generate a new API token with selected abilities |
| Revoke | Permanently delete a token |

Token values are shown once at creation and cannot be retrieved later. If a token is lost, revoke it and create a new one.

> **Note:** API tokens inherit the creating user's account permissions. A token cannot exceed the abilities granted to it.

## Preferences

### General

**Route:** `/admin/settings/preferences`

Configure display and formatting defaults used across the application.

| Setting | Description | Default |
|---------|-------------|---------|
| Decimal Separator | Character for decimal points (`.` or `,`) | `.` |
| Thousands Separator | Character for thousands grouping (`,`, `.`, space, or none) | `,` |
| Currency Display | Show currency as symbol, code, or name | `symbol` |
| First Day of Week | Which day starts the week (0=Sunday through 6=Saturday) | `1` (Monday) |
| Items Per Page | Default pagination size (10, 25, 50, or 100) | `25` |

### Email

**Route:** `/admin/settings/email`

Configure outbound email delivery. Supports SMTP, Amazon SES, Mailgun, Postmark, and Laravel's log driver.

| Setting | Description |
|---------|-------------|
| Mailer | Email driver (smtp, ses, mailgun, postmark, log) |
| From Address | Default sender address |
| From Name | Default sender name |
| Reply-To Address | Default reply-to address |
| SMTP/SES/Mailgun/Postmark | Driver-specific credentials |

A **Send Test Email** button lets you verify your configuration by sending to any address.

### Email Templates

**Routes:** `/admin/settings/email-templates`, `/admin/settings/email-templates/{template}/edit`

Manage database-stored email templates with Markdown bodies and merge field syntax. System templates can be customised and reset to defaults.

| Action | Description |
|--------|-------------|
| Edit | Modify the template subject and Markdown body |
| Reset | Revert a system template to its seeded default content |

Templates support merge fields using `{{ field.path }}` syntax with optional filters: `{{ name | upper }}`, `{{ value | default:"N/A" }}`. A merge field reference sidebar shows available fields for each template. Every edit creates a version snapshot for history tracking.

> **Note:** Requires the `email-templates.manage` permission.

### Notifications

**Route:** `/admin/settings/notifications`

Configure system-wide notification channels for each registered notification type. Types are grouped by category with per-type channel toggles (database, email, broadcast) and a master enable/disable switch.

Each notification type defines which channels are available and which are enabled by default. System-level overrides apply to all users unless individual users set their own preferences.

> **Note:** Requires the `notifications.manage` permission.

### Scheduling

**Route:** `/admin/settings/scheduling`

Set defaults for opportunity durations, buffer times, reminders, and availability windows.

| Setting | Description | Default |
|---------|-------------|---------|
| Default Opportunity Duration | Duration in days for new opportunities | `1` |
| Buffer Before | Minutes of prep time before an opportunity | `0` |
| Buffer After | Minutes of cleanup time after an opportunity | `0` |
| Collection Reminder | Days before collection to send reminders | `1` |
| Return Reminder | Days before return to send reminders | `1` |
| Default Start Time | Default daily start time | `09:00` |
| Default End Time | Default daily end time | `17:00` |
| Weekend Availability | Whether weekends are available for scheduling | Off |

## System

### Action Log

**Route:** `/admin/settings/action-log`

Browse the audit trail of all recorded actions. The log captures who did what, when, and the before/after values for changes. Entries are created automatically when action classes fire `AuditableEvent`.

The table supports filtering by action type, entity type, user, and date range. Expand a row to see the old and new value diff. Entries are paginated and sorted by most recent.

Action logs are automatically pruned based on the configured retention period (default: 12 months). The `action-log:prune` Artisan command runs daily via the scheduler.

### System Health

**Route:** `/admin/settings/system-health`

Read-only diagnostic dashboard showing the status of all connected services. Each service displays as a status card with connection details.

| Check | What it tests |
|-------|---------------|
| PostgreSQL | Database connectivity, version |
| Redis | Connection status, version (skipped if not in use) |
| S3 Storage | Bucket access (skipped if using local disk) |
| Queue | Pending and failed job counts |
| Scheduler | Heartbeat detection (warns if no run in 5+ minutes) |
| PHP | Version, memory limit, execution time, upload limits |

Click **Refresh** to re-run all checks. Failed checks report the error message for diagnostics.

### Infrastructure

**Route:** `/admin/settings/infrastructure`

**Owner-only.** Configure low-level service connections (database, Redis, S3, queue driver) and run connection tests. Changes are written directly to the `.env` file and take effect after reloading.

Each section has a **Test Connection** button to verify credentials before saving. This page also provides access to run Artisan `migrate` and clear application caches.

> **Note:** This page is only visible to the account owner.

### Webhooks

**Route:** `/admin/settings/webhooks`

Manage webhook subscriptions that notify external services when events occur in Signals. Webhooks are delivered via HTTP POST with HMAC-SHA256 signed payloads.

| Action | Description |
|--------|-------------|
| Create Webhook | Register a new endpoint URL with selected event subscriptions |
| Edit | Update URL, events, or active status |
| Re-enable | Reactivate a disabled webhook and reset its failure counter |
| View Logs | See delivery history with response codes and retry attempts |
| Delete | Permanently remove a webhook subscription |

Webhooks are automatically disabled after 18 consecutive delivery failures (approximately 3 days). Re-enable from this page after fixing the receiving endpoint.

> **Note:** Webhook secrets are shown once at creation and cannot be retrieved later. If lost, delete the webhook and create a new one.

### Database Seeders

**Route:** `/admin/settings/seeders`

Run database seeders to populate reference data. Each seeder shows its status (not run, completed, or available) and can be executed individually. Useful after initial setup or when adding new modules.

## Data

### Custom Field Groups

**Route:** `/admin/settings/custom-field-groups`

Organise custom fields into named groups for display purposes. Groups control how custom fields are grouped on entity detail pages.

| Action | Description |
|--------|-------------|
| Create | Add a new field group with name, description, and sort order |
| Edit | Update group details |
| Delete | Remove a group (only if no fields are assigned to it) |

### Custom Fields

**Route:** `/admin/settings/custom-fields`

Define custom data fields that extend entities with additional information. Fields can be filtered by module type. See the [Custom Fields](/docs/platform/custom-fields) page for detailed documentation.

| Action | Description |
|--------|-------------|
| Create | Define a new custom field with type, module, group, and validation |
| Edit | Update field configuration |
| Delete | Remove a field definition |

### List Names

**Route:** `/admin/settings/list-names`

Manage configurable list definitions used for dropdown menus. See the [Lists](/docs/platform/lists) page for detailed documentation.

| Action | Description |
|--------|-------------|
| Create | Add a new list definition |
| Edit | Update list name and description |
| Manage Values | Navigate to the list's values page |
| Delete | Remove a non-system list |

### List Values

**Route:** `/admin/settings/lists/{listName}`

Manage the individual values within a list. Accessed via the "Manage Values" action on the List Names page.

| Action | Description |
|--------|-------------|
| Create | Add a new value to the list |
| Edit | Update value name, sort order, or parent |
| Toggle Active | Show or hide the value in dropdowns |
| Delete | Remove a non-system value |

### Countries

**Route:** `/admin/settings/countries`

View and manage the list of countries available in the system. Countries are read-only reference data — you can only toggle their active status.

| Action | Description |
|--------|-------------|
| Toggle Active | Enable or disable a country for use in address forms and settings |

## Tax

### Product Tax Classes

**Route:** `/admin/settings/tax/product-tax-classes`

Manage product tax classifications. See the [Tax Classes](/docs/platform/tax-classes) page for detailed documentation.

| Action | Description |
|--------|-------------|
| Create | Add a new product tax class |
| Edit | Update name and description |
| Set Default | Designate as the default for new products |
| Delete | Remove a non-default tax class |

### Organisation Tax Classes

**Route:** `/admin/settings/tax/organisation-tax-classes`

Manage organisation tax classifications. See the [Tax Classes](/docs/platform/tax-classes) page for detailed documentation.

| Action | Description |
|--------|-------------|
| Create | Add a new organisation tax class |
| Edit | Update name and description |
| Set Default | Designate as the default for new organisations |
| Delete | Remove a non-default tax class |

## Access Control

The admin panel is protected by the `EnsureAdmin` middleware, which checks that the authenticated user has `is_admin` set to `true`, `is_owner` set to `true`, or holds the `Admin` role. The middleware is registered as the `admin` alias and applied to all `/admin/*` routes alongside authentication and 2FA middleware.

```php
// Route middleware stack
['signals.setup-complete', 'auth', '2fa', 'admin']
```

Individual actions within the admin panel are further protected by gate checks using the `resource.action` permission pattern (e.g. `users.invite`, `roles.manage`, `settings.manage`). The account owner bypasses all permission checks via a `Gate::before` callback.

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
