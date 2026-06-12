# Members Exemplar — Copy-From File Map

The Members module is the canonical vertical slice. For each layer of a new module, open the files below and mirror them. Where Phase-2 modules (Products, Activities) are newer, the **deltas** section at the end overrides.

Verified against the codebase 2026-06-09.

## 1. Model layer

| File | Shows |
|------|-------|
| `app/Models/Member.php` | `HasSchema` implementation (`defineSchema()` with searchable/filterable fields), traits (`HasCustomFields`, `HasAttachments`, `HasFactory`, `SoftDeletes`, `FormatsMoney`), `casts()` method, morph relations (addresses/emails/phones/links/activities), scopes |
| `app/Enums/MembershipType.php` | String-backed enum, TitleCase keys, `label()` helper |
| `database/migrations/2026_03_11_000010_create_members_table.php` | Soft deletes, indexes on type/active/name columns |
| `database/factories/MemberFactory.php` | States: `organisation()`, `contact()`, `venue()`, `user()`, `inactive()`, `withMembership()` — one state per variant, no hard-coded IDs |
| `database/seeders/DatabaseSeeder.php` | First-run seeder — register any new system/reference seeder in its `$this->call([...])` list |
| `database/seeders/DemoDataSeeder.php` | Demo records (`signals:seed-demo`); tag with `'demo-data'` in `tag_list` — `SignalsClearDemoCommand` deletes by `whereJsonContains('tag_list', 'demo-data')`, so untagged demo data is unremovable |

## 2. Actions + DTOs

`app/Actions/Members/` — every action: invocable, `Gate::authorize` first, persist, `syncCustomFields`, fire `AuditableEvent`, dispatch webhook event.

| Action | Notes |
|--------|-------|
| `CreateMember.php` / `UpdateMember.php` | The canonical create/update shape incl. custom-field validation + sync |
| `ArchiveMember.php` / `RestoreMember.php` | Archive = `is_active=false` + soft delete; restore reverses. Fire `member.archived` / `member.restored` |
| `DeleteMember.php` | Hard delete + `member.deleted` |
| `MergeMember.php` | Transactional merge: migrate polymorphics, dedup relationships, skip duplicate custom-field values, archive secondary, fire `member.merged` with metadata |
| `AnonymiseMember.php` | GDPR pattern if PII applies |

`app/Data/Members/` — `MemberData` (response, `fromModel()`, `Lazy` relations, `MapOutputName`, flat `custom_fields`), `CreateMemberData` (validation attributes incl. `custom_fields` array), `UpdateMemberData` (nullable/Optional PATCH semantics), `MergeMemberData`.

## 3. API

| File | Shows |
|------|-------|
| `app/Http/Controllers/Api/V1/MemberController.php` | Thin controller: `use FiltersQueries, ResourceActions;` `$allowedFilters`, `$filterAliases` (`'active' => 'is_active'`), `$allowedSorts`, `$allowedIncludes`, `$defaultIncludes`, `$customFieldModule`, `applyViewOrFilters()` for `view_id`, sparse view-column responses |
| `app/Http/Traits/FiltersQueries.php`, `app/Http/Traits/ResourceActions.php` | The shared query/action plumbing — never reimplement |
| `app/Http/Controllers/Api/V1/Member{Address,Email,Phone,Link,Relationship}Controller.php` | Nested-resource pattern (`except(['show'])` / `only([...])`) |
| `routes/api.php` | `Route::apiResource('members', ...)` + nested resources, `.names('api.v1.members')` |

## 4. List page (the full datatable contract)

| File | Shows |
|------|-------|
| `resources/views/livewire/members/index.blade.php` | Volt page: `#[Url] $typeFilter` / `#[Url] $archiveFilter='active'`, type-chip counts, `archiveMember/restoreMember/archiveSelected`, datatable invocation (grep `<livewire:components.data-table`), `<livewire:members.merge-modal />` |
| `app/Views/MemberColumnRegistry.php` (+ base `ColumnRegistry.php`, `Column.php`) | Column metadata (label, sortable, filterable, type), `defaultColumns()` |
| `app/Livewire/Components/DataTable.php` + `resources/views/livewire/components/data-table.blade.php` | The shared datatable — multiselect, sortable columns, pagination (12/24/48), `#[Url]` search/sort/filters |
| `resources/views/livewire/members/partials/toolbar.blade.php` | `<x-signals.column-toggle />` + `<x-signals.export-button />` |
| `resources/views/livewire/members/partials/bulk-actions.blade.php` | `<x-signals.bulk-bar>`: merge (when exactly 2 selected) + archive with `<x-signals.modal>` confirm |
| `resources/views/livewire/members/partials/row-actions.blade.php` | Ellipsis menu: edit / archive / restore / delete |
| `resources/views/livewire/members/partials/column-*.blade.php` | Column renderers: avatar, name-link, type badge (`s-badge-*`), email, phone, tags, created |
| `app/Livewire/Members/MergeModal.php` + `merge-modal.blade.php` | Merge comparison modal |
| `database/seeders/ViewSeeder.php` | 3–5 system views per entity built from `ColumnRegistry->defaultColumns()` + Ransack filter arrays, one `isDefault: true` |

The invocation to mirror (in `members/index.blade.php`, grep `<livewire:components.data-table`):

```blade
<livewire:components.data-table
    :columns="$columns"
    :model="\App\Models\Member::class"
    :searchable="['name']"
    :with="['emails', 'phones']"
    :with-counts="['addresses', 'emails', 'phones', 'links']"
    :scopes="$scopes"
    :refresh-events="['member-archived', 'member-restored', 'member-merged']"
    default-sort="name"
    empty-message="No members found."
    actions-view="livewire.members.partials.row-actions"
    bulk-actions-view="livewire.members.partials.bulk-actions"
    toolbar-view="livewire.members.partials.toolbar"
    entity-type="members"
    :key="'members-table-' . $typeFilter . '-' . $archiveFilter"
/>
```

## 5. Show page

| File | Shows |
|------|-------|
| `resources/views/livewire/members/show.blade.php` | Volt detail page loading relation counts |
| `partials/member-header.blade.php` | `<x-signals.page-header>`: breadcrumbs, entity icon, meta badges (type + active/archived), Edit link, `<x-signals.split-button>` "New" quick-actions menu (create related records) |
| `partials/member-tabs.blade.php` | Tab nav to one route per related model (addresses, emails, phones, links, custom-fields, relationships, activities, files, …) |
| Tab views (`addresses.blade.php`, `activities.blade.php`, `files.blade.php`, `custom-fields.blade.php`, …) | Related-model listing per tab; activity timeline; attachments via the shared upload components |

## 6. Form page

`resources/views/livewire/members/form.blade.php` — single Volt component for create + edit (routes `/{plural}/create` and `/{plural}/{id}/edit` in `routes/web.php`), builds Create/Update DTO → calls the same action the API uses, renders custom fields from definitions, uses `<x-signals.field>` / form components.

## 7. Permissions

| File | Shows |
|------|-------|
| `database/seeders/PermissionSeeder.php` | `members.access` (area) → `members.view` → `create`/`edit` → `delete` dependency chain |
| `database/seeders/RoleSeeder.php` | Distribution across Admin / Operations Manager / Sales / Warehouse / Read Only |
| `app/Policies/MemberPolicy.php` | `AuthorizesByPermission` trait mapping policy methods → permission strings |

## 8. Search, palette, navigation

| File | Shows |
|------|-------|
| `app/Http/Controllers/Web/SearchController.php` | Per-entity block: `Gate::allows('members.view')` → query → max 8 results with `{id, name, type, isActive, initials, url}` |
| `resources/views/components/signals/command-palette.blade.php` | Navigation entry (grep `group: 'Navigation', label: 'Members'`), Create entry (grep `group: 'Create', label: 'New Member'`), live search wiring (grep `searchMembers`) |
| `resources/views/components/layouts/app/header.blade.php` | Mega-menu + sidebar entries, `routeIs('members.*')` active state, gated on `members.access` |

> **Reality check:** there is NO `NavigationService` class and NO `Searchable` trait/`search_index` table yet, whatever the plans say. Nav = header blade + palette; global search = SearchController blocks.

## 9. Docs

`docs/platform/members.md` (user guide), `docs/api/members.md` (endpoints, filters, includes, abilities), entries in `docs/documentation.json` manifest. Format per the **generate-docs** skill (front-matter title + description <160 chars, h2/h3 only).

## 10. Tests

| File | Covers |
|------|--------|
| `tests/Feature/Actions/Members/*` (per action) | Direct invocation, `Event::fake([AuditableEvent::class])`, merge dedup + rollback, archive/restore |
| `tests/Feature/Api/MemberApiTest.php` | CRUD, wrapper keys, abilities, validation, 401/403 |
| `tests/Feature/Api/MemberApiViewTest.php` | `view_id` + sparse columns |
| `tests/Feature/Api/MemberCustomFieldFilterTest.php` | `q[cf.*]` filtering |
| `tests/Feature/Models/MemberTest.php`, `tests/Feature/Policies/MemberPolicyTest.php`, `tests/Feature/Livewire/Members/*` | Casts/scopes/schema, role matrix, page behaviour |

Conventions: seed Permission/Role seeders in `beforeEach`, authenticate as owner, factory states over manual setup, Ransack `_eq`-family only over the SQLite test DB (pg-only predicates are unit-tested in `RansackFilterTest`).

## List-sync registrations (modify, don't create — the silent-failure zone)

Every new module MUST touch all of these. Forgetting them caused UAT defects D5 (webhooks) and D6 (schema cache):

| File | Add |
|------|-----|
| `app/Http/Controllers/Api/V1/SchemaController.php` | `'{plural}' => Model::class` in the `MODEL_MAP` const (grep `MODEL_MAP =`) |
| `app/Services/Api/WebhookService.php` | EVERY event the actions dispatch, in the `EVENTS` const (grep `const EVENTS`) |
| `app/Services/ColumnRegistryResolver.php` | `'{plural}' => {Entity}ColumnRegistry::class` in `$map` (grep `private array $map`) — `resolve()` is the single source of truth feeding both `DataTable::getColumnRegistry()` and `CreateCustomView`/`UpdateCustomView` column validation. One registration wires up the shared datatable too — there is no separate `DataTable.php` match to edit. Missing here = custom-view create/update silently skips validation AND the datatable can't resolve columns. Guarded by the filesystem-scan meta-test `tests/Feature/Services/ColumnRegistryResolverTest.php` (auto-discovers every concrete `app/Views/` registry and fails if one isn't mapped under its own `entityType()` key — no per-module assertion to add) |
| `database/seeders/PermissionSeeder.php` + `RoleSeeder.php` | Permission chain + role grants |
| `database/seeders/ViewSeeder.php` | System views |
| `database/seeders/ListOfValuesSeeder.php` | Any LOV-backed type lists |
| `database/seeders/DatabaseSeeder.php` | New system/reference seeder in the first-run `call([...])` list |
| `database/seeders/DemoDataSeeder.php` | Demo records, tagged `'demo-data'` in `tag_list` (clear-demo compatible) |
| `app/Enums/FeatureProfile.php` | Module key in EVERY profile's `modules()` map (DryHire/FullService/Crew/General/Minimal) — the only place modules are registered now the admin Modules page is removed |
| `routes/api.php` / `routes/web.php` | Resource + Volt routes |
| `resources/views/components/layouts/app/header.blade.php` | Mega-menu + sidebar |
| `resources/views/components/signals/command-palette.blade.php` | Navigation + Create + search results |
| `app/Http/Controllers/Web/SearchController.php` | Gate-gated search block |
| `resources/views/livewire/admin/settings/custom-field-form.blade.php` | Module name in the hardcoded `moduleTypes` array (grep `'moduleTypes' =>`) — the resolver is generic, but without this users can't create the module's custom fields in the UI |
| `resources/views/livewire/admin/settings/api.blade.php` | `{resource}:read` / `{resource}:write` in the hardcoded ability list (grep `'members:read'`) |
| `docs/api/webhooks.md` | New webhook events added to the `## Events` list |
| `docs/documentation.json` | Platform + API page entries |

## Admin surfaces (where the module must show up)

All under `resources/views/livewire/admin/settings/`:

| Page | Data source | What a new module does |
|------|------------|------------------------|
| `permissions.blade.php` + `roles.blade.php`/`role-form.blade.php` | `PermissionRegistry->grouped()` (singleton built in `AppServiceProvider`, grep `singleton(PermissionRegistry`); roles from DB | Register permission keys + group metadata in `AppServiceProvider`; seed via `PermissionSeeder` (it validates against the registry) — then both pages surface them automatically |
| `api.blade.php` | Hardcoded ability list (grep `'members:read'`) | Add `{resource}:read`/`{resource}:write` entries |
| `email-templates.blade.php` | `EmailTemplate` model (DB) | Seed templates via `EmailTemplateSeeder` for any outbound comms the module sends |
| `notifications.blade.php` | `NotificationType` model | Register types in `NotificationTypeSeeder::types()` (feeds `NotificationRegistry`) |
| `scheduling.blade.php` | `settings()->group('scheduling')` | Add keys to the `scheduling` settings group + form fields if the module has scheduling-relevant config |
| `list-names.blade.php` / `lists.blade.php` | `list_names`/`list_values` | Seed via `ListOfValuesSeeder` — surfaced automatically |
| `action-log.blade.php` | `action_logs` via `AuditableEvent` → `LogAction` | Nothing extra IF every action fires `AuditableEvent` — verify rows appear; assert `ActionLog` creation in action tests |
| `tax/`, `countries.blade.php`, `rate-definitions.blade.php` | Global engines | Consume, never duplicate (see SKILL.md Global Engines table) |

## Dashboard

`resources/views/dashboard.blade.php` — Quick Actions block (grep `Quick Actions` / `.quick-action` anchors); widget components live in `app/Livewire/Dashboard/` + `resources/views/livewire/dashboard/`. There is NO WidgetRegistry yet (plans describe one; not built) — widgets are placed directly in the dashboard blade, permission-gated. NB: some existing quick-action anchors use `href="#"` placeholders — a real module's quick action must link to its `route('{plural}.create')` and be permission-gated.

## Newer-pattern deltas (follow these over Members)

- **Polymorphic "regarding"**: `app/Models/Activity.php` `$regardingMap` + MorphTo — use for generic entity references instead of many explicit FKs.
- **No soft deletes outside major entities**: Activities/Products archive via flags/status; soft deletes stay limited to members/opportunities/invoices.
- **Schema cache invalidation**: custom-field actions must bust the schema cache (UAT D6 fix in CustomField actions) — already handled centrally; don't bypass it.
- **Merge reference**: `app/Livewire/Products/MergeModal.php` is the newer merge modal if Members' diverges.
- **Webhook registration discipline**: `WebhookService::EVENTS` must list every dispatched event (UAT D5) — there is a test asserting registration; extend it.
