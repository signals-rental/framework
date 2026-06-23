# RMS-unified opportunity line-items + local-first editor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Re-platform the opportunity line-item spine onto a single Current-RMS-faithful event-sourced model (`item_type` + materialized `path` + `revenue_group_id`) and rebuild the Overview editor on the local-first prototype.

**Architecture:** One `opportunity_items` table holds every row (group/product/accessory/service); nesting+order live in a materialized `path`; `path` is replay-stable via a new `ItemsRestructured` event. Big-bang cutover: editor + API + actions switch together, demo data wiped & re-seeded, full 3-lane suite re-greened at the end.

**Tech Stack:** PHP 8.4 / Laravel 12 / Livewire 4 + Volt / hirethunk/verbs (event sourcing) / Alpine + Dexie (IndexedDB) / Pest 4 / PostgreSQL.

**Design spec:** `docs/superpowers/specs/2026-06-22-rms-unified-line-items-design.md` (read it first — this plan implements it).

## Global Constraints

- **Big-bang reality:** This is a single cutover. The full suite is intentionally RED from the first P2 morph-rename until P6 re-greens it. Per-task tests below assert what is *locally* meaningful at that point (enum behaviour, migration up/down, model casts, single-event replay); **cross-cutting green is a P6 deliverable, not a per-task one.** Do NOT try to keep the whole suite green mid-flight.
- **Money:** integer minor units in DB; decimal strings in API; never floats/DECIMAL. `brick/money` + `finller/laravel-money` casts.
- **PostgreSQL only.** JSONB not JSON. Functional indexes. Migrations re-declare all existing column attributes when modifying.
- **Verbs invariants:** wrap `fire()` + `Verbs::commit()` in one `DB::transaction` (`CommitsVerbsEvents`). `apply()` is pure single-state. `Event::metadata()` does not round-trip after replay — read raw `verb_events.metadata`. Skip side-effects (demand sync, audit) under `Verbs::unlessReplaying()`.
- **Statuses/transitions are config-driven, never hardcoded** (standing Ben steer).
- **PK is app-assigned** (`SequenceAllocator`) — `opportunity_items.id` does not auto-increment.
- **Don't auto-commit / push** — Ben gates. Commit messages end with the Co-Authored-By trailer.
- **Final gate (P6):** `vendor/bin/pint --dirty` · `vendor/bin/phpstan analyse` (NO path args) · 3-lane suite (SQLite `--parallel --testsuite=Unit,Feature --exclude-group=env-writing </dev/null` · pgsql `--group=pgsql` sequential · env-writing sequential).
- **Model routing:** implementation tasks run on Opus (xhigh/max effort); Fable orchestrates + verifies; reviews use Claude tooling (not Codex).
- **Reference, do not delete yet:** `editor-local-first.blade.php` + its route + `PrototypeEditorService`/table/model/enum stay as the design reference until P5 is confirmed by Ben.

---

## File Structure (what changes)

**New:**
- `app/Enums/OpportunityItemType.php` — structural role enum (group/product/accessory/service).
- `database/migrations/<ts>_cutover_unified_opportunity_line_items.php` — the re-platform migration.
- `app/Verbs/Events/Opportunities/ItemsRestructured.php` — the tree event.
- `app/Verbs/Events/Opportunities/ItemRenamed.php` — rename event.
- `app/Actions/Opportunities/RestructureOpportunityItems.php`, `AddOpportunityGroup.php`, `AddOpportunityAccessory.php`, `RenameOpportunityItem.php`.
- `app/Services/Opportunities/ItemPathService.php` — path build/clamp algorithm (ported from `PrototypeEditorService::persistTree`), shared by action + tests.
- New Volt component `resources/views/livewire/opportunities/line-items.blade.php` (production local-first editor; replaces `items.blade.php`).

**Modified:**
- `app/Models/OpportunityItem.php` — rename morph (`itemable_*`), add `item_type`/`path`/`revenue_group_id`, drop `section_id`/`sort_order`, `isProductBacked()` keys off `itemable_type`, `defineSchema()` RMS columns, path helpers.
- `app/Verbs/States/OpportunityItemState.php` — add `item_type`/`path`/`revenue_group_id`/`itemable_type`/`itemable_id`.
- `app/Verbs/Events/Opportunities/ItemAdded.php` — carry new fields; gate pricing/demand on product-backing.
- `app/Verbs/Events/Opportunities/ItemRemoved.php` — subtree cascade.
- Consumers reading the morph: `OpportunityItemDemandResolver`, `AvailabilityService`, `ShortageDetector`/probes, asset events — `item_type`/`item_id` → `itemable_type`/`itemable_id`.
- `app/Data/Opportunities/OpportunityItemData.php` + `AddOpportunityItemData.php` — RMS shape.
- `app/Http/Controllers/Api/V1/OpportunityController.php` + `SchemaController.php` + `routes/api.php` — `/items/tree`, group/accessory create, drop section.
- `app/Models/Opportunity.php` — drop `sections()`; keep version-scoped `items()`.
- Seeders (`database/seeders/*Opportunity*`) — unified model.
- `resources/views/livewire/opportunities/show.blade.php` — mount the new editor component.

**Removed (P5, after Ben confirms):**
- `OpportunitySection` model, `OpportunitySectionData`, 6 section actions, section migrations folded into cutover, `items.blade.php`, prototype scaffolding.

---

## PHASE 1 — Backend spine (DETAILED)

> P1 deliverable: the unified schema + model + state exist and round-trip. The wider suite is red after this (expected); P1's own tests pass.

### Task 1: `OpportunityItemType` enum

**Files:**
- Create: `app/Enums/OpportunityItemType.php`
- Test: `tests/Unit/Enums/OpportunityItemTypeTest.php`

**Interfaces:**
- Produces: `OpportunityItemType` (string-backed enum) with cases `Group='group'`, `Product='product'`, `Accessory='accessory'`, `Service='service'`; methods `isPriceable(): bool` (false for Group), `generatesDemand(): bool` (true for Product+Accessory).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\OpportunityItemType;

it('exposes the four RMS line-item roles', function () {
    expect(OpportunityItemType::Group->value)->toBe('group')
        ->and(OpportunityItemType::Product->value)->toBe('product')
        ->and(OpportunityItemType::Accessory->value)->toBe('accessory')
        ->and(OpportunityItemType::Service->value)->toBe('service');
});

it('marks only non-group roles priceable and only product/accessory demand-generating', function () {
    expect(OpportunityItemType::Group->isPriceable())->toBeFalse()
        ->and(OpportunityItemType::Product->isPriceable())->toBeTrue()
        ->and(OpportunityItemType::Group->generatesDemand())->toBeFalse()
        ->and(OpportunityItemType::Accessory->generatesDemand())->toBeTrue()
        ->and(OpportunityItemType::Service->generatesDemand())->toBeFalse();
});
```

- [ ] **Step 2: Run, expect fail** — `php artisan test --compact --filter=OpportunityItemTypeTest` → FAIL (class not found).

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Enums;

/**
 * Structural role of an opportunity line-item row in the unified, Current-RMS
 * line-item model. Distinct from the polymorphic catalogue reference
 * (`itemable_type`/`itemable_id`).
 */
enum OpportunityItemType: string
{
    case Group = 'group';
    case Product = 'product';
    case Accessory = 'accessory';
    case Service = 'service';

    /** Group rows are containers — never priced. */
    public function isPriceable(): bool
    {
        return $this !== self::Group;
    }

    /** Only physical lines (product/accessory) claim availability. */
    public function generatesDemand(): bool
    {
        return $this === self::Product || $this === self::Accessory;
    }
}
```

- [ ] **Step 4: Run, expect pass.**
- [ ] **Step 5: Commit** — `feat(opportunities): add OpportunityItemType role enum`.

### Task 2: Cutover migration + `OpportunityItem` model

**Files:**
- Create: `database/migrations/<ts>_cutover_unified_opportunity_line_items.php`
- Modify: `app/Models/OpportunityItem.php`
- Test: `tests/Feature/Opportunities/UnifiedLineItemSchemaTest.php`

**Interfaces:**
- Produces: `opportunity_items` columns `item_type` (string), `path` (string, indexed), `revenue_group_id` (nullable bigint), `itemable_type`/`itemable_id` (renamed morph); `section_id` and `sort_order` gone; `opportunity_sections` table dropped. `OpportunityItem`: cast `item_type => OpportunityItemType::class`; `depth(): int` (`strlen(path)/4`); `parentPath(): ?string`; `isProductBacked()` keys off `itemable_type`/`itemable_id`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\OpportunityItemType;
use App\Models\OpportunityItem;
use Illuminate\Support\Facades\Schema;

it('has the unified columns and no section/sort_order columns', function () {
    expect(Schema::hasColumns('opportunity_items', [
        'item_type', 'path', 'revenue_group_id', 'itemable_type', 'itemable_id',
    ]))->toBeTrue();
    expect(Schema::hasColumn('opportunity_items', 'section_id'))->toBeFalse();
    expect(Schema::hasColumn('opportunity_items', 'sort_order'))->toBeFalse();
    expect(Schema::hasTable('opportunity_sections'))->toBeFalse();
});

it('casts item_type and derives depth from path', function () {
    $item = OpportunityItem::factory()->make([
        'item_type' => OpportunityItemType::Product,
        'path' => '00010002',
    ]);
    expect($item->item_type)->toBe(OpportunityItemType::Product)
        ->and($item->depth())->toBe(2)
        ->and($item->parentPath())->toBe('0001');
});
```

- [ ] **Step 2: Run, expect fail** (columns/table assertions fail).

- [ ] **Step 3: Write the migration** (re-declaring nothing it doesn't change; PG-safe; reversible `down()` recreates the prior shape minimally for rollback). Truncation of data lives in P3, not here — this migration is schema-only so it can run on a fresh DB in tests.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->renameColumn('item_type', 'itemable_type');
            $table->renameColumn('item_id', 'itemable_id');
        });

        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->string('item_type')->default('product')->after('itemable_id');
            $table->string('path')->default('')->after('item_type');
            $table->unsignedBigInteger('revenue_group_id')->nullable()->after('path');
            $table->index(['opportunity_id', 'path']);
            $table->index('revenue_group_id');
        });

        // Drop the section linkage + sort_order (subsumed by path).
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('section_id');
            $table->dropColumn('sort_order');
        });

        Schema::dropIfExists('opportunity_sections');
    }

    public function down(): void
    {
        Schema::create('opportunity_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('opportunity_sections')->nullOnDelete();
            $table->string('auto_group_key')->nullable();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropIndex(['opportunity_id', 'path']);
            $table->dropIndex(['revenue_group_id']);
            $table->dropColumn(['item_type', 'path', 'revenue_group_id']);
            $table->foreignId('section_id')->nullable()->after('opportunity_id')->constrained('opportunity_sections')->nullOnDelete();
            $table->integer('sort_order')->default(0);
        });

        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->renameColumn('itemable_type', 'item_type');
            $table->renameColumn('itemable_id', 'item_id');
        });
    }
};
```

- [ ] **Step 4: Update `OpportunityItem`** — in `fillable` swap `item_id`/`item_type`/`section_id`/`sort_order` for `itemable_id`/`itemable_type`/`item_type`/`path`/`revenue_group_id`; in `casts()` drop `section_id`/`sort_order`, add `'item_type' => OpportunityItemType::class`, `'revenue_group_id' => 'integer'`; update the `item()` morph to `morphTo('itemable', 'itemable_type', 'itemable_id')`; delete the `section()` relation; rewrite `isProductBacked()` against `itemable_type`/`itemable_id`; add:

```php
/** Tree depth (1-based) derived from the 4-char-per-level path. */
public function depth(): int
{
    return (int) (strlen((string) $this->path) / 4);
}

/** The parent row's path prefix, or null at the top level. */
public function parentPath(): ?string
{
    $len = strlen((string) $this->path);

    return $len > 4 ? substr($this->path, 0, $len - 4) : null;
}
```

Update the `@property` docblock (`item_type` → `OpportunityItemType`, add `path`/`revenue_group_id`/`itemable_*`, drop `section_id`/`sort_order`). Update `OpportunityItemFactory` to emit `item_type`, `path`, `itemable_*` (default a product row at path `'0001'`).

- [ ] **Step 5: Run, expect pass** — `php artisan test --compact --filter=UnifiedLineItemSchemaTest`.
- [ ] **Step 6: Commit** — `feat(opportunities): unified line-item schema + model (cutover migration)`.

### Task 3: `OpportunityItemState` fields

**Files:**
- Modify: `app/Verbs/States/OpportunityItemState.php`
- Test: `tests/Feature/Verbs/Opportunities/OpportunityItemStateShapeTest.php`

**Interfaces:**
- Produces: state public props `item_type` (OpportunityItemType), `path` (string), `revenue_group_id` (?int), `itemable_type` (?string), `itemable_id` (?int); the old `item_type`/`item_id` morph props renamed to `itemable_*`.

- [ ] **Step 1: Write the failing test** — assert a freshly constructed `OpportunityItemState` has properties `item_type`, `path`, `revenue_group_id`, `itemable_type`, `itemable_id` and lacks `section_id` (via reflection `property_exists`).
- [ ] **Step 2: Run, expect fail.**
- [ ] **Step 3: Add/rename the state properties** (default `path=''`, `item_type=OpportunityItemType::Product`).
- [ ] **Step 4: Run, expect pass.**
- [ ] **Step 5: Commit** — `feat(opportunities): add unified fields to OpportunityItemState`.

---

## PHASE 2 — Events & actions (EXPANDED)

> **Execution order (dependency-aware):** T2.1 → **T2.2** → **T2.10 (ripple — restore green for consumers)** → T2.3 → T2.5 → T2.4 → T2.6 → T2.7 → T2.8 → T2.9 → T2.11 → T2.12. Each task carries its own tests; whole-suite green is still a P6 deliverable, but T2.10 should re-green the availability/demand/shortage/diff suites. Implementers read the named real sibling files and apply the stated deltas (more reliable than transcribing full files blind).

- **T2.1 `ItemPathService`** — port `PrototypeEditorService::persistTree` path-rebuild + depth clamp (depth ≤ prevDepth+1) into `app/Services/Opportunities/ItemPathService.php` as `rebuild(array $nodes): array<int,string>` (id→path). **Tests:** pre-order/reset-counters, clamp, accessory-parent-must-be-product, group/product/service-parent-is-root-or-group. This is the replay-critical algorithm — test it hard (property-style cases).
- **T2.2 Extend `ItemAdded`** (sibling ref: current `app/Verbs/Events/Opportunities/ItemAdded.php`) — **(a)** resolve the P1 carry-forward FIRST: change `OpportunityItemState::$item_type` from an enum instance to `string` with a `::from()` accessor pattern (mirror `ChargePeriod`/`LineItemTransactionType` on the same state) OR prove Verbs snapshots the enum; **(b)** add ctor payload `item_type` (string role, default `'product'`), `path` (string, default `''`), `revenue_group_id` (?int), and rename `item_id`/`item_type` ctor params → `itemable_id`/`itemable_type`; **(c)** `apply()` seeds the new + renamed state props; **(d)** `handle()`'s `updateOrCreate` writes `itemable_type`/`itemable_id`/`item_type`/`path`/`revenue_group_id` (not `sort_order`), and **gates** `repriceAndRollUp` + `syncDemand` behind `OpportunityItemType::from($state->item_type)->isPriceable()` / `->generatesDemand()` so group rows write a zero-priced, demand-free row. **Tests:** group add → row with item_type=group, total 0, no demand; product add unchanged; replay reproduces `path`/`item_type`/`itemable_*`.
- **T2.3 `ItemsRestructured` event** — payload ordered `array<{id:int,path:string}>`; `apply()` sets each state `path`; `handle()` writes paths + one `opportunity.items_restructured` audit. **Tests:** replay reproduces the exact tree; partial-set leaves others untouched.
- **T2.4 `ItemRenamed` event** + `RenameOpportunityItem` action. **Tests:** group + line rename; replay-stable.
- **T2.5 `RestructureOpportunityItems` action** — accept `[{id,depth}]`, resolve via `ItemPathService`, guard rules, fire `ItemsRestructured`. **Tests:** legal nest, illegal accessory reparent rejected, depth clamp, cross-group move.
- **T2.6 Extend `ItemRemoved`** — subtree cascade (fire per descendant deepest-first in one commit). **Tests:** removing a group removes its subtree; existing asset/dispatch guards still fire per row.
- **T2.7 `AddOpportunityGroup` / `AddOpportunityAccessory` actions** — group genesis (no itemable); accessory under a principal product (validate parent is product; auto-path under it). **Tests:** group created at next top-level path; accessory locked under product.
- **T2.8 Accessory auto-materialize** — on product add, append `included` catalogue accessories as accessory rows (qty = ratio×product qty). **Tests:** included → rows; suggested → none.
- **T2.9 Merge-key change** — `MergeOpportunityItems::isMergeable` swaps `section_id` for same parent-path. **Tests:** updated dataset.
- **T2.10 Morph-rename ripple** — `OpportunityItemDemandResolver`, `AvailabilityService`, `ShortageDetector`/`ItemShortageProbe`/`ShortageAutoResolver`/`DetectOrderShortages`, asset events read `itemable_type`/`itemable_id`; ensure group rows are skipped (not errored) in every flat iteration. **Tests:** existing availability/shortage tests pass against the renamed columns + a tree containing group rows.
- **T2.11 Repurpose `OpportunityAutoGroupResolver`** → resolve `revenue_group_id` (+ default group label) instead of `auto_group_key`. **Tests:** product → revenue_group_id from group tree.
- **T2.12 Version/clone** — thread `item_type`/`path`/`revenue_group_id` through `ClonesOpportunityItems::itemDataFrom`; `CreateVersion`/`CloneOpportunity` reproduce the full tree. **Tests:** clone reproduces groups + nesting (fixes today's lost-grouping gap).
- **Retire** the 6 section actions (`CreateOpportunitySection`, `RenameOpportunitySection`, `DeleteOpportunitySection`, `AssignSectionParent`, `ReorderOpportunitySections`, `AssignItemToSection`) + `ReorderOpportunityItems`/`ItemSortOrderChanged` (subsumed). Delete in P5 with their tests (approved exception — confirm list before deleting).

---

## PHASE 3 — Migration data + seeders (OUTLINE)

- **T3.1 Data-wipe migration step** — separate migration after the schema cutover: truncate `opportunity_items`, item `verb_events` (scope to item state types), `demands`, `opportunity_item_assets`; zero opportunity rollups. Reversible-safe on demo data. (Frozen-legacy events: do not replay.)
- **T3.2 Demo seeders** — rebuild opportunity seeders to emit group/product/accessory/service rows with paths + revenue groups; re-seed opp 1, 13, and the standard demo set with rich 3-level trees.
- **T3.3** — `php artisan migrate:fresh --seed` on the demo DB; verify rollups + availability recompute. **Tests:** a seeder smoke test asserting a seeded opportunity has groups+nested products+accessories with valid paths and non-null totals.

---

## PHASE 4 — API RMS cut (OUTLINE)

- **T4.1 `OpportunityItemData`** — drop `section_id`; add `item_type`, `path`, `parent_path`, `depth`, `revenue_group_id`; external morph naming default = RMS `item_type`/`item_id` mapped to `itemable_*` (resolve the deferred naming here). **Tests:** DTO shape + fromModel.
- **T4.2** Remove `OpportunitySectionData` + section includes from `OpportunityData`/controller.
- **T4.3 `PATCH /opportunities/{opportunity}/items/tree`** → `RestructureOpportunityItems`; controller method + route + ability scope `opportunities:write`. **Tests:** reorder/nest via API; illegal drop 422.
- **T4.4** `POST /items` accepts `item_type` (group/accessory); `PATCH /items/{item}` accepts `name`. **Tests.**
- **T4.5** `OpportunityItem::defineSchema()` + `SchemaController` — drop `section_id`, add `item_type`/`path`/`revenue_group_id`. **Tests:** schema endpoint shape.

---

## PHASE 5 — Editor rebuild (OUTLINE — largest)

New Volt component `resources/views/livewire/opportunities/line-items.blade.php`, mounted in `show.blade.php` replacing `<livewire:opportunities.items>`. Built on the local-first prototype foundation, wired to real actions.

- **T5.1 Shell + server contract** — Volt component exposing `tree()` (the version-scoped item tree as the prototype read shape, from real `OpportunityItem`s incl. group rows), and `$wire` methods bridging to actions: `addItem/addGroup/addAccessory/renameItem/updateField(qty|rate|discount|dates)/toggleOptional/substitute/removeItem/restructure(nodes)/setDealPrice/clearDealPrice`. Reuse existing actions (`ChangeItemQuantity`, `OverrideItemPrice`, `SetItemDiscount`, `ChangeItemDates`, `ToggleItemOptional`, `SubstituteItem`, `RemoveOpportunityItem`, plus the P2 new ones).
- **T5.2 Alpine store + render** — port `editor-local-first.blade.php`: Alpine-owned render under `wire:ignore`, frozen `window.__lfSeed` island, idempotent `boot()`, Dexie cache (serialised `_cacheChain`, empty-set guard, monotonic `_tempSeq`), custom pointer DnD with nesting + accessory-lock rules, default collapse.
- **T5.3 Bake in prototype bug-fixes** — price≥£1000 re-edit (raw-minor prefill), temp-id field/clone remap on flush (re-read latest local values post-remap; include cloned subtree), single money formatter matching server (no thousands-sep parse hazard).
- **T5.4 Conflict/divergence handling** — tree-revision token (latest item `verb_event` id) sent per sync; stale-write reconcile (pull server truth, re-base pending queue, conflict pill); `BroadcastChannel` multi-tab; Echo/Reverb cross-user pull. **Net rule:** local wins for un-synced edits, server is merge authority for others' commits.
- **T5.5 Feature parity** (spec §9.4, 18 items): two-tier search + qty-prefix, quick-add destination, expand/collapse-all + per-group, inline qty/rate/discount/dates, optimistic line+grand totals, per-group subtotal, edit-line modal, optional toggle+badge, availability badge + Gantt link, **real accessory sub-rows**, DnD reorder+nest+cross-group, group CRUD, merge dupes, substitute, move-to-group, read-only mode, cross-component totals sync (`opportunity-totals-updated`), **deal price**.
- **T5.6 Remove `items.blade.php`** + mount the new component.
- **T5.7 Confirm with Ben → remove remaining prototype scaffolding** (prototype table/model/service/enum + local-first route/view + `OpportunitySection` model/DTO + the 6 section actions + their migrations folded into the cutover).
- **Tests:** Livewire/Volt component tests per feature; a couple Pest 4 browser tests for DnD/nest where feasible; conflict-handling unit tests (revision token + reconcile logic extracted to a testable JS module or PHP-side guard).

---

## PHASE 6 — Tests + final gate (OUTLINE)

- **T6.1** Rewrite/remove the section + sort_order tests to the unified model (confirm the delete list with Ben first — approved exception to the no-delete rule).
- **T6.2** Fill coverage gaps: `ItemsRestructured` replay-stability, `ItemPathService` clamp/rules, accessory lock guard, group cascade remove, `ItemAdded` role branching, migration/seed smoke, API RMS shape, editor feature tests, conflict-handling.
- **T6.3 Final gate:** pint --dirty · phpstan (no path args) · 3-lane suite all green · line coverage ≥ 90%. Update scratchpad #38 + memory; offer Ben the push/merge/tag decision.

---

## Self-Review (against the spec)

- **Spec §4 data model** → P1 T2 (migration + model), T1 (enum), T3 (state). ✓
- **Spec §5 events** → P2 T2.2–T2.7. ✓
- **Spec §6 accessories** → P2 T2.7–T2.8, lock guard T2.5. ✓
- **Spec §7 API** → P4. ✓
- **Spec §8 migration/seed** → P3. ✓
- **Spec §9 editor (incl. §9.2 fixes, §9.3 conflict, §9.4 parity)** → P5 T5.1–T5.6. ✓
- **Spec §10 downstream low-coupling / morph rename** → P2 T2.10. ✓
- **Spec §12 risks** — path replay (T2.1, T6.2), morph ripple (T2.10), conflict handling (T5.4), accessory demand (T2.8 + T3.2 seed), test churn (T6.1). ✓
- **Deferred-in-plan decisions resolved:** itemable external API naming → T4.1; group-delete cascade → spec default (cascade subtree) implemented in T2.6.
- **Placeholder scan:** P1 is full-code; P2–P6 are deliberately task-outlines (to be expanded at phase start with real code/tests, same as P1) — this is the agreed decomposition for a 6-phase cutover, not a placeholder gap.
- **Type consistency:** `item_type` (role enum) vs `itemable_type`/`itemable_id` (morph) used consistently; `ItemPathService::rebuild` and `RestructureOpportunityItems`/`ItemsRestructured` share the `[{id,depth|path}]` shape.

---

## Execution note

P1 is execution-ready now. P2–P6 get expanded into bite-sized TDD tasks (P1's structure) at the start of each phase, informed by what the previous phase taught us — appropriate for a big-bang re-platform where later detail depends on earlier reality.
