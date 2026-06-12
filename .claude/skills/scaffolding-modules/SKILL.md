---
name: scaffolding-modules
description: "Use when starting a new entity, domain slice, or 'new part of the system' (e.g. vehicles, projects, venues) with UI and/or API; when asked to scaffold, bootstrap, or build a new module; or when asked to check, audit, or gap-analyse an existing module for completeness against framework conventions. Not for adding a single field or endpoint to an existing module — use normal conventions for those."
license: MIT
metadata:
  author: signals
---

# Scaffolding New Domain Modules

Builds a **complete vertical slice** the way Members was built. A module is not "done" when the model and API exist — it is done when the list page, record page, admin config, permissions, events/webhooks, global search, docs, and tests all exist and the quality gate passes. Partial slices are the #1 failure mode this skill prevents.

**Exemplar:** Members (`app/Models/Member.php` and everything around it). Where Phase-2 modules (Products, Activities) use a newer pattern, follow them instead — deltas are listed in `references/members-exemplar.md`.

## When to Apply

- User asks for a new module, entity, model, or "new part of the system" with UI and/or API.
- A framework-plans phase calls for a new domain slice.
- User asks to check, audit, or gap-analyse an existing module against framework conventions.
- NOT for: single new fields, new endpoints on existing modules, admin-only reference tables (those need only a subset — but still check the Definition of Done for which subset).

## Modes

Determine from the request (ask if ambiguous):

**`new`** — building a module from scratch. Follow Phase 0 and the build phases in order. This is the default when the entity does not exist yet.

**`existing`** — gap-audit a module that already exists (fully or partly built). Do NOT start building. Instead:

1. Run Phase 0 steps 1–5 (the review gate) for that module's domain. **For an existing module, Phase 0 is a *verification* step, not a questionnaire** — the URL/nav/dashboard placement is already decided and baked into the code. Read the actual routes (`routes/web.php`), header (`header.blade.php`), and dashboard blade to confirm placement, and flag any discrepancy between code and the plan. Do NOT re-ask the four placement questions; only ask the user when a placement decision is genuinely undecided because the item is a *gap being fixed* (step 4).
2. Walk EVERY item in `references/checklist.md` and grade it against the codebase: **present** (evidence: file path), **partial** (what's there / what's not), or **missing**. The list-sync registrations table and admin-surfaces table in `references/members-exemplar.md` are the verification map — grep each registration point for the module's keys.
3. Save the report to `.claude/reviews/module-audit-{module}-{YYYY-MM-DD}.md`, following `references/audit-report-template.md` — that file owns the full structure (frontmatter, summary table, graded checklist, prioritised gap list, exemplar verdict, decision log) and the grading guidance.
4. Present the gap list to the user and offer to close gaps — closing them then follows the relevant `new`-mode phases for just those pieces. Only here do the Phase 0 placement questions apply, and only for gap items that need a fresh nav/URL/dashboard decision.

## The Iron Rule: Review Before Building

**No file is created or modified until Phase 0 is complete.** Violating this produces bespoke components, duplicated logic, and missed conventions.

### Phase 0 — Review existing work (mandatory gate)

1. **Read the relevant framework plan(s)** in `framework-plans/` and the matching GitHub discussion(s) — the mapping table is in `references/checklist.md`. The plan may already specify your table's columns, name, or phase (e.g. the scheduling plan already specifies `vehicles`).
2. **Read `framework-plans/data-model-implementation.md`** for the table's phase, column spec, and naming. Check `framework-plans/implementation-proposal.md` + [discussion #20](https://github.com/signals-rental/framework/discussions/20) for whether this module is even in the current phase.
3. **Inventory the exemplar**: skim `references/members-exemplar.md`, then open the actual Members (and Products/Activities) files you will mirror. Do not work from memory of "typical Laravel".
4. **Read `framework-plans/component-library.md`** (and the rendered library at `docs/development` / `/prototypes/component-reference`) before touching any Blade. ALL UI must use the Signals framework components — `<x-signals.*>` containers and `s-*` classes; never build a new table, badge, modal, bulk bar, or any custom styling that duplicates an `s-` component.
5. **Search for prior art**: grep `app/` for similar models/services. If something close exists (e.g. a registry, a trait, a column type), extend it.
6. **Ask the user — never guess — four placement questions** (use AskUserQuestion):
   - **URL placement:** root level (`/vehicles`) or within a section (`/resources/vehicles`)?
   - **Nav placement:** top-level entry, or inside an existing mega-menu section (e.g. CRM, Resources)?
   - **Dashboard widget:** does this module need a dashboard component built and placed on the dashboard?
   - **Dashboard quick action:** should a quick action (e.g. "New {Entity}") be added to the dashboard Quick Actions block?
7. **Write a short scope note** (5–10 lines: entity name, table columns, archive semantics, relationships, URL/nav placement, which phases of the checklist apply) and confirm direction with the user if anything is ambiguous — especially archive semantics and whether merge applies.

### Key decisions to make in Phase 0

| Decision | Options | Rule of thumb |
|----------|---------|---------------|
| Archive semantics | Soft delete + `is_active` (Members) vs `is_active` only | Soft deletes ONLY for major business entities (members, opportunities, invoices). Either way the list page gets Active/Archived/All filtering and Archive/Restore actions. |
| Type taxonomy | PHP enum vs `list_values` | Enum when workflow-bound (status); list-of-values when user-extensible (categories, types). |
| Merge ability | Yes/No | Yes for entities users create duplicates of (members, products). Mirror `MergeMember`/`MergeModal`. |
| Polymorphic relations | Explicit FKs vs MorphTo | Newer modules (Activities) use MorphTo `regarding`-style maps. |
| Store scoping | `store_id` FK or not | Per data-model plan; store-scoped models get a global scope. |

## Build Phases (after Phase 0)

Work in this order; each phase lists its acceptance in `references/checklist.md` and its copy-from files in `references/members-exemplar.md`.

1. **Data model** — migration (industry-standard RMS-compatible names, integer PK, UTC timestamps, JSONB for flexible config, FKs constrained + indexed, money as INTEGER minor units), model (`casts()` method, `HasCustomFields`, `HasAttachments` if files apply, implements `HasSchema` with `defineSchema()`), factory with states. **Seeders, two kinds:** system/reference data in its own seeder registered in `DatabaseSeeder::run()` (the first-run seeder); demo records in `DemoDataSeeder` (`signals:seed-demo`) tagged `'demo-data'` in `tag_list` so `signals:clear-demo` can remove them.
2. **Actions + DTOs + events** — one invocable action per operation that occurs (no inline mutations anywhere), `Gate::authorize` inside; **every mutation fires `AuditableEvent` and dispatches its webhook event** (create/update/archive/restore/delete/merge + domain verbs). Each `AuditableEvent` must produce an `action_logs` row surfaced in `/admin/settings/action-log` — assert this in tests. Spatie Data DTOs only — never Form Requests or API Resources.
   - **Webhook dispatch placement:** `DeliverWebhook` now declares `public bool $afterCommit = true`, so the queue holds the job until the surrounding `DB::transaction` commits and discards it on rollback — in-transaction dispatch no longer produces phantom webhooks. Still call `WebhookService::dispatch()` AFTER the `DB::transaction` closure for readability; `MergeMember`/`AnonymiseMember` model the preferred ordering.
   - **Webhook payload conventions:** single-entity lifecycle events (archive/restore/delete/anonymise) send `['id' => $model->id]`; create/update send the full DTO under the entity key (`['member' => MemberData::fromModel($m)->toArray()]`); merge sends `['primary_id' => ..., 'secondary_id' => ...]`.
   - **Business-rule failures** throw `ValidationException::withMessages([...])` (auto-422 in API, clean Livewire field handling) — not `\InvalidArgumentException`. Some older actions (`MergeMember`, `MergeProduct`) still throw `InvalidArgumentException` (todo #138); new modules use `ValidationException`.
3. **API** — thin controller using `FiltersQueries` + `ResourceActions` traits ($allowedFilters, $filterAliases, $allowedSorts, $allowedIncludes, $customFieldModule); `apiResource` routes + nested resources; Sanctum abilities `{resource}:read`/`{resource}:write` registered in the token-ability picker; `view_id` custom-view support via `applyViewOrFilters()`; Scramble PHPDoc. Register the module's `{Entity}ColumnRegistry` in `ColumnRegistryResolver::$map` — see the authoritative list-sync row in `references/members-exemplar.md` for the why and the meta-test that guards it. **`exists:` validation on a soft-deletable FK** (e.g. another `members` row) must use `Rule::exists(...)->withoutTrashed()`, or the rule accepts archived records that then 404.
4. **List-sync registrations** (the silent-failure zone — see red flags): `SchemaController::MODEL_MAP`, `WebhookService::EVENTS`, custom-field module list, `PermissionSeeder` + `RoleSeeder`, `ViewSeeder` system views, `DatabaseSeeder` (first-run) + `DemoDataSeeder`, token-ability picker, and — if the module should be user-toggleable — the admin Modules page (`$moduleDefinitions` in `resources/views/livewire/admin/settings/modules.blade.php`) plus every `FeatureProfile::modules()` preset map.
5. **List page** — Volt page at `resources/views/livewire/{plural}/index.blade.php` + `app/Views/{Entity}ColumnRegistry.php`. Must include the full datatable contract: shared `<livewire:components.data-table>` (multiselect, sortable columns, avatars/entity icons, pagination), `toolbar-view` (type-filter chips with counts, Active/Archived/All filter, `<x-signals.column-toggle />`, `<x-signals.export-button />`), `actions-view` row-actions ellipsis, `bulk-actions-view` with `<x-signals.bulk-bar>` (archive + merge when applicable), merge modal, archive confirm modal, search, custom-view selector. 3–5 system views seeded in `ViewSeeder`.
6. **Record (show) page** — `<x-signals.page-header>` (breadcrumbs, type/status meta badges, Edit link, split-button "New" quick-actions menu linking to every relevant sub-model create), tabs for each related model listing, activity timeline, files/attachments tab, custom-fields tab.
7. **Form page** — single Volt form for create + edit (own routes per CRUD conventions: `/{plural}/create`, `/{plural}/{id}/edit`), constructs DTO → calls the same action the API uses, renders custom fields from definitions.
8. **Admin panels & admin surfaces** — settings definition class + registry entry if the module has configuration; reference-data CRUD pages under `/settings` (permission-gated) for any config tables; admin custom-fields module dropdown. Then make the module visible in every relevant admin surface: permissions appear in `admin/settings/permissions` + `roles` (via `PermissionRegistry` registration in `AppServiceProvider`), API scopes in the `admin/settings/api` ability list, seeded email templates in `admin/settings/email-templates`, registered notification types in `admin/settings/notifications`, scheduling-relevant settings in the `scheduling` settings group/page, LOV lists in `admin/settings/list-names`. See the admin-surfaces table in `references/members-exemplar.md`.
9. **Permissions** — `{resource}.access/view/create/edit/delete` chain in `PermissionSeeder`, assigned to every seeded role in `RoleSeeder`, Policy via `AuthorizesByPermission` trait.
10. **Search, nav + dashboard** — permission-gated block in `app/Http/Controllers/Web/SearchController.php` (global search results), command palette Navigation + Create entries AND search-result wiring, header mega-menu + sidebar entry in `resources/views/components/layouts/app/header.blade.php` gated on `{resource}.access` (placement per the Phase 0 answers). **Gate at the right altitude:** a section dropdown that contains several modules' links must NOT be wrapped in one module's `@can` — that swallows the other modules' links. Wrap the dropdown in `@canany([...all contained permissions])` and gate each link/column with its own `@can` (the CRM dropdown does this: `@canany(['members.access', 'activities.access'])` on the wrapper, `@can('members.access')`/`@can('activities.access')` per column). If requested in Phase 0: dashboard widget as a Livewire component in `app/Livewire/Dashboard/` placed in `resources/views/dashboard.blade.php`, and/or a quick action in the dashboard Quick Actions block — both permission-gated.
11. **Docs** — new pages: `docs/platform/{slug}.md` + `docs/api/{slug}.md` + `docs/documentation.json` manifest entries (use the **generate-docs** skill for format). Existing pages to UPDATE: `docs/api/webhooks.md` `## Events` list (new webhook events), `docs/getting-started/seeders.md` (any new seeders + DatabaseSeeder table), `docs/platform/admin-panel.md` (any admin panel changes/additions), `docs/development/getting-started.md` (if developer-facing setup/conventions changed). **All finished changes recorded in `docs/changelog/{next-version}.md`** — not optional. **Docs are a hidden list-sync registration:** adding any docs page or changelog file changes `getSearchIndex` counts and the changelog-frontmatter expectations hardcoded in `tests/Feature/Docs/DocsServiceTest.php` — update that test's expectations (newest changelog version/date/title, search-index assertions) or it fails.
12. **Tests + quality gate** — 90% line coverage target: actions (direct invocation + `Event::fake`), API (CRUD, abilities, Ransack `_eq`-family only on SQLite, includes, custom-field shape, validation), policy matrix per role, model (casts/scopes/schema), Livewire pages, seeder meta-tests. Then the mandatory gate: tests → `vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse` → pr-review agents → fix and repeat. Ask before committing.

## Global Engines — Never Reinvent

If the module touches any of these concerns, it MUST use the existing global engine — a module-local alternative is always wrong:

| Concern | Use | Never |
|---------|-----|-------|
| Tax | Global `TaxRate`/`TaxRule` + product/org tax classes (`admin/settings/tax`), `TaxCalculator` | Module-local tax columns or rates |
| Currency | Global `Currency` + `ExchangeRate` (`CurrencyService`), `currency_code` + rate snapshot on financial rows | Hardcoded currencies, float money |
| Countries | Seeded ISO `countries` table | Module-local country lists |
| Anything priced by time/quantity | `RateDefinition` presets + `ProductRate` pattern, `RateResolver`/`RateCalculator` (`admin/settings/rate-definitions`) | A parallel pricing/rate mechanism |
| Outbound email content | DB-stored `EmailTemplate` (seeded, editable in admin) | Hardcoded mail bodies |
| User-extensible dropdowns | `list_names`/`list_values` (admin-editable) | Hardcoded option arrays |
| List/custom-view column resolution | Register `{Entity}ColumnRegistry` in `ColumnRegistryResolver::$map` (`resolve()` feeds both `DataTable` and custom-view validation) | Inline column lists or a bespoke validation path |

## Red Flags — STOP, you are about to ship a partial slice

| Rationalization | Reality |
|-----------------|---------|
| "A table with search and pagination is enough for the list page" | The list contract is the FULL datatable: ColumnRegistry, custom views, column toggle, export, multiselect + bulk bar, row-actions ellipsis, archive filters, merge. Members shows every piece. |
| "Docs can come later / in another PR" | Docs are part of the slice (org principle: docs at parity with code). Platform doc + API doc + manifest entry, every time. |
| "Command-palette nav entry covers search" | Global search means a `SearchController` block + palette search results, not just a nav shortcut. |
| "I'll skip merge/archive for v1" | Decide in Phase 0 with the user, don't silently drop. Archive filtering is always required. |
| "I'll register webhooks/schema later" | UAT defects D5/D6 were exactly this. Phase 4's list-sync registrations are graded items in the checklist. |
| "I'll dispatch the webhook inside the transaction for atomicity" | Safe now — `DeliverWebhook::$afterCommit = true` holds the job until commit and drops it on rollback. Still place dispatch AFTER the transaction for readability. |
| "One `@can` on the section dropdown is fine" | If the dropdown holds multiple modules' links, that gate swallows the others. `@canany([...])` on the wrapper, `@can` per link/column. |
| "I remember how members works" | Open the files. Patterns drifted between Phase 1 and Phase 2 (MorphTo, enums, SchemaRegistry). |
| "I'll add a NavigationService entry" | `NavigationService` does not exist yet despite docs mentioning it — nav lives in `header.blade.php` and the command palette. Verify against the codebase, not the plans. |
| "This component doesn't exist, I'll build it" | Check `framework-plans/component-library.md` and `resources/views/components/signals/` first. It almost certainly exists. |

## Definition of Done

Every box in `references/checklist.md` ticked, suite green, pint + phpstan clean, pr-review run. If a layer is intentionally skipped (e.g. no merge for an asset register), the decision is recorded in the PR/scratchpad — silence is not a decision.
