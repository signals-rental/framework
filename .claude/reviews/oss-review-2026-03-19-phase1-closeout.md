# OSS Review — 2026-03-19 (Phase 1 Close-Out)

## Scope
97 files across PHP (app, tests, routes, database), Blade/CSS (views), and config. Implements Phase 1 close-out: custom views polish (ColumnRegistry + custom fields, column validation, sparse API responses), multi-currency completion (exchange rate triangulation, currency/exchange rate API), and file & attachment Phase 2 (attachment API, icon upload component).

### Files Reviewed
**PHP — app/ (40 files):** Actions (7), Data/DTOs (8), Controllers (4), Livewire (2), Models (6), Policies (4), Services (5), Views (3), Enums (1)
**PHP — tests/ (20 files):** Feature tests across API, Livewire, Models, Services, Views, Policies, Seeders, Enums
**PHP — other (10 files):** routes/api.php, migrations (6), factories (4), seeders (2)
**Blade/CSS (7 files):** icon-upload, view-builder, view-selector, form, data-table, toolbar, index
**Config (4 files):** CLAUDE.md, composer.json, composer.lock, phpstan.neon

## Findings

### Critical
- **RESOLVED:** `UpdateExchangeRateData` missing `after:effective_at` validation on `expires_at` — fixed
- **PRE-EXISTING:** `view-builder.blade.php` has inline styles defining component-level styling (breaks `s-` tokens contract) — pre-existing file, not introduced by this PR

### Important
- **RESOLVED:** Column validation duplicated between `CreateCustomView` and `UpdateCustomView` — extracted to `ColumnRegistryResolver::validateColumns()`
- **RESOLVED:** Triangulation in `CurrencyService` could recurse infinitely — added `$allowTriangulation` parameter
- **RESOLVED:** `IconUpload` had model class injection risk — added allowlist, Gate authorization, S3 cleanup
- **RESOLVED:** `AttachmentController::store()` missing null guard on `$request->file()` — added check
- **PRE-EXISTING:** `CustomViewController` missing `authorizeApi()` calls (Sanctum token ability check skipped)
- **PRE-EXISTING:** `ViewResolver` doesn't enforce visibility on explicit `view_id`
- **PRE-EXISTING:** `view-builder.blade.php` uses raw HTML form inputs instead of `<flux:*>` components

### Suggestions
- `CreateAttachment` morph map could use `Relation::morphMap()` in a service provider (future scalability)
- Permission-only policies (11 total) could benefit from a shared `AuthorizesByPermission` trait
- `MemberController::filterResponseByView()` could be extracted to a service if other entities need sparse responses
- Action classes (`Actions/ExchangeRates/*`, `Actions/Attachments/*`, `Actions/Views/*`) lack class-level PHPDoc
- API controllers missing `@response` PHPDoc tags for Scramble OpenAPI generation

## Documentation Status

| Layer | Status | Details |
|-------|--------|---------|
| User docs | Missing | No docs pages for currencies, exchange rates, attachments, or custom views APIs |
| API docs (Scramble) | Needs update | All 4 new controllers have basic comments but missing `@param`/`@response` tags |
| Code PHPDoc | Partial | Services and ColumnRegistry well-documented; action classes and DTOs minimal |

## Component Library Compliance

- `icon-upload.blade.php` — **Compliant** (uses `s-btn`, design tokens correctly)
- `view-selector.blade.php` — **Compliant** (uses `s-dropdown`, `s-btn-split` correctly)
- `members/form.blade.php` — **Compliant** (uses `<x-signals.*>`, `<flux:*>` correctly)
- `view-builder.blade.php` — **Pre-existing issues** (inline component styles, raw HTML inputs)

## Test Coverage

| Area | Status | Gap |
|------|--------|-----|
| ColumnRegistryResolver | Partial (60%) | `register()` method untested (plugin extensibility, low priority) |
| ColumnRegistry | 90.91% | At threshold — `mapSchemaType` private method partial coverage |
| CurrencyService | 97.22% | Adequate |
| FileService | 97.30% | Adequate |
| ViewResolver | 98.57% | Adequate |
| All other new classes | 100% | Fully covered |
| Overall (2118 tests) | Pass | All pass, 11 skipped |

## Agent Reviews Dispatched

| Agent | Key Findings |
|-------|-------------|
| silent-failure-hunter | 10 findings; 5 fixed (null guard, triangulation logging, IconUpload security, S3 cleanup, bcdiv zero check) |
| code-reviewer | 7 findings; 2 fixed (triangulation recursion, sort_column validation); 5 pre-existing |
| simplicity/duplication | 7 findings; 2 fixed (column validation extraction, expires_at bug); rest are suggestions |
| documentation | User docs missing for 4 APIs; Scramble PHPDoc minimal on all controllers |
| component-library | icon-upload and view-selector compliant; view-builder has pre-existing issues |
| test-analyzer | Pending |
| type-design-analyzer | Pending |

## Resolution Status

All **Critical** and **Important** findings from this PR's changes have been resolved:
- Column validation extracted to `ColumnRegistryResolver::validateColumns()`
- Triangulation recursion guard added (`$allowTriangulation` parameter)
- `UpdateExchangeRateData` expires_at validation fixed
- IconUpload secured (model allowlist, Gate auth, S3 cleanup, error handling)
- AttachmentController null file guard added
- Exchange rate bcdiv zero guard added
- Triangulation exceptions logged via `report()`

Pre-existing issues flagged for follow-up:
- CustomViewController Sanctum ability checks
- ViewResolver visibility on explicit view_id
- view-builder.blade.php component library compliance
- User documentation for new APIs
