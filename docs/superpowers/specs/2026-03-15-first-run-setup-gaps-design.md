# First-Run/Setup Gap Fixes

**Date:** 2026-03-15
**Source:** GitHub Discussion #15 audit (`.claude/reviews/audit-first-run-discussion-15.md`)
**Approach:** Single commit, all gaps with stubs for future-dependent items

## Context

Audit of Discussion #15 found 14 missing and 4 partial items. Core setup architecture is solid — these are spec compliance gaps. Items that depend on unbuilt models (Product, Opportunity, Invoice) get stub implementations.

## Changes

### 1. FeatureProfile enum — add 7 missing modules, fix profile mappings
- **File:** `app/Enums/FeatureProfile.php`
- Add: `serialisation`, `credit_notes`, `purchase_orders`, `vehicles`, `quarantines`, `discussions`, `webhooks`
- Fix Dry Hire: remove `crm`, `inspections`
- Fix Minimal: remove `invoicing`, `crm`
- Match Discussion #15 table exactly for all 5 profiles x 16 modules

### 2. CompleteSetup — seed reference data on completion
- **File:** `app/Actions/Setup/CompleteSetup.php`
- Add `seedReferenceData()` calling all 7 seeders idempotently after settings are written
- Call `Artisan::call('config:cache')` instead of just `config:clear`

### 3. CompleteSetup — link admin user to Member
- **File:** `app/Actions/Setup/CompleteSetup.php`
- Create `Member` with `membership_type = 'User'`
- Create `User` with `member_id` → new Member
- Create `Membership` linking member to default store with `is_owner = true`

### 4. Password validation — strengthen to 12 chars
- **Files:** `SignalsSetupCommand.php`, `wizard.blade.php` (Volt)
- Change `min:8` → `min:12` with mixed case, numbers, symbols

### 5. Install command — PG extension check + explicit SIGNALS_SETUP_COMPLETE=false
- **File:** `app/Console/Commands/SignalsInstallCommand.php`
- Call `PostgresConnectionTester::checkExtensions()` after DB connect
- Write `SIGNALS_SETUP_COMPLETE=false` to `.env` in finalization

### 6. Getting-started checklist — expand from 3 to ~8 items
- **File:** `app/Livewire/Dashboard/GettingStartedChecklist.php`
- Add: create product (stub), create opportunity (stub), invite team member, configure email, upload logo

### 7. DemoDataSeeder — add tagging + stubs + fix clear command
- **Files:** `database/seeders/DemoDataSeeder.php`, `app/Console/Commands/SignalsClearDemoCommand.php`
- Tag all demo records for bulk identification/deletion
- Add stub seeders for products, opportunities, invoices, custom fields, activities
- Fix clear command to remove ALL demo data (members, emails, phones, relationships), not just stores

## Verification

1. `php artisan test --parallel --exclude-group=env-writing` — all existing tests pass
2. `php artisan test --group=env-writing` — env-writing tests pass
3. `vendor/bin/pint --dirty`
4. `vendor/bin/phpstan analyse`
5. Manual: `php artisan signals:install` checks PG extensions and writes SIGNALS_SETUP_COMPLETE=false
6. Manual: web wizard CompleteSetup seeds reference data and creates Member-linked admin
