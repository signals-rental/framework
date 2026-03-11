# Signals Framework - Claude Code Guidelines

## Project Overview

Signals is an open-source rental management framework — a CRM/ERP platform for equipment rental and event hire companies. It covers members/CRM, quoting/ordering (opportunities), product catalogue, stock/inventory, invoicing/payments, crew/services, projects, and inspections.

The system is **API-first** with drop-in compatibility for **Current RMS (CRMS)** — identical field names, Ransack-compatible query syntax, and matching API response shapes. The web UI and API share business logic through action classes and Spatie Laravel Data DTOs.

Key architectural traits:
- **Members as universal entity** — contacts, organisations, venues, and users are all `members` differentiated by `membership_type`
- **Plugin system** — Composer-based plugins with config-driven rendering, no plugin-owned migrations or views
- **Open-source core + commercial SaaS** — the OSF is tenant-ignorant; multi-tenancy is a separate `signals/cloud` package using database-per-tenant isolation
- **PostgreSQL only** — leverages JSONB, functional indexes, and vector search
- **Custom fields via EAV** — stored relationally, presented as flat JSON in API responses for CRMS compatibility
- **65+ tables** implemented in phased domain slices (see `framework-plans/`)

## Stack & Versions

- **PHP** 8.4 / **Laravel** 12 (streamlined structure)
- **Livewire** 4
- **Pest** 4 (with browser testing)
- **Laravel Pint** (preset: `laravel`)
- **Tailwind CSS** 4 via `@tailwindcss/vite`
- **Vite** 7 with `laravel-vite-plugin`

### Key Packages

- **Spatie Laravel Data** — DTOs for input validation, internal transfer, and API response serialisation (replaces Form Requests AND API Resources)
- **Spatie Laravel Permission** — roles and permissions (`resource.action` pattern)
- **Laravel Sanctum** — API token auth with scoped abilities (`resource:action`)
- **Laravel Horizon** — Redis queue monitoring and management
- **Laravel Reverb** — WebSocket broadcast for real-time features
- **Laravel Nightwatch** — APM and observability
- **Scramble (dedoc/scramble)** — OpenAPI documentation auto-generated from code (route definitions, PHPDoc, validation rules, return types)
- **DomPDF** — PDF generation (Browsershot as optional alternative)
- **pragmarx/google2fa-laravel** — TOTP-based 2FA

## Commands

```bash
# Setup
composer setup              # install deps, generate key, migrate, build frontend

# Development
composer dev                # runs server, queue, pail, vite concurrently

# Frontend
npm run dev                 # vite dev server
npm run build               # production build

# Linting & Static Analysis
vendor/bin/pint --dirty     # fix formatting on changed files
vendor/bin/pint             # fix all files
vendor/bin/phpstan analyse  # run static analysis (level 5, app/ only)

# Testing
php artisan test --parallel --exclude-group=env-writing  # run all tests (parallel)
php artisan test --group=env-writing                     # run env-writing tests (sequential)
php artisan test --parallel tests/Feature/ExampleTest.php  # run a single file
php artisan test --parallel --filter=testName            # filter by name

# Coverage (merges parallel + env-writing runs)
composer test:coverage          # text summary to terminal
composer test:coverage-html     # HTML report to build/coverage/html
```

## Architecture (Laravel 12)

This project uses the Laravel 11+ streamlined structure:

- `bootstrap/app.php` - registers middleware, exceptions, and routing
- `bootstrap/providers.php` - application service providers
- No `app/Http/Kernel.php` or `app/Console/Kernel.php`
- No middleware files in `app/Http/Middleware/` by default
- Commands in `app/Console/Commands/` auto-register
- Routes: `routes/web.php`, `routes/console.php`, `routes/api.php`

### Directory Layout

```
app/
├── Actions/                  # Invocable action classes (business logic)
│   ├── Opportunities/        # Grouped by domain
│   ├── Invoices/
│   └── Members/
├── Console/Commands/         # Artisan commands (auto-register)
├── Data/                     # Spatie Laravel Data DTOs
│   ├── Opportunities/        # Input + Response DTOs per domain
│   ├── Invoices/
│   └── Members/
├── Enums/                    # PHP enums (TitleCase keys)
├── Events/                   # Event classes
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/           # API controllers (thin — call actions)
│   │   └── Web/              # Web controllers (if any outside Livewire)
│   └── Middleware/
├── Jobs/                     # Queued jobs
├── Listeners/                # Event listeners
├── Livewire/                 # Livewire components
│   ├── Opportunities/        # Full-page + nested components per domain
│   └── ...
├── Models/                   # Eloquent models
├── Notifications/            # Notification classes
├── Policies/                 # Authorization policies
├── Services/                 # Services (registries, calculators, helpers)
│   ├── ConnectionTesters/    # Database, Redis, S3 connectivity testers
│   ├── CacheService.php
│   ├── NotificationRegistry.php
│   ├── NavigationService.php
│   ├── SettingsService.php
│   └── ...
└── ValueObjects/             # Money, Timezone, etc.
```

## Core Architectural Patterns

### 1. Shared Service Layer (Action Classes + DTOs)

Every operation flows through a **DTO** (validates data) and an **action class** (executes logic). API controllers, Livewire components, CLI commands, and jobs all use the same path.

```
Request → DTO (validates) → Action (authorises, persists, fires events) → Response DTO
```

**Action classes** are invocable, single-responsibility:
```php
// Calling pattern (same everywhere):
$result = (new CreateOpportunity)($dto);

// Action structure:
class CreateOpportunity
{
    public function __invoke(CreateOpportunityData $data): OpportunityData
    {
        Gate::authorize('create', Opportunity::class);
        $opportunity = Opportunity::create($data->toArray());
        $opportunity->syncCustomFields($data->custom_fields);
        event(new OpportunityCreated($opportunity));
        return OpportunityData::fromModel($opportunity);
    }
}
```

**DTOs** serve triple duty — input validation, internal transfer, API response:
```php
// Input DTO (in App\Data\Opportunities):
class CreateOpportunityData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $subject,
        #[Required, Exists('members', 'id')]
        public int $member_id,
        // ...
    ) {}
}

// Response DTO (in App\Data\Opportunities):
class OpportunityData extends Data
{
    public function __construct(
        public int $id,
        public string $subject,
        // Lazy-loaded relationships:
        /** @var Lazy|OpportunityItemData[] */
        public Lazy|array $items,
    ) {}

    public static function fromModel(Opportunity $model): self { /* ... */ }
}
```

**Rules:**
- Do NOT create Form Request classes — use Spatie Data DTOs with `#[Validate]` attributes instead
- Do NOT create API Resource classes — use response DTOs with `fromModel()` instead
- API controllers are thin: construct DTO → call action → return response DTO
- Livewire components: construct DTO from form properties → call action → update state
- Always use `Gate::authorize()` inside actions, not in controllers

### 2. Registry Pattern

Used for all extensible feature registrations. Each registry follows the same shape:

```php
class SomeRegistry
{
    /** @var array<string, SomeDefinition> */
    private array $definitions = [];

    public function register(SomeDefinition $definition): void { /* ... */ }
    public function get(string $key): SomeDefinition { /* ... */ }
    public function all(): array { /* ... */ }
}
```

Active registries: `NotificationRegistry`, `WidgetRegistry`, `ReportRegistry`, `NavigationService`, `SettingsRegistry`, `ColumnRegistry`.

Plugins extend registries via `PluginRegistrar` in their `register()` method.

### 3. Multi-Tenancy Contracts

The OSF is **completely tenant-unaware**. No `tenant_id` columns, no tenancy code in core.

Three contracts exist for the commercial `signals/cloud` package to implement:
- `DatabaseConnectionResolver` — resolves tenant to DB connection
- `CacheKeyResolver` — prefixes cache keys with tenant ID
- `StoragePathResolver` — prefixes storage paths with tenant slug

OSF ships single-tenant defaults (no-ops). Never add tenant awareness to core code.

## Conventions

### General

- Follow existing code conventions. Check sibling files for structure, approach, naming.
- Use descriptive names: `isRegisteredForDiscounts`, not `discount()`.
- Reuse existing components before creating new ones.
- Don't create new base folders or change dependencies without approval.
- Don't create documentation files unless explicitly requested.
- Consult `framework-plans/` for architectural decisions before implementing a feature.

### UI Components

- Check `framework-plans/component-library.md` before building any interface.
- Use `<x-signals.*>` Blade components for structural containers (card, panel, page-header, etc.).
- Use `s-*` CSS classes directly for inline elements (badge, btn, chip, status, etc.).
- Never create custom styling that duplicates an existing `s-` component.
- All `s-` CSS is globally available via `app.css` — do not add `s-` tokens to page `<style>` blocks.

### PHP

- Always use curly braces for control structures, even single-line.
- Use PHP 8.4 constructor property promotion. No empty zero-parameter constructors.
- Always use explicit return types and parameter type hints.
- Prefer PHPDoc blocks over inline comments. Only comment genuinely complex logic.
- Add array shape type definitions in PHPDoc when appropriate.
- Enum keys should be TitleCase: `FavoritePerson`, `Monthly`.

### Naming Conventions

| Thing | Pattern | Example |
|-------|---------|---------|
| Action class | `{Verb}{Entity}` | `CreateOpportunity`, `IssueInvoice`, `AllocateStock` |
| Input DTO | `{Verb}{Entity}Data` | `CreateOpportunityData`, `UpdateMemberData` |
| Response DTO | `{Entity}Data` | `OpportunityData`, `InvoiceData` |
| Service | `{Name}Service` / `{Name}Registry` | `SettingsService`, `NotificationRegistry` |
| Job | `{Verb}{Entity}Job` or `{Verb}{Entity}` | `DeliverWebhook`, `ProcessCsvImport` |
| Event | `{Entity}{PastTenseVerb}` | `OpportunityCreated`, `InvoiceIssued` |
| Notification | `{Entity}{PastTenseVerb}Notification` | `OpportunityStatusChanged` |
| Permission | `resource.action` | `opportunities.create`, `invoices.issue` |
| API ability | `resource:action` | `opportunities:read`, `invoices:write` |
| Webhook event | `resource.action` | `opportunity.created`, `invoice.issued` |
| Settings key | `group.key` | `company.name`, `email.smtp_host` |
| Cache key | `domain:entity:id` | `settings:all`, `custom-fields:opportunities` |
| Enum | TitleCase keys | `OpportunityState::Quotation` |

### Laravel

- Use `php artisan make:*` commands with `--no-interaction` to create files.
- Use `artisan make:class` for generic PHP classes.
- Prefer Eloquent relationships over raw queries. Never use `DB::` facade; always use `Model::query()` or Eloquent. For framework tables without models (e.g. `jobs`, `failed_jobs`), create a simple model or use the framework's built-in model classes.
- Use eager loading to prevent N+1 problems.
- **Do NOT create Form Request classes** — use Spatie Data DTOs for validation instead.
- Use named routes and `route()` for URL generation.
- Never use `env()` outside config files. Use `config()` instead.
- Use queued jobs with `ShouldQueue` for all long-running operations. Never block web requests.
- Use gates and policies for authorisation. Check inside action classes.
- When creating models, also create factories and seeders.
- **Do NOT create API Resource classes** — use response DTOs with `fromModel()` instead.
- Column modifications in migrations must re-declare all existing attributes.
- Use `casts()` method on models (not `$casts` property). Follow existing model conventions.
- Settings: use `settings('group.key')`, never `env()` or `config()` for runtime settings.

### Database

- **PostgreSQL only.** Use JSONB (not JSON) for flexible columns. Use functional indexes.
- **Money columns:** `INTEGER` — stored in minor units (pence, cents, fils). Use `brick/money` value objects with `finller/laravel-money` Eloquent casts. Never use floats or DECIMAL for money.
- **Money in API responses:** decimal strings (`"125.50"`), not floats or raw integers. Match CRMS format. Conversion from minor units happens at the serialisation layer.
- **Timestamps:** always `created_at` + `updated_at`. All stored in UTC.
- **Primary keys:** auto-incrementing integers (`id`). Not UUIDs. CRMS compatibility.
- **Foreign keys:** `{table_singular}_id` (e.g. `member_id`). Always constrained + indexed.
- **Polymorphic columns:** `{relation}_type` + `{relation}_id` (e.g. `attachable_type`, `attachable_id`).
- **Soft deletes:** only on `opportunities`, `invoices`, `members`. Not on reference data.
- **JSONB** for: custom field metadata, validation rules, plugin settings, flexible config.
- **Boolean columns:** prefix with `is_` (e.g. `is_active`, `is_primary`).
- Use migrations for all schema changes. Never manually modify the database.

### API

- **URL prefix:** `/api/v1/` for all endpoints.
- **Authentication:** Sanctum bearer tokens. Abilities: `resource:action`.
- **Response shape:** single resource wrapped in singular key `{"opportunity": {...}}`, collections in plural key `{"opportunities": [...], "meta": {...}}`.
- **Pagination:** offset-based `?page=2&per_page=20`. Meta: `total`, `per_page`, `page`.
- **Filtering:** Ransack-compatible `?q[field_predicate]=value`. Predicates: `_eq`, `_not_eq`, `_lt`, `_lteq`, `_gt`, `_gteq`, `_cont`, `_not_cont`, `_start`, `_end`, `_present`, `_blank`, `_null`, `_not_null`, `_in`, `_not_in`, `_true`, `_false`.
- **Includes:** `?include=items,costs` for lazy-loaded relationships.
- **Custom fields in responses:** flat JSON `{"custom_fields": {"po_reference": "PO-123"}}`.
- **Custom field queries:** `?q[cf.field_name_eq]=value` or `?q[field_id_eq]=value`.
- **Dates:** ISO 8601 format in UTC (`2026-01-15T14:30:00Z`).
- **Errors:** Laravel default `{"message": "...", "errors": {"field": ["..."]}}`.
- **Async operations:** return `202 Accepted` with `job_id`.
- **API docs:** Scramble auto-generates OpenAPI specs from controllers. Use PHPDoc `@param`, `@response`, and typed return values to enrich docs. Access docs at `/docs/api`. Config: `config/scramble.php`.

### Livewire

- Create components with `php artisan make:livewire`.
- Components require a single root element.
- State lives on the server. Always validate and authorize in Livewire actions.
- Use `wire:loading`, `wire:dirty` for loading states.
- Always add `wire:key` in loops.
- Use lifecycle hooks: `mount()`, `updatedFoo()`.
- Use `wire:navigate` for SPA-like page transitions.
- Full-page component patterns: `Index` (list with table), `Show` (detail), `Form` (create/edit).
- Construct DTOs from form properties, call action classes — same pattern as API controllers.

### Jobs & Queues

- **Driver:** Redis primary, `database` fallback for self-hosted without Redis.
- **Named queues:** `default`, `webhooks`, `notifications`, `exports`, `imports`, `mail`.
- All jobs implement `ShouldQueue`. Use appropriate middleware:
  - `RateLimited` for external API calls
  - `WithoutOverlapping` for jobs that shouldn't run concurrently
  - `ThrottlesExceptions` for transient external failures
- Use `Bus::batch()` for bulk operations, `Bus::chain()` for sequential workflows.
- Track long-running job progress via Cache: `job-progress:{class}:{id}`.
- **Horizon** manages all queues in production. Dashboard gated behind super-admin.

### Caching

- **Driver:** Redis primary (db0=cache, db1=queues, db2=sessions, db3=reverb).
- Cache-aside pattern: `Cache::remember($key, $ttl, $callback)`.
- Tag-based invalidation for grouped data (e.g. `Cache::tags(['settings'])->flush()`).
- Key format: `domain:entity:identifier` (e.g. `settings:all`, `navigation:42`).
- **What's cached:** settings (indefinite), permissions (Spatie built-in), navigation (1hr), custom field definitions (indefinite), exchange rates (24hr), dashboard widgets (5min), plugin registry (indefinite).
- Invalidate on write — never rely solely on TTL for data that changes via user action.

### Notifications

- Multi-channel: database (in-app), email (queued), broadcast (Reverb).
- Each notification type registered in `NotificationRegistry` with default channels.
- User preferences per notification type per channel (stored in `notification_preferences`).
- Email templates stored in DB, rendered with Blade.
- Real-time via Reverb private channel: `App.Models.User.{id}`.

### Settings

- Stored in `settings` table. Access: `settings('group.key')`.
- Grouped by definition classes: `CompanySettings`, `EmailSettings`, `SecuritySettings`, etc.
- Cached indefinitely, busted on write via tag invalidation.
- Email/SMTP credentials stored encrypted.
- Never use `config()` or `env()` for user-configurable values — use `settings()`.

### Permissions

- **Spatie Laravel Permission** with `resource.action` naming.
- Built-in roles: `owner`, `admin`, `manager`, `operator`, `viewer`.
- Owner has implicit all-access (flag on membership, not a permission).
- Plugins register permissions via `PluginRegistrar`.
- Check in action classes via `Gate::authorize()`.
- API abilities match but use colon: `opportunities:read`, `invoices:write`.

### Custom Fields

- EAV storage: `custom_field_values` table with typed columns (`value_string`, `value_integer`, `value_decimal`, `value_boolean`, `value_date`, `value_datetime`, `value_time`, `value_json`).
- Definitions in `custom_fields` table with validation rules (JSONB), visibility rules.
- API: serialised as flat JSON `{"custom_fields": {"field_name": "value"}}`.
- Queryable via Ransack: `?q[cf.field_name_eq]=value`.
- Auto-copy between entities (e.g. Opportunity → Invoice when field name + type match).

### Files & Attachments

- **S3 only.** UUID-based paths. Signed temporary URLs for all downloads.
- Polymorphic `attachments` table (`attachable_type`, `attachable_id`).
- Optional ClamAV virus scanning via queued job.
- Thumbnails via Intervention Image (300×300 attachments, 150×150 icons).

### Multi-Currency & Localisation

- `currencies` table (ISO 4217), `exchange_rates` table with effective dates.
- Financial entities store `currency_code` + `exchange_rate` snapshot at creation time.
- `brick/money` value objects with `RationalMoney` for lossless intermediate arithmetic. `finller/laravel-money` for Eloquent casts. Never use floats for money.
- All dates stored UTC. Display in user's timezone via `Timezone` helper.
- `@localdate` / `@localdatetime` Blade directives for display.
- English-only UI (v1). All user-facing strings use `__()` for future i18n.

### Webhooks

- Registration: `POST /api/v1/webhooks` with URL + event subscriptions.
- Delivery: queued on `webhooks` queue, HMAC-SHA256 signed, exponential backoff (6 retries over 12 hours).
- Auto-disable after 3 consecutive days of failures.
- All deliveries logged in `webhook_logs` table.

### Audit Trail

- `action_logs` table: `user_id`, `action`, `auditable_type`, `auditable_id`, `old_values` (JSONB), `new_values` (JSONB), `ip_address`, `user_agent`.
- Action classes fire `AuditableEvent` → `LogAction` listener records automatically.
- Configurable retention with scheduled pruning.
- API: `GET /api/v1/actions` (read-only, requires `action-log.view`).

### Plugins

- Composer packages implementing `Plugin` contract with `signals.json` manifest.
- `PluginRegistrar` fluent API for: routes, events, actions, custom fields, widgets, notifications, reports, nav items, permissions, settings.
- **No plugin-shipped views** — config-driven rendering only.
- **No plugin-owned migrations** — extend via custom fields and JSONB.
- Lifecycle: discover → validate → register → activate → boot → disable.

### Testing (Pest 4)

- All tests use Pest. Create with `php artisan make:test --pest <name>` (add `--unit` for unit tests).
- Tests live in `tests/Feature/` and `tests/Unit/`. Browser tests in `tests/Browser/`.
- Never remove tests without approval.
- **Test coverage target: 90% line coverage.** Run `composer test:coverage` to measure. The script merges parallel and `env-writing` group runs via `bin/coverage-merge.php`.
- Every new feature or bug fix must include tests. Test happy paths, failure paths, edge cases, and error handling branches — untested code paths are considered incomplete work.
- Interactive CLI commands using Laravel Prompts (`text()`, `select()`, `confirm()`, `password()`) are testable via `expectsQuestion()`, `expectsChoice()`, and `expectsConfirmation()` — Laravel auto-enables prompt fallbacks in tests.
- Use model factories in tests. Check for existing factory states before manual setup.
- Use specific assertion methods: `assertForbidden()`, not `assertStatus(403)`.
- Use datasets for tests with repeated data (especially validation rules).
- Import mocks via `use function Pest\Laravel\mock;` or use `$this->mock()` per existing convention.
- Resolve dependencies via `app()` container in production code to enable mock injection in tests.
- Run the minimal relevant tests after changes. Ask before running the full suite.
- Test actions directly: `$result = (new CreateOpportunity)($dto)` — don't only test via HTTP.
- Use `Queue::fake()`, `Event::fake()`, `Notification::fake()` to isolate side effects.

### Static Analysis (PHPStan)

- **PHPStan level 5** is enforced on all code in `app/`. Config: `phpstan.neon`.
- Run `vendor/bin/phpstan analyse` before finalizing changes.
- All code must pass with zero errors. Do not suppress errors with `@phpstan-ignore` without approval.

### Formatting

- Run `vendor/bin/pint --dirty` before finalizing changes.

## Framework Plans

All architectural decisions are documented in `framework-plans/`. **Always consult the relevant plan before implementing a feature.** Plans cover:

| Plan | Key Topics |
|------|-----------|
| `implementation-proposal.md` | 9-phase build order, spec inventory, dependency graph, MVP milestones |
| `data-model-implementation.md` | 65+ tables across 8 phases, column definitions, dependency graph |
| `api-architecture.md` | Shared service layer, DTOs, Ransack engine, Sanctum, webhooks |
| `field-registry-schema-engine.md` | Unified field metadata, three-source schema merge, consumer interface |
| `permissions-authorisation-engine.md` | Four-layer auth: UI areas, actions, cost visibility, store scoping |
| `custom-fields.md` | EAV storage, 17 field types, validation, visibility rules, auto-copy |
| `settings-and-administration.md` | Admin panel, roles, settings registry, taxation, audit trail, templates |
| `first-run-and-setup.md` | CLI installer, web wizard, module system, preset profiles |
| `navigation-and-ui-shell.md` | Sidebar, NavigationService, Blade components, Livewire page patterns |
| `opportunity-lifecycle.md` | Two-axis state model, hybrid event sourcing via Verbs, quote versioning |
| `availability-engine.md` | Demand-based availability, tstzrange snapshots, multi-store, plugin-extensible |
| `rate-definitions-rate-engines.md` | Composable rate engine, calculation strategies, CRMS presets |
| `discount-pricing-rules-engine.md` | Four-stage pricing pipeline, price categories, discount rules, surcharges |
| `multi-currency-tax-engine.md` | brick/money, integer minor-unit storage, tax resolution engine, exchange rates |
| `localisation-timezone-formatting.md` | Timezone helper, Formatter service, countries table, i18n architecture |
| `serialised-containers.md` | Template-driven containers, dissolve-on-dispatch, scanning integration |
| `shortage-resolution-sub-hires.md` | Shortage detection, resolver registry, virtual stock, sub-hire POs |
| `scheduling-resource-allocation-engine.md` | Crew, vehicle, facility, equipment scheduling, conflict detection |
| `approval-chain-engine.md` | Approval gates as workflow steps, multi-stage chains, escalation |
| `maintenance-asset-lifecycle-engine.md` | Inspections, certifications, quarantine, cost tracking, disposal |
| `scanning-abstraction-layer.md` | Three-layer scanning: input adapters, resolution, context handlers |
| `document-template-engine.md` | Blade templates per store, rendering pipeline, field browser, public sharing, numbering |
| `notification-communication-engine.md` | Multi-channel notifications, customer comms, three-layer resolution |
| `import-export-migration-engine.md` | SQLite-staged import, field mapping, CRMS migration, cross-references |
| `reporting-framework.md` | Aggregated queries, field registry integration, dimensions, measures |
| `workflows.md` | No-code automation, handler system, events, conditions, delays |
| `plugin-system.md` | Plugin SDK, YAML manifest, hooks, mediated data access, lifecycle |
| `custom-views.md` | Saved list configs (columns, filters, sort), visibility levels, API view_id |
| `dashboard.md` | Section layout, widgets, real-time via Reverb, shared dashboards |
| `search.md` | PostgreSQL full-text search, tsvector/GIN, global search modal |
| `file-and-attachment-system.md` | S3 storage, polymorphic attachments, signed URLs, virus scanning |
| `queue-and-job-infrastructure.md` | Redis/Horizon, named queues, job patterns, scheduling, progress tracking |
| `caching-strategy.md` | Redis config, cache map, invalidation patterns, tenant isolation |
| `sso-and-enterprise-auth.md` | Socialite, SAML 2.0, OIDC, SSO connections, MFA enforcement |
| `observability.md` | Nightwatch, structured logging, health checks, error tracking, alerting |
| `cloud-multi-tenancy.md` | DB-per-tenant, 3 contracts, tenant-ignorant OSF, SaaS provisioning |
| `backup-and-disaster-recovery.md` | pg_dump, WAL archiving, tenant export/import, DR procedures |

## Pre-Commit Quality Gate

Before every commit, the following checks **must** pass in order:

1. **Tests:** `php artisan test --parallel --compact --exclude-group=env-writing && php artisan test --compact --group=env-writing` (relevant tests, or full suite if changes are broad)
2. **Formatting:** `vendor/bin/pint --dirty --format agent`
3. **Static analysis:** `vendor/bin/phpstan analyse`
4. **Code review:** Run `pr-review` agents to catch silent failures, code quality issues, and test gaps
5. **Fix and repeat:** If any review findings are actionable, fix them and re-run steps 1-4 until clean
6. **OSS review (recommended):** Run `oss-maintainer` skill for documentation, component library, and duplication checks

Do not skip any step. Do not commit with known failing tests or phpstan errors.

## Git Workflow

- Do not auto-commit. Always ask before committing.
- Always ask before pushing to remote.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.18
- laravel/ai (AI) - v0
- laravel/cashier (CASHIER) - v15
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/nightwatch (NIGHTWATCH) - v1
- laravel/octane (OCTANE) - v2
- laravel/pennant (PENNANT) - v1
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/sanctum (SANCTUM) - v4
- laravel/scout (SCOUT) - v10
- laravel/socialite (SOCIALITE) - v5
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- livewire/volt (VOLT) - v1
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `configuring-horizon` — Configures Laravel Horizon for Redis queue management. Activate when the user explicitly mentions Horizon by name. Covers Horizon installation, supervisor configuration, worker processes, dashboard authorization, auto-scaling, balancing strategies, job monitoring, metrics, tags, and notifications. Also applies when troubleshooting Horizon-specific issues such as blank metrics, LongWaitDetected alerts, or misconfigured Horizon workers. Do NOT activate for generic queue, Redis, or job monitoring questions that do not mention Horizon.
- `pennant-development` — Manages feature flags with Laravel Pennant. Activates when creating, checking, or toggling feature flags; showing or hiding features conditionally; implementing A/B testing; working with @feature directive; or when the user mentions feature flags, feature toggles, Pennant, conditional features, rollouts, or gradually enabling features.
- `fluxui-development` — Develops UIs with Flux UI Free components. Activates when creating buttons, forms, modals, inputs, dropdowns, checkboxes, or UI components; replacing HTML form elements with Flux; working with flux: components; or when the user mentions Flux, component library, UI components, form fields, or asks about available Flux components.
- `livewire-development` — Develops reactive Livewire 4 components. Activates when creating, updating, or modifying Livewire components; working with wire:model, wire:click, wire:loading, or any wire: directives; adding real-time updates, loading states, or reactivity; debugging component behavior; writing Livewire tests; or when the user mentions Livewire, component, counter, or reactive UI.
- `volt-development` — Develops single-file Livewire components with Volt. Activates when creating Volt components, converting Livewire to Volt, working with @volt directive, functional or class-based Volt APIs; or when the user mentions Volt, single-file components, functional Livewire, or inline component logic in Blade files.
- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.
- `ai-sdk-development` — Builds AI agents, generates text and chat responses, produces images, synthesizes audio, transcribes speech, generates vector embeddings, reranks documents, and manages files and vector stores using the Laravel AI SDK (laravel/ai). Supports structured output, streaming, tools, conversation memory, middleware, queueing, broadcasting, and provider failover. Use when building, editing, updating, debugging, or testing any AI functionality, including agents, LLMs, chatbots, text generation, image generation, audio, transcription, embeddings, RAG, similarity search, vector stores, prompting, structured output, or any AI provider (OpenAI, Anthropic, Gemini, Cohere, Groq, xAI, ElevenLabs, Jina, OpenRouter).

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pennant/core rules ===

# Laravel Pennant

- This application uses Laravel Pennant for feature flag management, providing a flexible system for controlling feature availability across different organizations and user types.
- IMPORTANT: Always use `search-docs` tool for version-specific Pennant documentation and updated code examples.
- IMPORTANT: Activate `pennant-development` every time you're working with a Pennant or feature-flag-related task.

=== fluxui-free/core rules ===

# Flux UI Free

- Flux UI is the official Livewire component library. This project uses the free edition, which includes all free components and variants but not Pro components.
- Use `<flux:*>` components when available; they are the recommended way to build Livewire interfaces.
- IMPORTANT: Activate `fluxui-development` when working with Flux UI components.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces using only PHP — no JavaScript required.
- Instead of writing frontend code in JavaScript frameworks, you use Alpine.js to build the UI when client-side interactions are required.
- State lives on the server; the UI reflects it. Validate and authorize in actions (they're like HTTP requests).
- IMPORTANT: Activate `livewire-development` every time you're working with Livewire-related tasks.

=== volt/core rules ===

# Livewire Volt

- Single-file Livewire components: PHP logic and Blade templates in one file.
- Always check existing Volt components to determine functional vs class-based style.
- IMPORTANT: Always use `search-docs` tool for version-specific Volt documentation and updated code examples.
- IMPORTANT: Activate `volt-development` every time you're working with a Volt or single-file component-related task.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

=== laravel/ai rules ===

## Laravel AI SDK

- This application uses the Laravel AI SDK (`laravel/ai`) for all AI functionality.
- Activate the `developing-with-ai-sdk` skill when building, editing, updating, debugging, or testing AI agents, text generation, chat, streaming, structured output, tools, image generation, audio, transcription, embeddings, reranking, vector stores, files, conversation memory, or any AI provider integration (OpenAI, Anthropic, Gemini, Cohere, Groq, xAI, ElevenLabs, Jina, OpenRouter).

</laravel-boost-guidelines>
