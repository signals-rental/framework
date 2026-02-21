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
- **Scribe (knuckleswtf/scribe)** — API documentation auto-generation
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

# Linting
vendor/bin/pint --dirty     # fix formatting on changed files
vendor/bin/pint             # fix all files

# Testing
php artisan test                              # run all tests
php artisan test tests/Feature/ExampleTest.php  # run a single file
php artisan test --filter=testName            # filter by name
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
- Prefer Eloquent relationships over raw queries. Avoid `DB::`; use `Model::query()`.
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
- **Money columns:** `DECIMAL(15, 4)` — 4 decimal places, 11 digits before decimal.
- **Money in API responses:** decimal strings (`"125.5000"`), not floats. Match CRMS format.
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
- `Money` value object with bcmath arithmetic. Never use floats for money.
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
- Test happy paths, failure paths, and edge cases.
- Use model factories in tests. Check for existing factory states before manual setup.
- Use specific assertion methods: `assertForbidden()`, not `assertStatus(403)`.
- Use datasets for tests with repeated data (especially validation rules).
- Import mocks via `use function Pest\Laravel\mock;` or use `$this->mock()` per existing convention.
- Run the minimal relevant tests after changes. Ask before running the full suite.
- Test actions directly: `$result = (new CreateOpportunity)($dto)` — don't only test via HTTP.
- Use `Queue::fake()`, `Event::fake()`, `Notification::fake()` to isolate side effects.

### Formatting

- Run `vendor/bin/pint --dirty` before finalizing changes.

## Framework Plans

All architectural decisions are documented in `framework-plans/`. **Always consult the relevant plan before implementing a feature.** Plans cover:

| Plan | Key Topics |
|------|-----------|
| `api-architecture.md` | Shared service layer, DTOs, Ransack engine, Sanctum, webhooks |
| `data-model-implementation.md` | 65+ tables across 8 phases, column definitions, dependency graph |
| `plugin-system.md` | Plugin contract, PluginRegistrar, lifecycle, distribution |
| `custom-fields.md` | EAV storage, 17 field types, validation, visibility rules, auto-copy |
| `settings-and-administration.md` | Admin panel, roles, settings registry, taxation, audit trail, templates |
| `first-run-and-setup.md` | CLI installer, web wizard, module system, preset profiles |
| `cloud-multi-tenancy.md` | DB-per-tenant, 3 contracts, tenant-ignorant OSF, SaaS provisioning |
| `dashboard.md` | Section layout, widgets, real-time via Reverb, shared dashboards |
| `custom-views.md` | Saved list configs (columns, filters, sort), visibility levels, API view_id |
| `navigation-and-ui-shell.md` | Sidebar, NavigationService, Blade components, Livewire page patterns |
| `notifications.md` | Multi-channel, registry, user preferences, email templates, broadcast |
| `search.md` | PostgreSQL full-text search, tsvector/GIN, global search modal |
| `reporting.md` | Standard reports + SQL query builder, exports, scheduling |
| `localisation-and-multi-currency.md` | Money value object, exchange rates, timezone handling, Formatter |
| `file-and-attachment-system.md` | S3 storage, polymorphic attachments, signed URLs, virus scanning |
| `queue-and-job-infrastructure.md` | Redis/Horizon, named queues, job patterns, scheduling, progress tracking |
| `caching-strategy.md` | Redis config, cache map, invalidation patterns, tenant isolation |
| `sso-and-enterprise-auth.md` | Socialite, SAML 2.0, OIDC, SSO connections, MFA enforcement |
| `backup-and-disaster-recovery.md` | pg_dump, WAL archiving, tenant export/import, DR procedures |
| `observability.md` | Nightwatch, structured logging, health checks, error tracking, alerting |

## Git Workflow

- Do not auto-commit. Always ask before committing.
- Always ask before pushing to remote.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.18
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
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs
- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The `search-docs` tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== herd rules ===

## Laravel Herd

- The application is served by Laravel Herd and will be available at: `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate URLs for the user to ensure valid URLs.
- You must not run any commands to make the site available via HTTP(S). It is always available through Laravel Herd.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version-specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pennant/core rules ===

## Laravel Pennant

- This application uses Laravel Pennant for feature flag management, providing a flexible system for controlling feature availability across different organizations and user types.
- Use the `search-docs` tool, in combination with existing codebase conventions, to assist the user effectively with feature flags.

=== fluxui-free/core rules ===

## Flux UI Free

- This project is using the free edition of Flux UI. It has full access to the free components and variants, but does not have access to the Pro components.
- Flux UI is a component library for Livewire. Flux is a robust, hand-crafted UI component library for your Livewire applications. It's built using Tailwind CSS and provides a set of components that are easy to use and customize.
- You should use Flux UI components when available.
- Fallback to standard Blade components if Flux is unavailable.
- If available, use the `search-docs` tool to get the exact documentation and code snippets available for this project.
- Flux UI components look like this:

<code-snippet name="Flux UI Component Example" lang="blade">
    <flux:button variant="primary"/>
</code-snippet>

### Available Components
This is correct as of Boost installation, but there may be additional components within the codebase.

<available-flux-components>
avatar, badge, brand, breadcrumbs, button, callout, checkbox, dropdown, field, heading, icon, input, modal, navbar, otp-input, profile, radio, select, separator, skeleton, switch, text, textarea, tooltip
</available-flux-components>

=== livewire/core rules ===

## Livewire

- Use the `search-docs` tool to find exact version-specific documentation for how to write Livewire and Livewire tests.
- Use the `php artisan make:livewire [Posts\CreatePost]` Artisan command to create new components.
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend; they're like regular HTTP requests. Always validate form data and run authorization checks in Livewire actions.

## Livewire Best Practices
- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle Hook Examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>

## Testing Livewire

<code-snippet name="Example Livewire Component Test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>

<code-snippet name="Testing Livewire Component Exists on Page" lang="php">
    $this->get('/posts/create')
    ->assertSeeLivewire(CreatePost::class);
</code-snippet>

=== volt/core rules ===

## Livewire Volt

- This project uses Livewire Volt for interactivity within its pages. New pages requiring interactivity must also use Livewire Volt.
- Make new Volt components using `php artisan make:volt [name] [--test] [--pest]`.
- Volt is a class-based and functional API for Livewire that supports single-file components, allowing a component's PHP logic and Blade templates to coexist in the same file.
- Livewire Volt allows PHP logic and Blade templates in one file. Components use the `@volt` directive.
- You must check existing Volt components to determine if they're functional or class-based. If you can't detect that, ask the user which they prefer before writing a Volt component.

### Volt Functional Component Example

<code-snippet name="Volt Functional Component Example" lang="php">
@volt
<?php
use function Livewire\Volt\{state, computed};

state(['count' => 0]);

$increment = fn () => $this->count++;
$decrement = fn () => $this->count--;

$double = computed(fn () => $this->count * 2);
?>

<div>
    <h1>Count: {{ $count }}</h1>
    <h2>Double: {{ $this->double }}</h2>
    <button wire:click="increment">+</button>
    <button wire:click="decrement">-</button>
</div>
@endvolt
</code-snippet>

### Volt Class Based Component Example
To get started, define an anonymous class that extends Livewire\Volt\Component. Within the class, you may utilize all of the features of Livewire using traditional Livewire syntax:

<code-snippet name="Volt Class-based Volt Component Example" lang="php">
use Livewire\Volt\Component;

new class extends Component {
    public $count = 0;

    public function increment()
    {
        $this->count++;
    }
} ?>

<div>
    <h1>{{ $count }}</h1>
    <button wire:click="increment">+</button>
</div>
</code-snippet>

### Testing Volt & Volt Components
- Use the existing directory for tests if it already exists. Otherwise, fallback to `tests/Feature/Volt`.

<code-snippet name="Livewire Test Example" lang="php">
use Livewire\Volt\Volt;

test('counter increments', function () {
    Volt::test('counter')
        ->assertSee('Count: 0')
        ->call('increment')
        ->assertSee('Count: 1');
});
</code-snippet>

<code-snippet name="Volt Component Test Using Pest" lang="php">
declare(strict_types=1);

use App\Models\{User, Product};
use Livewire\Volt\Volt;

test('product form creates product', function () {
    $user = User::factory()->create();

    Volt::test('pages.products.create')
        ->actingAs($user)
        ->set('form.name', 'Test Product')
        ->set('form.description', 'Test Description')
        ->set('form.price', 99.99)
        ->call('create')
        ->assertHasNoErrors();

    expect(Product::where('name', 'Test Product')->exists())->toBeTrue();
});
</code-snippet>

### Common Patterns

<code-snippet name="CRUD With Volt" lang="php">
<?php

use App\Models\Product;
use function Livewire\Volt\{state, computed};

state(['editing' => null, 'search' => '']);

$products = computed(fn() => Product::when($this->search,
    fn($q) => $q->where('name', 'like', "%{$this->search}%")
)->get());

$edit = fn(Product $product) => $this->editing = $product->id;
$delete = fn(Product $product) => $product->delete();

?>

<!-- HTML / UI Here -->
</code-snippet>

<code-snippet name="Real-Time Search With Volt" lang="php">
    <flux:input
        wire:model.live.debounce.300ms="search"
        placeholder="Search..."
    />
</code-snippet>

<code-snippet name="Loading States With Volt" lang="php">
    <flux:button wire:click="save" wire:loading.attr="disabled">
        <span wire:loading.remove>Save</span>
        <span wire:loading>Saving...</span>
    </flux:button>
</code-snippet>

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest
### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest {name}`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests that have a lot of duplicated data. This is often the case when testing validation rules, so consider this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>

=== pest/v4 rules ===

## Pest 4

- Pest 4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing
- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest 4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>

=== tailwindcss/core rules ===

## Tailwind CSS

- Use Tailwind CSS classes to style HTML; check and use existing Tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc.).
- Think through class placement, order, priority, and defaults. Remove redundant classes, add classes to parent or child carefully to limit repetition, and group elements logically.
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing; don't use margins.

<code-snippet name="Valid Flex Gap Spacing Example" lang="html">
    <div class="flex gap-8">
        <div>Superior</div>
        <div>Michigan</div>
        <div>Erie</div>
    </div>
</code-snippet>

### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.

=== tailwindcss/v4 rules ===

## Tailwind CSS 4

- Always use Tailwind CSS v4; do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.

<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>

### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option; use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
</laravel-boost-guidelines>
