---
title: Seeders
description: What data gets seeded by default and how to run individual seeders.
---

Signals ships with four database seeders that populate your application with permissions, roles, a default store, and optional demo data. The default `php artisan db:seed` command runs the essential seeders automatically.

## What Runs by Default

The `DatabaseSeeder` calls these seeders in order:

| Seeder | Purpose |
|--------|---------|
| `PermissionSeeder` | Creates all system permissions for role-based access control |
| `RoleSeeder` | Creates the four system roles with their permission sets |

It also creates a test user (`test@example.com`) for development.

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

This seeder uses `Permission::findOrCreate()`, making it safe to run multiple times.

## RoleSeeder

Creates the four system roles and assigns their default permission sets. Owner access is handled separately via the `is_owner` flag on users — not as a Spatie role.

| Role | Access Level |
|------|-------------|
| **Admin** | All permissions |
| **Manager** | All resource permissions (no settings, users, or roles management) |
| **Operator** | Core operational permissions — opportunities, invoicing, stock, and read-only products/members |
| **Viewer** | Read-only access to all resources |

All roles are marked as system roles (`is_system = true`) and cannot be deleted through the UI. The RoleSeeder calls PermissionSeeder internally, so running it alone ensures permissions exist.

## StoreSeeder

Creates a default store called "Main Warehouse" if no stores exist. This seeder is **not** included in the default `DatabaseSeeder` — run it separately if needed.

> **Note:** The setup wizard (`php artisan signals:setup`) handles store creation interactively, so this seeder is primarily useful for automated deployments.

## DemoDataSeeder

Creates three demo stores for testing and evaluation:

- London Warehouse
- Manchester Depot
- Edinburgh Office

This seeder is **not** included in the default `DatabaseSeeder`. Run it separately when you want sample data for development or demos.

## Running Seeders

```bash
# Run all default seeders (PermissionSeeder + RoleSeeder + test user)
php artisan db:seed

# Run a specific seeder
php artisan db:seed --class=StoreSeeder
php artisan db:seed --class=DemoDataSeeder

# Run in production (requires --force flag)
php artisan db:seed --force
php artisan db:seed --class=PermissionSeeder --force
```

You can also run seeders from the admin panel under **Settings > System > Database Seeders**, which shows the current status of each seeder and lets you run them individually.

## Idempotency

All seeders are designed to be run multiple times safely:

- **PermissionSeeder** uses `Permission::findOrCreate()` — existing permissions are not duplicated
- **RoleSeeder** uses `Role::findOrCreate()` and `syncPermissions()` — roles are created or updated
- **StoreSeeder** checks `Store::query()->exists()` — skips if any store already exists
- **DemoDataSeeder** creates stores unconditionally — running it multiple times will create duplicates
