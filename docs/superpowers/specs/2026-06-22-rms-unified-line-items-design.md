# RMS-unified opportunity line-items + local-first editor — Design Spec

**Date:** 2026-06-22
**Branch:** `phase-3/m1-foundations` (local, unmerged — Phase 3 on hold for UAT)
**Status:** Approved design → pending spec review → implementation plan
**Supersedes:** the throwaway Editor Lab prototypes (`prototype_opportunity_items`, `PrototypeEditorService`, the 4 prototype Volt pages, the 4 `editor-lab.*` routes — all to be removed).

---

## 1. Goal

Re-platform the opportunity line-item spine from the current **two-table split**
(`opportunity_sections` groups + `opportunity_items` products) to a **single,
uniform, Current-RMS-faithful model** — one `opportunity_items` table where every
row is distinguished by `item_type` (`group | product | accessory | service`),
nesting + order are encoded in a materialized `path`, and grouping/reporting is
carried by `revenue_group_id`. Rebuild the Overview line-item editor on the chosen
**local-first** prototype (Alpine store + IndexedDB + background sync + custom
pointer DnD), wired to the real event-sourced backend, with full feature parity
and the bug-fixes found in the prototype review.

This is a **big-bang cutover** (decisions locked with the user, see §3) on
unmerged demo data, so there is no production data or API consumer to protect and
the suite is re-greened at the end rather than at each step.

---

## 2. Source of truth & related docs

- Prototype reviewed: `resources/views/livewire/opportunities/prototypes/editor-local-first.blade.php`
- Prototype backend: `app/Services/Prototypes/PrototypeEditorService.php` (the **`path` contract** reference implementation)
- Current editor (feature checklist): `resources/views/livewire/opportunities/items.blade.php` (~2130 lines, 27 features catalogued in §9)
- Scratchpad: Solo scratchpad **#38** (this work) — single source of truth going forward.
- Framework plan open question this resolves: `framework-plans/data-model-implementation.md:1078` ("opportunity item grouping — column or separate table?").

---

## 3. Locked decisions (from brainstorming)

| # | Decision | Choice |
|---|----------|--------|
| 1 | How does `path` live in the event-sourced backend? | **Fully event-sourced** — every row is an event-sourced item; tree moves fire one `ItemsRestructured` event carrying ordered `[{id, path}]`; `path` is replay-stable state. |
| 2 | Accessories | **First-class persisted rows** (`item_type='accessory'`), product-backed, priced + demand-generating, locked under their principal product. |
| 3 | Rollout | **Big-bang cutover** — one migration; editor + API + actions switch together; final 3-lane gate. |
| 4 | API contract | **Clean RMS cut** — replace `section_id` with `item_type`/`path`/`revenue_group_id`; remove `OpportunitySectionData`. |
| 5 | Editor persistence | **Full local-first incl. IndexedDB** (Dexie cache surviving reload + background sync) with real multi-tab / multi-user conflict handling. |
| 6 | Existing data & event history | **Wipe & re-seed demo data** — drop existing line-item rows + their item `verb_events`/`demands`; pre-cutover events frozen as legacy (not replayed); demo seeders rebuilt. |

---

## 4. Data model

### 4.1 `opportunity_items` (the single line-item table)

**Rename (resolve the `item_type` collision):**
- `item_type` → **`itemable_type`** (the polymorphic morph to the catalogue entity, currently `App\Models\Product`)
- `item_id` → **`itemable_id`**

**Add:**
- `item_type` — string/enum, the **structural role**: `group | product | accessory | service`. Backed by a new `OpportunityItemType` enum (promote the prototype's `PrototypeItemType`).
- `path` — `varchar`, indexed. 4-char zero-padded segment per level (`"0001"`, `"00010001"`). Lexical sort over `path` == tree pre-order. `depth = strlen(path)/4`.
- `revenue_group_id` — `bigint` nullable, FK to the product-group/revenue-group concept; derived on add from the product's group (mirrors today's `auto_group_key` resolution via `OpportunityAutoGroupResolver`).

**Drop:**
- `section_id` (sections retired)
- `sort_order` (subsumed by `path`)

**Unchanged:** `id` (app-assigned), `state_id`, `opportunity_id`, `version_id`,
`name`, `description`, `quantity`, `unit_price`, `charge_period`, `total`,
`discount_percent`, `tax_rate`, `transaction_type`, `starts_at`, `ends_at`,
fulfilment quantities, store overrides, `is_optional`, `custom_fields`, `notes`,
timestamps.

### 4.2 Retired entirely
- Table `opportunity_sections` (dropped in the cutover migration).
- `app/Models/OpportunitySection.php`
- `app/Data/Opportunities/OpportunitySectionData.php`
- Actions: `CreateOpportunitySection`, `RenameOpportunitySection`,
  `DeleteOpportunitySection`, `AssignSectionParent`, `ReorderOpportunitySections`,
  `AssignItemToSection`.
- `OpportunitySection`-related relations on `Opportunity` (`sections()`), the
  `section()` relation on `OpportunityItem`, `auto_group_key` column/migrations.
- `OpportunityAutoGroupResolver` is **retained** but repurposed to resolve
  `revenue_group_id` (+ a default group row label) instead of an `auto_group_key`.

### 4.3 Item-type semantics

| `item_type` | itemable | Priced? | Demand? | Notes |
|-------------|----------|---------|---------|-------|
| `group`     | null     | No      | No      | Container/header row; carries `name`, children via `path`. Subtotal computed from descendants. |
| `product`   | Product  | Yes     | Yes     | As today. |
| `accessory` | Product  | Yes     | Yes     | **New first-class.** Path-nested directly under a principal product; locked there. |
| `service`   | Product/null | Yes | No      | Service line (today `transaction_type=Service`); no physical demand. |

`isProductBacked()` stays the gate for pricing/demand and now keys off
`itemable_type` (group → false → never priced/no demand).

---

## 5. Event sourcing

`OpportunityItemState` gains: `item_type`, `path`, `revenue_group_id`,
`itemable_type`, `itemable_id` (renamed from `item_type`/`item_id`). `section_id`
was never in state — nothing to remove there.

### 5.1 Events

- **`ItemAdded`** (extended) — single genesis event for **all** roles. New payload
  fields: `item_type`, `path`, `revenue_group_id`. `apply()` seeds them into state;
  `handle()` writes the row, then prices + syncs demand **only when product-backed**
  (groups skip both). Used for groups, products, accessories, services.
- **`ItemsRestructured`** (new) — the universal tree event. Payload: ordered
  `array<{id:int, path:string}>` (or `{id, depth}` with the server rebuilding paths
  via the same algorithm as `PrototypeEditorService::persistTree()`). `apply()`
  sets each item-state `path`; `handle()` writes all paths + one audit
  `opportunity.items_restructured`. **Replaces** `ItemSortOrderChanged`,
  `ReorderOpportunityItems`, `AssignItemToSection`, `AssignSectionParent`,
  `ReorderOpportunitySections`.
- **`ItemRenamed`** (new) — rename any row (group header or line). `apply()` sets
  `state->name`; `handle()` writes + audit. Replaces `RenameOpportunitySection`.
- **`ItemRemoved`** (extended) — cascades a group's subtree: the action fires one
  `ItemRemoved` per descendant row (deepest-first) inside one `commitVerbs()`
  boundary (same multi-fire pattern as `MergeOpportunityItems`). Existing
  asset/dispatch guards still apply per row.
- **Unchanged:** `ItemQuantityChanged`, `ItemPriceOverridden`, `ItemDiscountSet`,
  `ItemDatesChanged`, `ItemOptionalToggled`, `ItemSubstituted` — they target
  product/accessory/service rows; groups reject them by guard.

### 5.2 Actions

- **`AddOpportunityItem`** — extended to accept `item_type`, optional `parent path`
  / `revenue_group_id`; computes the new row's `path` (append-as-last-child of the
  target group, or next top-level) and fires `ItemAdded`.
- **`AddOpportunityGroup`** (new) — convenience action firing `ItemAdded(item_type=group)`.
- **`AddOpportunityAccessory`** (new) — fires `ItemAdded(item_type=accessory)` under a
  principal product; validates the parent is a product.
- **`RestructureOpportunityItems`** (new) — accepts ordered `[{id, depth}]`, rebuilds
  paths (clamp: depth ≤ prevDepth+1; accessory parent must be a product; group/
  product/service parent must be root or a group), fires `ItemsRestructured`.
- **`RenameOpportunityItem`** (new) — fires `ItemRenamed`.
- **`RemoveOpportunityItem`** — extended to cascade subtree removal for groups.
- **`MergeOpportunityItems`** — duplicate key drops `section_id`, gains "same parent
  `path` prefix" (same group) as the grouping dimension.

All keep the `CommitsVerbsEvents` (`DB::transaction` + `Verbs::commit`) boundary.

### 5.3 Version clone / opportunity clone
`CreateVersion` / `CloneOpportunity` already re-fire `ItemAdded` per item. They now
also carry `item_type`, `path`, `revenue_group_id` through `ClonesOpportunityItems::itemDataFrom()`,
so the **full tree (groups + nesting) is reproduced on clone** — fixing today's gap
where section assignments were lost on clone/version.

---

## 6. Accessories (first-class)

- On adding a product, **auto-materialize its catalogue `included` accessories**
  (`Product::accessories()->where('included', true)`) as `accessory` rows nested
  directly under the product (`path` = product path + next child segment),
  quantity = `ratio × product qty`. Non-included ("suggested") accessories are
  offered in the UI but not auto-added.
- **Lock rule** (client DnD + server guard in `RestructureOpportunityItems`): an
  `accessory` row's path-parent must be a `product`. It may be reordered among its
  siblings but never reparented elsewhere. Dragging a product carries its accessory
  subtree.
- Accessories are product-backed → priced + demand-generating like any product line
  (this is the deliberate upgrade from today's display-only zero-priced sub-rows).

---

## 7. API (clean RMS cut)

- **`OpportunityItemData`**: remove `section_id`; add `item_type`, `path`,
  `parent_path` (derived), `depth` (derived), `revenue_group_id`. Keep money/RMS
  decimal-string formatting. Rename serialised morph keys to `itemable_type` /
  `itemable_id` (or keep the RMS `item_type`/`item_id` external names mapped to the
  renamed columns — **decide in plan**, default to RMS external names mapped to
  `itemable_*`).
- Remove `OpportunitySectionData` and any `sections` includes.
- **New endpoint** `PATCH /api/v1/opportunities/{opportunity}/items/tree` —
  body `{ nodes: [{id, depth}] }` → `RestructureOpportunityItems`.
- Group/accessory creation via the existing `POST …/items` with `item_type` in the
  body; `PATCH …/items/{item}` gains `name` (rename) and tree fields are read-only
  there (structure changes go through `/items/tree`).
- `OpportunityItem::defineSchema()` + `SchemaController`: drop `section_id`; add
  `item_type`, `path`, `revenue_group_id` as filterable columns.

---

## 8. Migration & seeding (wipe & re-seed)

One cutover migration:
1. Drop FK + table `opportunity_sections`.
2. Alter `opportunity_items`: rename `item_type`→`itemable_type`,
   `item_id`→`itemable_id`; add `item_type`, `path`, `revenue_group_id`; drop
   `section_id`, `sort_order`; add `path` index.
3. **Truncate** existing `opportunity_items` rows and their related item
   `verb_events` + `demands` + `opportunity_item_assets` (line-item data is
   re-seeded; pre-cutover events are frozen legacy, never replayed).
4. Recompute opportunity rollups to zero/!empty as appropriate on re-seed.

Seeders: rewrite the opportunity/demo seeders to build opportunities in the unified
model (group/product/accessory/service rows with paths + revenue groups). Re-seed
the demo opportunities (opp 1, 13, …) so UAT has a clean, rich tree.

> **Note (test rule):** section-specific tests (`CreateOpportunitySection`,
> `AssignSectionParent`, etc.) will be **removed/rewritten** to the unified model.
> This is the approved exception to "never remove tests without approval" — called
> out and confirmed in the plan before deletion.

---

## 9. Editor rebuild — production local-first

New Volt component replacing `livewire:opportunities.items`, built on the
prototype's `editor-local-first` foundation, **wired to the real event-sourced
actions**.

### 9.1 Carried-over architecture (load-bearing patterns)
- **Alpine store is the render source of truth**; Livewire shell owns nothing in the
  table (`wire:ignore` on the render region, frozen `window.__lfSeed` island,
  idempotent `boot()`). Prevents morph-vs-Alpine flicker.
- **Dexie/IndexedDB** cache (serialised `_cacheChain` writer, empty-set guard,
  monotonic `_tempSeq` temp ids) — instant repaint from cache on reload.
- **Background sync queue** (debounced + `requestIdleCallback`) → translates queued
  local mutations into the real action calls (`AddOpportunityItem`,
  `ChangeItemQuantity`, `OverrideItemPrice`, `SetItemDiscount`, `ChangeItemDates`,
  `ToggleItemOptional`, `SubstituteItem`, `RemoveOpportunityItem`,
  `RestructureOpportunityItems`, group add/rename/remove, accessory add).
- **Custom pointer-events nested DnD** (Nestable-style indent), with the nesting
  rules: group/product/service → root or under a group; accessory → locked under its
  principal product.

### 9.2 Prototype bug-fixes baked in (from review)
- **Price ≥ £1,000 re-edit corruption** — pre-fill the price input from raw minor
  units (`(unit_price/100).toFixed(2)`), never from a thousands-separated display;
  one money formatter shared with the server convention.
- **Un-synced row edits/clones dropped server-side** — after a temp→real id remap on
  flush, re-read and send the latest local field values for that row; include
  cloned subtrees in the remap pass.
- **Formatting parity** — single formatter; no locale thousands-separators that the
  re-edit parser would mis-read.

### 9.3 Conflict / divergence handling (the hard, novel part)
Full local-first over a **multi-user, event-sourced, server-authoritative** backend:
- **Tree-revision token** — each opportunity tree carries a revision (latest item
  `verb_event` id, or a per-opportunity counter). The client sends its base revision
  with each sync.
- **Stale-write reconcile** — server rejects a sync whose base revision is behind →
  client pulls fresh server truth, re-bases its still-pending local queue, surfaces a
  conflict pill if a hard conflict remains (same field edited both sides).
- **Multi-tab** — `BroadcastChannel` shares cache invalidation + queue state across
  tabs of the same user.
- **Cross-user** — subscribe to the existing **Echo/Reverb** opportunity channel
  (M8 client); a remote item change pulls fresh server truth into the store,
  reconciled against the local pending queue.
- **Net rule:** *local wins for your un-synced edits; the event-sourced server is the
  merge authority for others' committed changes.* No silent overwrite of newer
  server state by stale cache.

### 9.4 Feature parity checklist (must preserve — from the current editor)
1. Two-tier product search (local MiniSearch + `#[Renderless]` pg_trgm fallback) +
   qty-prefix parse (`"6 spiider"`).
2. Quick-add bar with destination (group) selector.
3. **Expand-all / collapse-all** + per-group collapse toggle (Alpine state keyed by
   group/row id).
4. Inline edit: quantity, rate/unit-price override (blank = revert to engine),
   discount %, dates.
5. Optimistic per-line total + optimistic grand total (reseed-on-morph guard).
6. Per-group subtotal in the group header.
7. "Edit line" modal: price override + discount + dates + substitute product.
8. Optional/required toggle + badge.
9. Per-line availability badge (green/blue/red) + "View availability" Gantt deep-link.
10. **Accessory sub-rows** (now real persisted rows; expand/collapse, ratio × qty).
11. DnD reorder lines + **cross-group move** + **nesting** (depth-clamped, accessory-locked).
12. Group CRUD: add / rename / delete (delete cascades subtree to nowhere or to root — **decide in plan**, default: cascade-delete subtree with confirm).
13. Merge duplicate lines (same product+txtype+period+dates+optional+**parent group**).
14. Substitute product (keeps qty/price/dates).
15. Move-to-group from the row menu.
16. Read-only mode (respects `opportunities.edit` + closed-status guard) — no
    quick-add/toolbar/handles/menus.
17. Cross-component totals sync (`opportunity-totals-updated`) to the Show sidebar/header.
18. **Deal price** (set/clear) — API-complete today, surfaced in the editor now.

---

## 10. Downstream consumers (confirmed low-coupling)

These iterate a flat item list and need **no structural change** (only the
`itemable_*` rename where they read the morph):
- Pricing: `OpportunityTotalsCalculator` (`recalculateItem`, `rollUp`), all Verbs
  pricing concerns.
- Availability/demand: `OpportunityItemDemandResolver`, `AvailabilityService`.
- Shortage: `ShortageDetector`, `ItemShortageProbe`, `ShortageAutoResolver`,
  `DetectOrderShortages`.
- Asset allocation/dispatch events + actions; `DiffVersions`.

Group rows must be **excluded** from these iterations — they are not product-backed,
so the existing `isProductBacked()` gate already excludes them once it keys off
`itemable_type`. Verify each consumer's iteration tolerates group rows (skip, not error).

---

## 11. Implementation phasing (one cutover, sequenced plan)

- **P1 — Backend spine:** `OpportunityItemType` enum, migration (rename morph + add
  columns + drop section/sort_order + drop sections table), model updates,
  `OpportunityItemState` fields.
- **P2 — Events & actions:** extend `ItemAdded`; new `ItemsRestructured`,
  `ItemRenamed`; cascade `ItemRemoved`; new/extended actions; **morph-rename ripple**
  across demand/availability/schema consumers; merge-key change.
- **P3 — Migration & seeders:** wipe & re-seed; demo seeders rebuilt; rollups recomputed.
- **P4 — API:** DTO RMS cut, `/items/tree` endpoint, group/accessory create, schema.
- **P5 — Editor:** new local-first Volt component (architecture §9.1) wired to real
  actions, bug-fixes (§9.2), conflict handling (§9.3), full feature parity (§9.4);
  remove the 4 prototypes + `editor-lab.*` routes + `prototype_opportunity_items`
  table/model/service/enum.
- **P6 — Tests + gate:** rewrite item/section tests to the unified model; new tests
  for restructure replay-stability, accessory lock, group cascade, migration/seed,
  API RMS shape, editor features; **final 3-lane suite green** (SQLite parallel /
  env-writing seq / pgsql seq) + phpstan + pint.

---

## 12. Risks

- **Highest:** §9.3 conflict handling — novel, multi-user, hard to test deterministically.
- **Wide-but-mechanical:** the `item_type`→`itemable_type` morph rename across all
  consumers and the schema/registry.
- **Replay-stability** of `path` via `ItemsRestructured` (must clamp depth exactly
  like `persistTree`, and survive replay) — port the prototype algorithm + test it.
- **Accessory demand** — making accessories real demand-generating lines changes
  availability/shortage numbers vs today; re-seed + tests must reflect that.
- **Test churn** — many section tests removed/rewritten (approved exception, §8 note).

## 13. Out of scope (v1)
- Migrating real (non-demo) production data — none exists (unmerged branch).
- Replaying historical event streams into the new vocabulary (frozen as legacy).
- Revenue-group management UI beyond derive-on-add (reporting use is later).
