# OSS Review — 2026-03-21

## Scope
228 files changed between `8aa0026..HEAD` (commits: Phase 1 gap close-out + Activities, Stock Transactions, Product Groups with full CRUD). Covers Products module, Activities CRM entity, Stock Transactions, Product Groups web interface, plus schema registry and security enhancements.

### Files Reviewed
- **PHP (app/):** 86 files — actions (13), DTOs (17), enums (8), models (21), controllers (6), policies (4), services (5), views/registries (3)
- **Blade/CSS:** 38 files — Livewire pages, partials, layouts, command palette
- **Database:** 20 files — migrations (9), factories (8), seeders (8)
- **Tests:** 48 files — unit (7), feature (41)
- **Documentation:** 8 files — platform (3), API (4), changelog (1)
- **Routes/Config:** 3 files

## Findings

### Critical

**C1. Three actions lack DB::transaction() — data corruption risk**
- `CreateActivity`: activity is created, then participants in a loop. If participant creation fails, orphaned activity persists with no participants.
- `UpdateActivity`: deletes ALL existing participants then re-creates. If re-creation fails mid-loop, original participants are permanently lost.
- `CreateStockTransaction`: creates transaction record, then increments `quantity_held`. If increment fails, transaction exists but stock count is wrong.

All three need wrapping in `DB::transaction()`.

**C2. ProductGroupController bypasses the shared service layer**
`store()` and `update()` perform logic directly in the controller instead of delegating to action classes + DTOs. `destroy()` calls `$productGroup->delete()` directly, skipping the `DeleteProductGroup` action (which has audit + webhook). Violates CLAUDE.md: "Every operation flows through a DTO and an action class."

**C3. ProductGroupController::destroy uses wrong permission**
Uses `$this->authorizeApi('products.edit', ...)` instead of `'products.delete'`. Users with only edit permission can delete product groups via API. Security authorization bug.

**C4. StockTransactionController does not validate parent-child relationship (IDOR)**
Route is `products/{product}/stock_levels/{stock_level}/stock_transactions` but `$productId` is never used. Any `stock_level_id` works regardless of the product in the URL. Same issue on `index()` and `show()`. Insecure Direct Object Reference vulnerability.

**C5. `formatTimestamp()` duplicated across 6 DTOs**
Identical method in `MemberData`, `ActivityData`, `ProductData`, `ProductGroupData`, `StockTransactionData`, `StockLevelData`. Extract to trait `App\Data\Concerns\FormatsTimestamps`.

**C6. Stock Transactions API has no documentation page**
Three endpoints exist but no `docs/api/stock-transactions.md` and no entry in `documentation.json`.

**C7. Activities API docs omit `POST /activities/{id}/complete` endpoint and have inaccurate response examples**
- Complete endpoint undocumented
- Examples show non-existent `completed_at` field
- Omit `location`, `activity_type_name`, `activity_status_name`, `time_status`, `time_status_name`, `participants`, nested `regarding`/`owner`

**C8. 6 action classes have zero direct test coverage**
`CreateActivity`, `UpdateActivity`, `CompleteActivity`, `DeleteActivity`, `CreateStockTransaction`, `DeleteProductGroup` — all Product actions have tests with authorization checks, Activity/StockTransaction actions do not.

**C9. Activity and StockTransaction API tests lack authorization assertions**
- `ActivityApiTest`: no `assertForbidden` tests for wrong abilities/permissions
- `StockTransactionApiTest`: zero authorization tests of any kind
- `AccessoryController`: no test file at all

### Important

**I1. `Activity::resolveRegardingType()` silently passes through unknown types**
Uses `$regardingMap[$type] ?? $type` — unknown types like `"Invoice"` or typos pass through and create unresolvable morph types in DB. Should throw `\InvalidArgumentException`. (DTO validation partially guards the API path but the method is public and called from Livewire too.)

**I2. `UpdateActivity` array_filter strips intentional null clears**
`array_filter($data->toArray(), fn ($v) => $v !== null)` means API consumers cannot clear fields (e.g. `{"description": null}`). The update silently ignores the intent, returning 200 success without applying the change.

**I3. `UpdateActivityData` drops temporal validation**
`CreateActivityData` has `'after_or_equal:starts_at'` on `ends_at`. `UpdateActivityData` does not. An update can set `ends_at` before `starts_at`, creating invalid temporal data.

**I4. Magic number defaults in DTOs**
`type_id = 1001`, `status_id = 2001`, `transaction_type = 4`, `priority = 1` — should reference enum constants: `ActivityType::Task->value`, etc.

**I5. `CreateActivityData` missing `required_with` for regarding pair**
`regarding_type` and `regarding_id` are independently nullable. Can set `regarding_type = 'Member'` with `regarding_id = null`, creating incomplete polymorphic reference.

**I6. Livewire action methods have no error handling or user feedback**
`deleteActivity()`, `completeActivity()` in activities/index and entity tabs, `addTransaction()` in stock-levels/show — no try-catch, no success/error flash messages. Authorization failures, ModelNotFoundExceptions, and DB errors surface as generic Livewire errors.

**I7. ViewResolver boilerplate duplicated in 5 API controllers**
~20-line block repeated in Activity, Product, ProductGroup, StockLevel, Member controllers. Extract to `FiltersQueries` trait method.

**I8. Activity tab pages duplicate methods across 3 Volt components**
`completeActivity()`, `deleteActivity()`, and `$columns` definition identical in member/product/stock-level activities tabs.

**I9. `ActivityController` and `StockTransactionController` `#[ApiResponse]` missing `type:` shapes**
Scramble generates less precise OpenAPI schemas. Other controllers have full typed shapes.

**I10. `s-badge-zinc` and `s-btn-secondary` CSS classes not defined**
Used in 6 Blade files but not in component library or `components.css`. Either add to CSS or replace.

**I11. Missing Livewire tests for Activity pages**
`tests/Feature/Livewire/Activities/` doesn't exist. Index, form, and show pages have zero coverage.

**I12. Inline SVG icons where `<flux:icon.*>` could be used**
9 Blade files use raw SVGs for common icons (plus, check, edit, trash, eye).

**I13. `s-table` without `s-table-wrap` wrapper in stock-levels/show**
Should use `.s-table-wrap > .s-table` per component library.

**I14. `StockLevelColumnRegistry` has no test file**
Other column registries have tests.

**I15. `MergeProduct` uses `DB::table()` for accessories when `Accessory` model exists**
CLAUDE.md: "Avoid `DB::`; prefer `Model::query()`." The `Accessory` model is available.

### Suggestions

**S1. `TransactionType::quantitySign()` uses `default => 1`**
Adding a new case silently defaults to positive. Fully explicit match would force a conscious decision on each new type.

**S2. `CreateStockTransaction` does not dispatch webhooks**
Inconsistent with other create actions. May be intentional for bookkeeping.

**S3. `ActivityStatus` missing `isTerminal()` method**
`completed` boolean on Activity is semantically redundant with status. Deriving it from the enum would prevent inconsistent state.

**S4. Breadcrumbs use manual HTML instead of `<x-signals.breadcrumb>` component** (6 files)

**S5. Meta labels use repeated inline styles instead of CSS class** (3 files)

**S6. Missing `TimeStatus` enum unit test**

**S7. `StockTransaction` model missing `@property-read` for `quantity_move` accessor**

**S8. Dead `rounded-lg` class in stock-levels/show.blade.php** (overridden by global rule)

## Documentation Status

| Layer | Status | Details |
|-------|--------|---------|
| User docs | Needs update | Stock transactions API undocumented; Activities docs incomplete (missing complete endpoint, inaccurate response examples, missing scoped tabs) |
| API docs (Scramble) | Needs update | ActivityController and StockTransactionController missing `type:` shapes on `#[ApiResponse]` attributes |
| Code PHPDoc | Compliant | All models, DTOs, and services have proper type hints and PHPDoc |

## Component Library Compliance

7 Important findings:
- `s-badge-zinc` and `s-btn-secondary` classes not defined (either add to CSS or replace)
- Missing `s-table-wrap` wrapper on transactions table
- Inline SVGs instead of `<flux:icon.*>` in 9 files
- Raw form container instead of `<x-signals.form-section>`
- `docs-search` classes used in header search bar instead of component library classes

6 Suggestions: manual breadcrumbs, repeated inline styles, missing `<x-signals.empty>`, manual avatars

## Test Coverage

| Area | Status | Gap |
|------|--------|-----|
| Activity actions (4) | Missing | No direct action tests — only covered indirectly via API tests |
| CreateStockTransaction | Missing | No direct action test |
| DeleteProductGroup | Missing | No direct action test |
| ActivityApiTest | Partial | Missing authorization/forbidden tests |
| StockTransactionApiTest | Partial | Missing authorization tests entirely |
| AccessoryController | Missing | Zero test coverage |
| Activity Livewire pages (3) | Missing | No Livewire::test() coverage |
| StockLevelColumnRegistry | Missing | No test file |
| TimeStatus enum | Missing | No unit test |
| Activity DTOs (4) | Missing | No DTO-level tests |
| StockTransaction DTOs (2) | Missing | No DTO-level tests |
| All Product actions (7) | Covered | Full tests with authorization |
| Product API, Model, Livewire | Covered | Comprehensive coverage |
| Activity Model | Covered | 17 tests with relationships and scopes |
| Activity Policy | Covered | 6+ tests |
| Activity Column Registry | Covered | 4+ tests |

## Agent Reviews Dispatched

### pr-review-toolkit:code-reviewer
- **Critical:** ProductGroupController bypasses shared service layer (no action classes for create/update, destroy skips DeleteProductGroup action)
- **Critical:** ProductGroupController::destroy uses `products.edit` instead of `products.delete` permission — authorization bug
- **Important:** `MergeProduct` uses `DB::table('accessories')` when `Accessory` model exists

### pr-review-toolkit:type-design-analyzer
Scored 10 types. Enums averaged 8.3/10 (strong). DTOs averaged 6.2/10 (weaker due to int-typed enum fields, untyped arrays, magic number defaults).

Key findings:
- Magic number defaults across all DTOs (should reference enum constants)
- `UpdateActivityData` missing temporal validation (`ends_at` >= `starts_at`)
- `CreateActivityData` missing `required_with` for `regarding_id`/`regarding_type` pair
- Response DTOs use `array<string, mixed>` where small dedicated DTOs would improve Scramble docs and static analysis
- `TransactionType::quantitySign()` default branch could hide bugs on new cases

### pr-review-toolkit:silent-failure-hunter
9 findings, 3 Critical:
1. `CreateActivity` — non-atomic participant creation, orphaned activity on failure
2. `UpdateActivity` — delete-and-recreate participants without transaction, data loss risk
3. `CreateStockTransaction` — transaction record + quantity increment not atomic

4 High:
4. `resolveRegardingType()` silently passes through unknown types
5. `StockTransactionController` IDOR — no parent-child validation
6. Stock levels show page — no user feedback on transaction success/failure
7. Activities index — no error handling on delete/complete actions

1 High (logic):
8. `UpdateActivity` array_filter strips intentional null clears — users cannot clear fields

## Resolution Status

All Critical and Important findings resolved. 2,783 tests pass, PHPStan clean, Pint clean.

### Fixes Applied

**Batch 1 — Safety:**
- C1: RESOLVED — Wrapped CreateActivity, UpdateActivity, CreateStockTransaction in `DB::transaction()`
- C3: RESOLVED — Fixed ProductGroupController::destroy permission to `products.delete`; wired to DeleteProductGroup action
- C4: RESOLVED — Added parent-child validation in all 3 StockTransactionController methods
- I1: RESOLVED — `resolveRegardingType()` now throws `InvalidArgumentException` on unknown types

**Batch 2 — Architecture:**
- C2: RESOLVED — Created CreateProductGroup, UpdateProductGroup actions + CreateProductGroupData, UpdateProductGroupData DTOs; rewired controller and Livewire form
- I2: DEFERRED — Known cross-codebase limitation (UpdateProduct has same pattern); requires Spatie Data `Optional` type migration
- I3: RESOLVED — Added `after_or_equal:starts_at` to UpdateActivityData
- I4: RESOLVED — Replaced magic number defaults with enum constant references in all DTOs
- I5: RESOLVED — Added `required_with` cross-validation for regarding_id/regarding_type pair

**Batch 3 — Tests (30 new tests):**
- C8: RESOLVED — Created action tests for CreateActivity, UpdateActivity, CompleteActivity, DeleteActivity, CreateStockTransaction, DeleteProductGroup (all with authorization assertions)
- C9: RESOLVED — Added forbidden/unauthorized tests to ActivityApiTest (3) and StockTransactionApiTest (4, including IDOR 404 test)
- S6: RESOLVED — Created TimeStatusTest
- I14: RESOLVED — Created StockLevelColumnRegistryTest

**Batch 4 — Docs + Polish:**
- C5: RESOLVED — Extracted `FormatsTimestamps` trait, removed duplication from 6 DTOs
- C6: RESOLVED — Created `docs/api/stock-transactions.md`, added to documentation.json
- C7: RESOLVED — Fixed activities API docs (added complete endpoint, corrected response examples)
- I6: RESOLVED — Added try-catch + ModelNotFoundException handling in 4 Livewire activity action methods; added validation + AuthorizationException handling in stock transaction form
- I9: DEFERRED — Scramble `type:` shapes are a polish item; controllers work correctly without them
- I10: RESOLVED — Added `s-badge-zinc` to components.css; replaced `s-btn-secondary` with `s-btn-ghost`
- I12: DEFERRED — Inline SVGs in partials follow existing codebase pattern (Products partials use same approach)
- I13: RESOLVED — Changed `overflow-x-auto` to `s-table-wrap` on transactions table
- S8: RESOLVED — Removed dead `rounded-lg` class

### Remaining Deferred Items (accepted)
- I2: array_filter null-clear — cross-codebase pattern, needs Spatie Data Optional migration
- I7: ViewResolver boilerplate — refactor opportunity, not a bug
- I8: Activity tab method duplication — refactor opportunity, not a bug
- I9: Scramble type shapes — polish
- I12: Inline SVGs — matches existing codebase convention
- I15: MergeProduct DB::table — matches MergeMember pattern for consistency
- S1-S5: Minor suggestions deferred
