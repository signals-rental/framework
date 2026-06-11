# New Module — Acceptance Checklist & Reading Map

Tick every box or record an explicit, user-approved skip. Silence is not a decision.

## Phase 0 — Review first (gate; no code before this)

- [ ] Relevant `framework-plans/*.md` read (see mapping table below) — table name/columns/phase confirmed against `data-model-implementation.md` and discussion #20
- [ ] Matching GitHub discussion(s) consulted for decided conventions + open questions
- [ ] Members exemplar files opened (not recalled from memory); newer Products/Activities deltas checked
- [ ] `framework-plans/component-library.md` read; reusable components identified — nothing rebuilt
- [ ] Prior art grep across `app/` (similar models, services, registries, traits)
- [ ] **User asked (AskUserQuestion, never guessed):** URL placement — root level or within a section?
- [ ] **User asked:** nav placement — top-level or inside an existing mega-menu section (CRM, Resources, …)?
- [ ] **User asked:** dashboard widget needed?
- [ ] **User asked:** dashboard quick action needed?
- [ ] Scope note written (entity, columns, archive semantics, merge y/n, relationships, store scoping, placements) and ambiguities confirmed with the user

## 1. Data model

- [ ] Migration: industry-standard RMS-compatible names, integer PK, `{singular}_id` FKs constrained + indexed, UTC timestamps, JSONB (not JSON), money as INTEGER minor units, `is_` boolean prefixes, soft deletes only if a major business entity
- [ ] Model: `casts()` method, `HasCustomFields`, `HasAttachments` (if files), implements `HasSchema` with `defineSchema()`, scopes, eager-load-safe relations
- [ ] Enum (workflow states) vs `list_values` (user-extensible types) decided and seeded
- [ ] Factory with one state per variant
- [ ] System/reference seeder written AND registered in `DatabaseSeeder::run()` (first-run seeding)
- [ ] Demo records in `DemoDataSeeder`, tagged `'demo-data'` in `tag_list` so `signals:clear-demo` removes them

## 2. Actions, DTOs, events

- [ ] One invocable action per operation that takes place — every mutation goes through an action, no inline writes in Livewire/controllers
- [ ] `Gate::authorize` inside every action (not controllers)
- [ ] EVERY mutation fires `AuditableEvent` AND dispatches its webhook event (`{entity}.created/updated/archived/restored/deleted/merged/...`)
- [ ] Each `AuditableEvent` produces an `action_logs` row surfaced in `/admin/settings/action-log` — asserted in action tests (`ActionLog` row with correct `auditable_type`/`action`)
- [ ] DTOs: `Create{Entity}Data` (validation attributes), `Update{Entity}Data` (PATCH semantics), `{Entity}Data` response with `fromModel()` + `Lazy` relations + flat `custom_fields`
- [ ] No Form Requests, no API Resources, no `DB::` facade

## 3. API

- [ ] Thin controller w/ `FiltersQueries` + `ResourceActions`; `$allowedFilters/$filterAliases/$allowedSorts/$allowedIncludes/$customFieldModule` set
- [ ] `apiResource` + nested routes under `/api/v1/`, named `api.v1.{plural}`
- [ ] Response wrappers: singular key for show, plural + `meta` for index; money as decimal strings; ISO-8601 UTC dates
- [ ] Ransack filtering incl. `q[cf.*]`; `?include=`; `view_id` support via `applyViewOrFilters()` with sparse view columns
- [ ] Abilities `{resource}:read`/`{resource}:write` enforced AND added to the token-ability picker UI
- [ ] Scramble PHPDoc on controller methods

## 4. List-sync registrations

- [ ] `SchemaController::MODEL_MAP` entry (field discovery)
- [ ] `WebhookService::EVENTS` — every dispatched event registered (UAT D5 class of bug)
- [ ] Custom-field module list (definition resolver + admin dropdown)
- [ ] `PermissionSeeder` chain + `RoleSeeder` grants for ALL seeded roles
- [ ] `ViewSeeder`: 3–5 system views, one default
- [ ] `ListOfValuesSeeder` for LOV types
- [ ] `DatabaseSeeder` + `DemoDataSeeder` registrations (see Phase 1)

## 5. List page

- [ ] Volt index page + `{Entity}ColumnRegistry` (sortable/filterable column metadata, `defaultColumns()`)
- [ ] Shared `<livewire:components.data-table>` — never a bespoke table
- [ ] Toolbar: type-filter chips with counts, Active/Archived/All filter (`#[Url]`), model search, `<x-signals.column-toggle />`, `<x-signals.export-button />`
- [ ] Multiselect + `<x-signals.bulk-bar>` bulk actions (archive; merge when exactly 2 selected, if merge applies)
- [ ] Row-actions ellipsis partial (edit/archive/restore/delete)
- [ ] Custom-view selector working end-to-end (UI + `view_id` API)
- [ ] Merge modal + archive confirm modal (reuse existing components)
- [ ] Badges via `s-badge-*` classes; avatars/entity icons via existing components

## 6. Record (show) page

- [ ] `<x-signals.page-header>`: breadcrumbs, entity icon, type/status meta badges, Edit link
- [ ] `<x-signals.split-button>` "New" quick-actions menu → create routes for every relevant sub-model
- [ ] One tab per related model with its listing
- [ ] Activity timeline tab; files/attachments tab (shared upload components); custom-fields tab

## 7. Form page

- [ ] Single Volt form for create + edit at `/{plural}/create` and `/{plural}/{id}/edit`
- [ ] DTO → same action as the API; custom fields rendered from definitions with validation

## 8. Admin

- [ ] Settings definition class + registry entry if the module has runtime config (`settings('group.key')`, never env/config)
- [ ] Reference-data CRUD under `/settings` (permission-gated) for config tables
- [ ] Module visible in admin custom-fields setup
- [ ] If user-toggleable: entry in `$moduleDefinitions` (`resources/views/livewire/admin/settings/modules.blade.php`) + key in every `FeatureProfile::modules()` preset. NB: toggle *enforcement* (nav/route gating on `settings('modules.{key}')`) is not wired framework-wide yet — gate your own nav entry on it and flag any gap to the user
- [ ] Permissions registered in `PermissionRegistry` (`AppServiceProvider`) with group metadata → surfaced in `admin/settings/permissions` + `admin/settings/roles`
- [ ] API abilities added to the `admin/settings/api` token-scope list (UI + any registry)
- [ ] Email templates for any outbound comms seeded via `EmailTemplateSeeder` → surfaced in `admin/settings/email-templates`
- [ ] Notification types registered in `NotificationTypeSeeder::types()` → surfaced in `admin/settings/notifications`
- [ ] Scheduling-relevant config added to the `scheduling` settings group + `admin/settings/scheduling` form, if applicable
- [ ] LOV lists seeded via `ListOfValuesSeeder` → surfaced in `admin/settings/list-names`

## 9. Permissions

- [ ] `{resource}.access/view/create/edit/delete` chain; policy via `AuthorizesByPermission`
- [ ] Role distribution mirrors comparable modules; permission-count meta-tests updated

## 10. Search & navigation

- [ ] `SearchController` Gate-gated block returning `{id, name, type, isActive, initials/icon, url}`
- [ ] Command palette: Navigation entry + Create entry + live search results
- [ ] Header mega-menu + sidebar entry gated on `{resource}.access` with `routeIs()` active state — placed per the Phase 0 nav answer
- [ ] Web routes placed per the Phase 0 URL answer (root vs section prefix)
- [ ] `NavigationTest` (or equivalent) updated
- [ ] Dashboard widget built (`app/Livewire/Dashboard/`) and placed in `resources/views/dashboard.blade.php`, permission-gated — if requested in Phase 0
- [ ] Dashboard quick action added to the Quick Actions block — if requested in Phase 0

## 11. Docs

- [ ] `docs/platform/{slug}.md` — user guide (list features, detail tabs, fields) per generate-docs format
- [ ] `docs/api/{slug}.md` — endpoints, abilities, filters, includes, custom views
- [ ] `docs/documentation.json` manifest entries for both
- [ ] `docs/api/webhooks.md` — new webhook events appended to the `## Events` list
- [ ] `docs/getting-started/seeders.md` — new seeders documented (incl. the DatabaseSeeder order table)
- [ ] `docs/platform/admin-panel.md` — updated with any admin panel changes/additions
- [ ] `docs/development/getting-started.md` — updated if developer-facing setup/conventions changed
- [ ] `docs/changelog/{next-version}.md` — ALL finished changes recorded (mandatory, not just releases)

## 12. Tests & quality gate (90% line coverage target)

- [ ] Action tests (direct invocation, `Event::fake`, edge + failure paths)
- [ ] API tests (CRUD, abilities read vs write, validation 422, 401/403, Ransack `_eq`-family on SQLite, includes, custom-field shape, `view_id`)
- [ ] Policy matrix per role; model tests (casts, scopes, schema contract, factory states)
- [ ] Livewire page tests (index filters/bulk/archive, form create+edit, show tabs)
- [ ] Webhook-registration + seeder meta-tests extended
- [ ] Gate, in order: relevant tests → `vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse` (zero errors) → pr-review agents + `/codex:adversarial-review` (cross-model, skip gracefully if the Codex plugin isn't installed) → fix & repeat. Ask before committing/pushing.

## Cross-cutting (every layer)

- UTC storage, `@localdate`/`@localdatetime` + `Formatter` for display; `__()` on all user-facing strings
- Money: brick/money integer minor units; decimal strings in API
- Tenant-ignorant: no `tenant_id`, no tenancy logic
- Resolve dependencies via `app()` for mockability; queued jobs for anything slow
- ALL UI via Signals framework components (`<x-signals.*>` + `s-*` classes per `framework-plans/component-library.md` / `/prototypes/component-reference`) — no bespoke styling
- Global engines only — tax (classes/rates/rules), currency + exchange rates, countries, rate definitions for anything priced (see SKILL.md Global Engines table)

## Reading map — work area → plan → discussion

| Work area | framework-plans | Discussion |
|-----------|-----------------|------------|
| Mission/principles | — | [org README](https://github.com/signals-rental/.github/blob/main/profile/README.md) |
| Main plan (15 constraints) | `implementation-proposal.md` | [#1](https://github.com/signals-rental/framework/discussions/1) |
| Phase order / what ships when | `implementation-proposal.md` | [#20](https://github.com/signals-rental/framework/discussions/20) |
| API architecture | `api-architecture.md` | [#5](https://github.com/signals-rental/framework/discussions/5) |
| Data model / schema conventions | `data-model-implementation.md` | [#18](https://github.com/signals-rental/framework/discussions/18) |
| Custom fields / LOV | `custom-fields.md` | [#25](https://github.com/signals-rental/framework/discussions/25) |
| Custom views | `custom-views.md` | [#9](https://github.com/signals-rental/framework/discussions/9) |
| Files / attachments | `file-and-attachment-system.md` | [#12](https://github.com/signals-rental/framework/discussions/12) |
| Import / export | `import-export-migration-engine.md` | [#24](https://github.com/signals-rental/framework/discussions/24) |
| Localisation / formatting | `localisation-timezone-formatting.md` | [#29](https://github.com/signals-rental/framework/discussions/29) |
| Settings / admin | `settings-and-administration.md` | [#7](https://github.com/signals-rental/framework/discussions/7) |
| Nav / UI shell | `navigation-and-ui-shell.md` + `component-library.md` | [#28](https://github.com/signals-rental/framework/discussions/28) |
| Plugins (extension surface) | `plugin-system.md` | [#6](https://github.com/signals-rental/framework/discussions/6) |
| Search | `search.md` | [#3](https://github.com/signals-rental/framework/discussions/3) |
| Notifications | `notification-communication-engine.md` | [#35](https://github.com/signals-rental/framework/discussions/35) |

**Known plan-vs-reality conflicts** (verify code, don't trust docs):
1. #6 allows plugin-owned `plugin_` tables + YAML manifest; CLAUDE.md says no plugin migrations + `signals.json` — follow CLAUDE.md, flag to the user if relevant.
2. #28's generic `<x-*>` component names and `NavigationService` predate reality — use `<x-signals.*>`/`s-*` and the header mega-menu.
3. #18's DECIMAL money columns are superseded by #1/CLAUDE.md integer minor units.
4. #3's `Searchable` trait + tsvector `search_index` is future design — today's global search is `SearchController`.
