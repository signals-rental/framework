# API Infrastructure Design

Date: 2026-03-10
Status: Approved
Scope: Phases 1-7 from api-architecture.md (excluding plugins)

## Summary

Build the complete API infrastructure for Signals Framework: Sanctum authentication, Ransack-compatible query engine, response formatting, rate limiting, webhook system, and concrete endpoints for settings, users, roles, system health, and action logs. Includes admin UI for API token management and Scramble-powered API documentation.

## Constraints

- No domain models exist yet (no Members, Opportunities, etc.)
- Only build endpoints for models that already exist: User, Setting, Store, ActionLog, EmailTemplate, Role
- Must match CRMS response shapes exactly
- PostgreSQL only
- Shared service layer pattern (actions + DTOs)

## Architecture

### Authentication & Token Management

Sanctum bearer tokens with scoped abilities following `resource:action` pattern.

**Token abilities:**
- `settings:read`, `settings:write`
- `users:read`, `users:write`
- `roles:read`, `roles:write`
- `webhooks:manage`
- `system:read`
- `action-log:read`

**Rate limiting:** 60 req/min authenticated, 20 req/min unauthenticated. Configurable via `settings('api.rate_limit')`.

**Admin UI:** New `admin/settings/api` Volt page for token CRUD with ability checkboxes and optional expiration.

### Base Controller Pattern (Trait-based)

`App\Http\Controllers\Api\Controller` base class:
- `respondWith($data, $key, $status)` — single resource wrapper
- `respondWithCollection($data, $key, $meta, $status)` — collection wrapper
- `respondWithError($message, $status, $errors)` — error wrapper
- `respondAccepted($jobId)` — async operation wrapper

`App\Http\Traits\FiltersQueries` trait:
- `applyFilters()` — Ransack predicate parsing
- `applySort()` — sort by field (prefix `-` for desc)
- `paginate()` — offset-based pagination

### Response Shapes (CRMS-compatible)

```json
// Single resource
{"user": {"id": 1, "name": "Ben", "email": "ben@example.com"}}

// Collection
{"users": [{"id": 1, ...}, {"id": 2, ...}], "meta": {"total": 50, "per_page": 20, "page": 1}}

// Error
{"message": "Validation failed.", "errors": {"email": ["The email field is required."]}}

// Async
{"job_id": "abc-123", "status": "accepted"}
```

### Ransack Query Engine

Standalone service: `App\Services\Api\RansackFilter`

**Full predicate set:**
`eq`, `not_eq`, `lt`, `lteq`, `gt`, `gteq`, `cont`, `not_cont`, `start`, `end`, `present`, `blank`, `null`, `not_null`, `in`, `not_in`, `true`, `false`

Each controller declares `$allowedFilters` to whitelist filterable fields.

### API Endpoints

**Settings** (`/api/v1/settings`):
- `GET /` — list groups (settings:read)
- `GET /{group}` — get group (settings:read)
- `PUT /{group}` — update group (settings:write)

**Users** (`/api/v1/users`):
- `GET /` — list with filtering/pagination (users:read)
- `GET /{id}` — show user (users:read)
- `POST /` — invite user (users:write)
- `PUT /{id}` — update user (users:write)
- `DELETE /{id}` — deactivate user (users:write)

**Roles** (`/api/v1/roles`):
- `GET /` — list roles (roles:read)
- `GET /{id}` — show with permissions (roles:read)
- `POST /` — create role (roles:write)
- `PUT /{id}` — update role (roles:write)
- `DELETE /{id}` — delete role (roles:write)

**System** (`/api/v1/system`):
- `GET /health` — health check (system:read)

**Action Log** (`/api/v1/actions`):
- `GET /` — list with filtering/pagination (action-log:read)

**Webhooks** (`/api/v1/webhooks`):
- `GET /` — list webhooks (webhooks:manage)
- `POST /` — register webhook (webhooks:manage)
- `GET /{id}` — show webhook (webhooks:manage)
- `PUT /{id}` — update webhook (webhooks:manage)
- `DELETE /{id}` — delete webhook (webhooks:manage)
- `GET /{id}/logs` — delivery logs (webhooks:manage)

### Webhook Infrastructure

**Models:**
- `Webhook`: url, secret, events (JSONB), is_active, consecutive_failures, disabled_at
- `WebhookLog`: webhook_id, event, payload (JSONB), response_code, response_body, attempts, delivered_at, next_retry_at

**Delivery:** `DeliverWebhook` job on `webhooks` queue. HMAC-SHA256 signed (`X-Signals-Signature` header). Exponential backoff: 6 retries over 12 hours. Auto-disable after 3 consecutive days of failures.

**Initial events:** `user.created`, `user.updated`, `user.deactivated`, `settings.updated`, `role.created`, `role.updated`, `role.deleted`

### Documentation

- Scramble auto-docs at `/docs/api/` with enriched PHPDoc
- Admin nav: "API" as top-level group under admin settings
- User docs: API overview (auth, rate limiting, response format, filtering, pagination)
- Enhanced `config/scramble.php` with proper title, description, version

### New Files

**Config:**
- `config/sanctum.php`

**Controllers:**
- `app/Http/Controllers/Api/Controller.php` (base)
- `app/Http/Controllers/Api/V1/SettingsController.php`
- `app/Http/Controllers/Api/V1/UserController.php`
- `app/Http/Controllers/Api/V1/RoleController.php`
- `app/Http/Controllers/Api/V1/SystemController.php`
- `app/Http/Controllers/Api/V1/ActionLogController.php`
- `app/Http/Controllers/Api/V1/WebhookController.php`

**DTOs:**
- `app/Data/Api/UserData.php` (response)
- `app/Data/Api/CreateUserData.php` (input)
- `app/Data/Api/UpdateUserData.php` (input)
- `app/Data/Api/RoleData.php` (response)
- `app/Data/Api/CreateRoleData.php` (input)
- `app/Data/Api/UpdateRoleData.php` (input)
- `app/Data/Api/SettingsGroupData.php` (response)
- `app/Data/Api/UpdateSettingsData.php` (input)
- `app/Data/Api/WebhookData.php` (response)
- `app/Data/Api/CreateWebhookData.php` (input)
- `app/Data/Api/UpdateWebhookData.php` (input)
- `app/Data/Api/ActionLogData.php` (response)
- `app/Data/Api/SystemHealthData.php` (response)

**Services:**
- `app/Services/Api/RansackFilter.php`
- `app/Services/Api/WebhookService.php`

**Traits:**
- `app/Http/Traits/FiltersQueries.php`

**Models:**
- `app/Models/Webhook.php` + factory
- `app/Models/WebhookLog.php` + factory
- `app/Models/PersonalAccessToken.php` (Sanctum override if needed)

**Migrations:**
- `create_webhooks_table`
- `create_webhook_logs_table`
- `create_personal_access_tokens_table` (if not already from Sanctum)

**Jobs:**
- `app/Jobs/DeliverWebhook.php`

**Admin UI:**
- `resources/views/livewire/admin/settings/api.blade.php`

**Routes:**
- `routes/api.php`

**Settings:**
- `app/Settings/ApiSettings.php`

**Middleware:**
- `app/Http/Middleware/CheckApiAbility.php` (optional, may use Sanctum's built-in)

**Tests:**
- `tests/Feature/Api/AuthenticationTest.php`
- `tests/Feature/Api/RateLimitingTest.php`
- `tests/Feature/Api/SettingsApiTest.php`
- `tests/Feature/Api/UserApiTest.php`
- `tests/Feature/Api/RoleApiTest.php`
- `tests/Feature/Api/SystemApiTest.php`
- `tests/Feature/Api/ActionLogApiTest.php`
- `tests/Feature/Api/WebhookApiTest.php`
- `tests/Unit/Services/RansackFilterTest.php`
- `tests/Unit/Jobs/DeliverWebhookTest.php`

**Docs:**
- `docs/api-overview.md` (or Blade page at resources/views/docs/)

### Testing Strategy

- Every endpoint: happy path, auth failure (no token, wrong ability), validation errors, filtering, pagination
- RansackFilter: all 18 predicates with edge cases
- Webhook delivery: signing verification, retry logic, auto-disable
- Rate limiting: verify 429 responses
- Response shape: verify CRMS-compatible wrapping
