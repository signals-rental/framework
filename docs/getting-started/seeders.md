---
title: Seeders
description: What data gets seeded by default and how to run individual seeders.
---

Signals ships with database seeders that populate your application with reference data, permissions, roles, and optional demo content. Running `php artisan db:seed` executes all default seeders in the correct dependency order.

## What Runs by Default

The `DatabaseSeeder` calls these seeders in order:

| # | Seeder | Purpose |
|---|--------|---------|
| 1 | `CountrySeeder` | ISO country data |
| 2 | `ListOfValuesSeeder` | System-managed lists of values |
| 3 | `CurrencySeeder` | ISO 4217 currency definitions |
| 4 | `TaxClassSeeder` | Default organisation and product tax classes |
| 5 | `TaxRateSeeder` | Default tax rates (Standard 20%, Reduced 5%, Zero 0%) |
| 6 | `RevenueGroupSeeder` | Revenue groups (Dry Hire, Wet Hire, Sales, Services) |
| 7 | `CostGroupSeeder` | Cost groups (Equipment Sub-Hire, Crew Sub-Hire, Transport, Consumables, Equipment Purchase) |
| 8 | `ProductGroupSeeder` | Default product groups (Audio, Lighting, Video, Staging, etc.) |
| 9 | `PermissionSeeder` | All system permissions for role-based access control |
| 10 | `RoleSeeder` | System roles with permission assignments |
| 11 | `EmailTemplateSeeder` | System email templates (invitation, password reset, test) |
| 12 | `NotificationTypeSeeder` | Notification type definitions with channel defaults |
| 13 | `ViewSeeder` | System-managed custom views for members, products, stock, and activities |
| 14 | `RateDefinitionPresetSeeder` | Industry-standard rate definition presets |
| 15 | `ProductSeeder` | Sample products across rental, sale, and service types |
| 16 | `ActivitySeeder` | Sample activities linked to seeded members and users |

It also creates a test user (`test@example.com`) for development.

## CountrySeeder

Loads all ISO country records from `database/data/countries.json`. Each country has a code, name, and associated metadata. Uses `updateOrCreate` keyed on `code` — safe to re-run.

## ListOfValuesSeeder

Creates the system-managed lists of values used throughout the platform:

| List | Values |
|------|--------|
| Address Type | Billing, Shipping, Primary, Registered |
| Email Type | Work, Personal, Billing, Support |
| Phone Type | Work, Mobile, Home, Fax |
| Link Type | Website, LinkedIn, Facebook, Instagram, X (Twitter), YouTube |
| Relationship Type | Employee, Director, Contractor, Agent |
| Lawful Basis Type | Legitimate interest (prospect/customer/supplier), Consent, Contract, Legal obligation, Vital interests, Public task, Not applicable |
| Location Type | Internal, External |
| Rating | None, 1–5 Stars |
| Invoice Term | Due on Receipt, Net 7/14/30/45/60/90, End of Month |
| Locale | 17 locale codes (en-GB, en-US, fr-FR, de-DE, and others) |
| File Category | Contract, Invoice, Quote, Purchase Order, Certificate, and 8 others |

All values are marked `is_system = true` and use `updateOrCreate` — safe to re-run.

## CurrencySeeder

Creates 30 ISO 4217 currency definitions. GBP, USD, and EUR are enabled by default; the remaining 27 are disabled and can be enabled in settings.

## TaxClassSeeder

Creates the default tax classification records:

- **Organisation Tax Class:** Standard (default)
- **Product Tax Classes:** Standard (default), Exempt

Uses `updateOrCreate` — safe to re-run.

## TaxRateSeeder

Creates three default tax rates suitable for UK VAT:

| Name | Rate |
|------|------|
| Standard | 20% |
| Reduced | 5% |
| Zero | 0% |

Uses `updateOrCreate` — safe to re-run.

## RevenueGroupSeeder

Creates four revenue groups used for categorising opportunity line items:

- Dry Hire
- Wet Hire
- Sales
- Services

Uses `updateOrCreate` — safe to re-run.

## CostGroupSeeder

Creates five cost groups used for categorising opportunity costs:

- Equipment Sub-Hire
- Crew Sub-Hire
- Transport
- Consumables
- Equipment Purchase

Uses `updateOrCreate` — safe to re-run.

## ProductGroupSeeder

Creates 10 default product groups with sort order:

Audio, Lighting – Generic, Lighting – Moving Heads, Video, Staging, Power, Rigging, Furniture, Transport, Consumables.

Uses `updateOrCreate` — safe to re-run.

## PermissionSeeder

Creates all permissions used by the role-based access control system. Permissions follow the `resource.action` naming convention and are grouped by domain:

| Group | Permissions |
|-------|------------|
| Settings | `settings.view`, `settings.manage` |
| Users | `users.view`, `users.invite`, `users.edit`, `users.deactivate`, `users.activate`, `users.reset-password` |
| Roles | `roles.view`, `roles.manage` |
| Members | `members.view`, `members.create`, `members.edit`, `members.delete` |
| Opportunities | `opportunities.view`, `opportunities.create`, `opportunities.edit`, `opportunities.delete` |
| Invoices | `invoices.view`, `invoices.create`, `invoices.edit`, `invoices.delete` |
| Products | `products.view`, `products.create`, `products.edit`, `products.delete` |
| Stock | `stock.view`, `stock.adjust`, `stock.transfer` |
| Reports | `reports.view`, `reports.export` |
| System | `action-log.view`, `custom-fields.manage`, `static-data.manage`, `webhooks.manage` |

Uses `Permission::findOrCreate()` — safe to re-run.

## RoleSeeder

Creates the four system roles and assigns their default permission sets. Owner access is handled separately via the `is_owner` flag on users — not as a Spatie role.

| Role | Access Level |
|------|-------------|
| **Admin** | All permissions |
| **Manager** | All resource permissions (no settings, users, or roles management) |
| **Operator** | Core operational permissions — opportunities, invoicing, stock, and read-only products/members |
| **Viewer** | Read-only access to all resources |

All roles are marked `is_system = true` and cannot be deleted through the UI. The RoleSeeder calls PermissionSeeder internally, ensuring permissions exist.

## EmailTemplateSeeder

Creates three system email templates:

| Key | Name | Purpose |
|-----|------|---------|
| `user_invited` | User Invitation | Sent when a new user is invited |
| `password_reset` | Password Reset | Sent when a user requests a password reset |
| `test_email` | Test Email | Sent when testing email configuration from admin settings |

Templates support merge fields (e.g. `{{ user.name }}`, `{{ company.name }}`). Uses `updateOrCreate` — safe to re-run.

## NotificationTypeSeeder

Registers notification type definitions via the `NotificationRegistry` and syncs them to the `notification_types` table:

| Key | Category | Default Channels |
|-----|----------|-----------------|
| `user.invited` | Users | database, mail |
| `user.deactivated` | Users | database |
| `user.reactivated` | Users | database |
| `password.reset` | System | mail |
| `system.test_email` | System | mail |

## ViewSeeder

Creates system-managed custom views for each entity type. These views appear in the API and UI view selectors and cannot be deleted by users.

| Entity | Views Created |
|--------|--------------|
| Members | All Members (default), Organisations Only, Contacts Only, Active Venues, Inactive Members |
| Products | All Products (default), Rental Products, Sale Products, Active Products, Inactive Products |
| Stock Levels | All Stock Levels (default), Serialised Stock, Bulk Stock |
| Activities | All Activities (default), Scheduled Activities, Completed Activities |
| Product Groups | All Product Groups (default) |

Uses `updateOrCreate` keyed on name + entity type — safe to re-run.

## RateDefinitionPresetSeeder

Creates the 11 industry-standard rate definition presets from `RatePresets::all()`. Presets are identified by a `preset_slug` and use `updateOrCreate` — safe to re-run. See the [Rate Definitions](/docs/platform/rate-definitions) page for details on each preset.

## ProductSeeder

Creates 14 sample products across rental, sale, and service types to demonstrate the catalogue:

| Group | Products |
|-------|---------|
| Audio | Shure SM58, JBL EON615, Yamaha TF1 Mixing Console |
| Lighting – Moving Heads | Robe Esprite, Martin MAC Aura XB |
| Lighting – Generic | Chauvet COLORado Panel Q40, ETC Source Four 750W |
| Video | Barco UDX-4K32, Samsung 55" LED Display |
| Staging | Stage Deck 8x4, Stage Leg 1m |
| Power | Power Distribution 63A 3-Phase |
| Consumables | Gaffer Tape 50m Black (sale item) |
| (none) | Lighting Technician (service item) |

> **Note:** This seeder is not idempotent — re-running it will create duplicate products. It depends on `ProductGroupSeeder` having run first.

## ActivitySeeder

Creates 6 sample activities (task, call, meeting, email, note, completed) linked to the first user and the first available members. Depends on `UserFactory` having created the test user.

> **Note:** This seeder is not idempotent — re-running it will create duplicate activities.

## StoreSeeder

Creates a default store called "Main Warehouse" if no stores exist. This seeder is **not** included in the default `DatabaseSeeder` — run it separately if needed.

> **Note:** The setup wizard (`php artisan signals:setup`) handles store creation interactively, so this seeder is primarily useful for automated deployments.

## DemoDataSeeder

Creates large volumes of realistic demo data for development and evaluation:

- 3 demo stores (London Warehouse, Manchester Depot, Edinburgh Office)
- 2,000 organisations, 500 venues, and 3,000 contacts — each with a primary email and phone
- Relationships between contacts, organisations, and venues

All demo members are tagged with `demo-data` so they can be identified and cleared later.

This seeder is **not** included in the default `DatabaseSeeder`. Use the Artisan commands below or the admin panel.

```bash
# Seed demo data
php artisan signals:seed-demo

# Remove demo data (deletes all records tagged 'demo-data')
php artisan signals:clear-demo
```

> **Note:** Running `DemoDataSeeder` multiple times will create duplicate stores and members. Use `signals:clear-demo` before re-seeding.

## Running Seeders

```bash
# Run all default seeders
php artisan db:seed

# Run a specific seeder
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=ViewSeeder

# Run in production (requires --force flag)
php artisan db:seed --force
php artisan db:seed --class=PermissionSeeder --force
```

You can also run seeders from the admin panel under **Settings > System > Database Seeders**, which shows the current status of each seeder and lets you run them individually.

## Idempotency

Most seeders use `updateOrCreate` or `findOrCreate` and are safe to run multiple times:

- **CountrySeeder** — `updateOrCreate` on `code`
- **ListOfValuesSeeder** — `updateOrCreate` on list name and value name
- **CurrencySeeder** — `updateOrCreate` on `code`
- **TaxClassSeeder** — `updateOrCreate` on `name`
- **TaxRateSeeder** — `updateOrCreate` on `name`
- **RevenueGroupSeeder** — `updateOrCreate` on `name`
- **CostGroupSeeder** — `updateOrCreate` on `name`
- **ProductGroupSeeder** — `updateOrCreate` on `name`
- **PermissionSeeder** — `Permission::findOrCreate()`
- **RoleSeeder** — `Role::findOrCreate()` and `syncPermissions()`
- **EmailTemplateSeeder** — `updateOrCreate` on `key`
- **NotificationTypeSeeder** — `syncToDatabase()` via registry
- **ViewSeeder** — `updateOrCreate` on name + entity type
- **RateDefinitionPresetSeeder** — `updateOrCreate` on `preset_slug`
- **ProductSeeder** — not idempotent; creates duplicates if re-run
- **ActivitySeeder** — not idempotent; creates duplicates if re-run
- **DemoDataSeeder** — not idempotent; use `signals:clear-demo` before re-seeding
