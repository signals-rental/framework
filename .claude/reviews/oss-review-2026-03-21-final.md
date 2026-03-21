# OSS Review — 2026-03-21 (Final)

## Scope
230 files across `915073a^..HEAD` + uncommitted fixes + UI bug fixes. Covers Products module, Activities CRM entity, Stock Transactions, Product Groups web, Phase 1 gap close-out, OSS review fixes, and UI bug fixes.

### Files Reviewed
- **PHP (app/):** 87 files — actions (15), DTOs (18), enums (8), models (21), controllers (7), policies (4), services (5), views/registries (3), traits (1), middleware (1)
- **Blade/CSS:** 42 files — Livewire pages (20), partials (15), layouts (2), command palette (1), CSS (1)
- **Database:** 20 files — migrations (9), factories (9), seeders (9)
- **Tests:** 58 files — unit (8), feature (50)
- **Documentation:** 10 files — platform (3), API (5), changelog (1), documentation.json (1)
- **Routes/Config:** 3 files

## Findings

### Critical

**C1. Missing tests for `CreateProductGroup` and `UpdateProductGroup` actions**
Both action classes exist but have no corresponding test files with happy path + authorization tests.

**C2. Missing `AccessoryApiTest.php`**
`AccessoryController.php` has 3 endpoints (index, store, destroy) with zero test coverage.

**C3. Missing Livewire tests for Activities domain**
`tests/Feature/Livewire/Activities/` directory does not exist. Index, show, and form pages have interactive actions (complete, delete) that are untested at the Livewire layer.

### Important

**I1. `s-badge-zinc` not documented in `framework-plans/component-library.md`**
Used in 25+ Blade files across the codebase but absent from the component reference.

**I2. Activities platform docs incomplete**
Missing: edit route, full form field listing, permissions section, product/stock-level activity tab cross-references.

**I3. Products platform docs missing Activities tab**
The Tabs table lists Overview, Stock, Accessories, Custom Fields, Files — but not Activities.

**I4. Stock-levels platform docs missing transactions panel and Activities tab**
The show page has a full transactions panel with inline form — undocumented.

**I5. Activities API docs field name mismatch**
Create request example shows `owner_id` but the actual DTO field is `owned_by`. API consumers sending `owner_id` would get silent failure.

**I6. `ActivityController` and `StockTransactionController` missing Scramble `type:` shapes**
All 9 `#[ApiResponse]` attributes lack `type:` parameter. Other controllers have full typed shapes.

**I7. `CompleteActivity` loads identical relations twice**
Lines 26 and 29 both call `->load(['owner', 'participants.member'])`. Second call is redundant.

**I8. `MergeProduct` uses `DB::table()` instead of `Accessory` model**
Violates CLAUDE.md convention: "Never use `DB::` facade; always use `Model::query()`."

**I9. Missing Livewire tests for ProductGroups domain**
`tests/Feature/Livewire/ProductGroups/` does not exist.

**I10. WebhookService import style inconsistent**
6 files use `use` import, 19 use inline FQCN `\App\Services\Api\WebhookService::class`.

**I11. Activity tab Blade files duplicate `completeActivity()`/`deleteActivity()` across 3 components**
Members, products, stock-levels activity tabs have identical PHP methods.

**I12. ViewResolver boilerplate duplicated across 4+ API controllers**
~25-line block copy-pasted in Activity, Product, ProductGroup, StockLevel controllers. Could be extracted to `FiltersQueries` trait.

### Suggestions

**S1.** No `exists` validation on `regarding_id` against the polymorphic target table
**S2.** Create/Update Activity DTO rules ~95% duplicated — could extract shared `baseRules()`
**S3.** Dead `rounded-lg` classes in 5 product view files (overridden by global rule)
**S4.** `CreateStockTransaction` and `MergeProduct` missing webhook dispatch (may be intentional)
**S5.** StockTransactionController repeats parent-child lookup 3 times — could extract to private method

## Documentation Status

| Layer | Status | Details |
|-------|--------|---------|
| User docs | Needs update | Activities/Products/Stock-levels platform docs have gaps (permissions, tabs, transactions panel) |
| API docs (Scramble) | Needs update | Activity + StockTransaction controllers missing `type:` shapes; Activities API has `owner_id`/`owned_by` field name error |
| Code PHPDoc | Compliant | All models, DTOs, services properly documented with types and PHPDoc |

## Component Library Compliance

- `s-badge-zinc`: defined in CSS, used correctly, but **not in component-library.md**
- `s-btn-secondary` usage: eliminated (replaced with `s-btn-ghost`)
- `s-table-wrap`: correctly used on transactions table
- No `s-` tokens in `<style>` blocks
- `<x-signals.*>` and `<flux:*>` components used consistently
- Dead `rounded-lg` in 5 product views (suggestion — global rule overrides)

## Test Coverage

| Area | Status | Gap |
|------|--------|-----|
| Activity actions (4) | Covered | Happy path + auth tests |
| Product actions (7) | Covered | Happy path + auth tests |
| CreateProductGroup action | **Missing** | No test file |
| UpdateProductGroup action | **Missing** | No test file |
| CreateStockTransaction | Covered | Happy path + auth + quantity update |
| DeleteProductGroup | Covered | Happy path + auth |
| ActivityApiTest | Covered | CRUD + auth + CRMS shape |
| StockTransactionApiTest | Covered | CRUD + auth + IDOR |
| ProductApiTest | Covered | Full coverage |
| AccessoryController | **Missing** | Zero test coverage |
| Activity Livewire pages | **Missing** | No directory |
| ProductGroup Livewire pages | **Missing** | No directory |
| Product Livewire pages | Covered | Index, form, show tests |
| StockLevel Livewire pages | Partial | Index test only |
| All enums | Covered | All 8 have unit tests |
| All column registries | Covered | All 3 have tests |
| All policies | Covered | Activity, Product, ProductGroup, StockLevel |
| All models | Covered | Relationships + scopes tested |

## Agent Reviews Dispatched

- **Phase 2 (Simplicity):** FormatsTimestamps trait clean; actions single-responsibility; WebhookService import inconsistency; activity tab duplication; ViewResolver boilerplate duplication
- **Phase 3 (Docs):** Platform docs need Activities permissions, Products Activities tab, Stock-levels transactions panel; API docs field name error (owner_id → owned_by)
- **Phase 4+5 (Components + Tests):** `s-badge-zinc` undocumented; 3 critical test gaps (ProductGroup actions, AccessoryApi, Activity Livewire)
- **pr-review-toolkit:code-reviewer:** Dispatched (timed out)
- **pr-review-toolkit:silent-failure-hunter:** Dispatched (timed out)

## UI Bug Fixes Applied This Session

1. **New Stock Level button on products/{product}/stock:** Created `stock-levels/form.blade.php`, added create/edit routes, product-scoped stock toolbar with `?product_id=` pre-fill
2. **Custom views dropdown on products/stock-levels/activities:** Fixed `DataTable::getColumnRegistry()` to handle all entity types (was hardcoded to members only); fixed stock-levels entity-type from `stock-levels` to `stock_levels` (underscore); fixed view-selector fallback text to be dynamic
3. **Product icons on /products list:** Updated `column-name.blade.php` to render product icons via FileService signed URLs with cube SVG fallback

## Resolution Status

### Resolved in this session
- DB::transaction wrapping (3 actions)
- ProductGroupController architecture (actions + DTOs)
- Permission bug (products.delete)
- IDOR protection (StockTransactionController)
- resolveRegardingType throws on unknown
- formatTimestamp trait extraction
- DTO validation fixes (magic numbers, required_with, temporal)
- Livewire error handling (try-catch)
- Component library (s-badge-zinc CSS, s-btn-secondary replaced, s-table-wrap)
- Stock level create form + routes
- Custom views dropdown fix (DataTable + entity-type)
- Product icon rendering on list page

### Remaining (to fix)

**Critical:**
- **C1:** CreateProductGroup + UpdateProductGroup action tests
- **C2:** AccessoryApiTest
- **C3:** Activity Livewire tests
- **C4:** `CreateProduct`, `UpdateProduct`, `CreateStockLevel`, `UpdateStockLevel` lack `DB::transaction()` (multi-step create + syncCustomFields)
- **C5:** `SchemaController::MODEL_MAP` missing new models (Product, ProductGroup, StockLevel, Activity) — schema API returns 404 for them

**Important:**
- **I1:** Add s-badge-zinc to component-library.md
- **I2-I5:** Platform + API docs corrections (owner_id→owned_by, missing tabs, missing transactions panel)
- **I6:** Scramble type shapes on 2 controllers
- **I7:** Remove redundant load() in CompleteActivity
- **I8:** MergeProduct use Accessory model instead of DB::table
- **I9:** MergeProduct + CreateStockTransaction missing webhook dispatch
- **I10:** DeleteActivity not wrapped in DB::transaction (fires audit+webhook before delete)
- **I11-I13:** Livewire tests, import consistency, code dedup (refactor items)

**Medium (from silent-failure-hunter):**
- Livewire activity catch blocks dispatch success events on ModelNotFound — should show warning instead
- `shortRegardingType()` silently falls back for unknown classes (should match `resolveRegardingType` strictness)
- `VisibilityRuleEvaluator` silently returns `true` for unknown operators — should log warning
- `IconUpload::getThumbDisplayUrlProperty()` catches all `\Throwable` without logging — add `report($e)`
