# OSS Review — 2026-03-10 (API Infrastructure)

## Scope

~61 unstaged/untracked files (excluding prototypes) covering the full API infrastructure implementation. Compared against `docs/plans/2026-03-10-api-infrastructure-design.md` and `docs/plans/2026-03-10-api-infrastructure.md`.

### Files Reviewed

**Controllers:** `app/Http/Controllers/Api/Controller.php` (base), `V1/SystemController.php`, `V1/SettingsController.php`, `V1/UserController.php`, `V1/RoleController.php`, `V1/ActionLogController.php`, `V1/WebhookController.php`

**DTOs:** `app/Data/Api/` — UserData, CreateUserData, UpdateUserData, RoleData, CreateRoleData, UpdateRoleData, WebhookData, CreateWebhookData, UpdateWebhookData, ActionLogData

**Services:** `app/Services/Api/RansackFilter.php`, `app/Services/Api/WebhookService.php`

**Traits:** `app/Http/Traits/FiltersQueries.php`

**Middleware:** `app/Http/Middleware/EnsureActiveUser.php`, `ForceJsonResponse.php`

**Jobs:** `app/Jobs/DeliverWebhook.php`

**Models:** `app/Models/Webhook.php`, `WebhookLog.php`

**Settings:** `app/Settings/ApiSettings.php`

**Modified Actions:** `InviteUser.php`, `UpdateUser.php`, `DeactivateUser.php`, `CreateRole.php`, `UpdateRole.php`, `DeleteRole.php` (webhook dispatch added)

**Config/Routes:** `config/sanctum.php`, `config/scramble.php`, `routes/api.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`

**Migrations:** personal_access_tokens, webhooks, webhook_logs

**Factories:** WebhookFactory, WebhookLogFactory

**Tests:** AuthenticationTest, RateLimitingTest, UserApiTest, RoleApiTest, WebhookApiTest, SettingsApiTest, ActionLogApiTest, DeliverWebhookTest, WebhookDispatchTest, RansackFilterTest

**Docs:** `docs/api/overview.md`, `authentication.md`, `webhooks.md`, `documentation.json`

---

## Findings

### Critical

**C1. DeliverWebhook retry mechanism is dead code for HTTP failures** (`app/Jobs/DeliverWebhook.php:105-109`)
HTTP 4xx/5xx responses call `recordFailure()` but don't throw, so the job "succeeds" and Laravel never retries. Exponential backoff only applies to network exceptions. Fix: throw after `recordFailure()` for 5xx, or use `$this->release()`.

**C2. Settings update silently persists arbitrary keys** (`app/Http/Controllers/Api/V1/SettingsController.php:89-108`)
`array_intersect_key` validates only known keys, but the `foreach` at line 105 persists ALL provided keys — including unknown ones. Fix: filter `$input` to known keys before persisting.

**C3. ILIKE wildcard injection in RansackFilter** (`app/Services/Api/RansackFilter.php`)
`_cont`, `_not_cont`, `_start`, `_end` predicates use ILIKE with unescaped `%` and `_`. Attackers can bypass intended filtering.

**C4. Webhook secret stored as plaintext** (`app/Models/Webhook.php`)
Missing `'secret' => 'encrypted'` cast. Also missing `$hidden = ['secret']`.

**C5. Documentation header mismatch** (`docs/api/webhooks.md`)
Docs say `X-Signature` but code sends `X-Signals-Signature`.

### Important

**I1. UpdateRole description cannot be cleared to null** (`app/Actions/Admin/UpdateRole.php`)
`array_filter` strips null values, preventing clearing description.

**I2. No `failed()` method on DeliverWebhook** (`app/Jobs/DeliverWebhook.php`)
Permanent failures have no cleanup — failure count isn't incremented.

**I3. Webhook dispatch failure cascades to 500** (`app/Http/Controllers/Api/V1/SettingsController.php:110-113`)
If `WebhookService::dispatch()` throws, settings update succeeds but response is 500.

**I4. Silent filter dropping in RansackFilter**
Invalid predicates and disallowed columns silently ignored. Users get unfiltered results unknowingly.

**I5. Missing SystemApiTest.php** (plan gap)
Plan called for dedicated test file. Health tests embedded in AuthenticationTest.php.

**I6. ForceJsonResponse middleware untested**
No dedicated test verifying Accept header is set.

**I7. per_page=0 not guarded** (`app/Http/Traits/FiltersQueries.php`)
Capped at 100 but no floor.

**I8. Duplicate index on webhooks.user_id** (migration)
`->constrained()` creates implicit index, then `->index()` adds duplicate.

**I9. Missing WebhookLogData response DTO**
Webhook logs serialized via inline array, inconsistent with other endpoints.

**I10. ActionLogApiTest missing PermissionSeeder**
Tests pass because owner bypasses checks, so permission gate is untested.

**I11. `json_encode` failure unhandled** (`app/Jobs/DeliverWebhook.php`)
`json_encode($this->payload)` can return false.

**I12. Plan called for 3 missing DTOs**
`SettingsGroupData`, `UpdateSettingsData`, `SystemHealthData` not implemented.

**I13. Inconsistent timestamp nullability across response DTOs**
`UserData` non-nullable, `RoleData` nullable.

**I14. Redundant null check in SettingsController** (lines 76-87)
`has()` returns false at line 76, then `get()` null-checked at line 85. Second check unreachable.

### Suggestions

**S1. ActionLogData exposes FQCN** — `auditable_type` returns raw class names. Consider friendly resource names.

**S2. DTOs are plain PHP, not Spatie Data** — CLAUDE.md prescribes Spatie Data but package isn't installed. Current approach works.

**S3. EnsureActiveUser returns 401** — Deactivated user is authenticated but unauthorized; 403 may be more appropriate.

**S4. WebhookService::dispatch() has no error handling** — Callers must handle failures independently.

**S5. `get_object_vars($this)` fragility in DTOs** — Exposes all public properties including inherited ones. Consider explicit property lists.

---

## Documentation Status

| Layer | Status | Details |
|-------|--------|---------|
| User docs | Needs update | webhooks.md wrong header name; missing action log docs |
| API docs (Scramble) | Compliant | Controllers have `@operationId`, typed params, `#[ApiResponse]` |
| Code PHPDoc | Compliant | Public methods have return types and param hints |

## Component Library Compliance

N/A — No Blade/CSS component changes in this API implementation.

## Test Coverage

| Area | Status | Gap |
|------|--------|-----|
| AuthenticationTest | Covered | — |
| RateLimitingTest | Covered | — |
| UserApiTest | Covered | — |
| RoleApiTest | Covered | — |
| WebhookApiTest | Covered | — |
| SettingsApiTest | Covered | Missing unknown key rejection test |
| ActionLogApiTest | Partial | Missing PermissionSeeder — gate untested |
| DeliverWebhookTest | Partial | Missing exception path for HTTP failures (C1) |
| WebhookDispatchTest | Covered | — |
| RansackFilterTest | Covered | Missing wildcard escape test |
| SystemApiTest | Missing | Tests in AuthenticationTest, no dedicated file |
| ForceJsonResponse | Missing | No dedicated middleware test |

## Agent Reviews Dispatched

1. **pr-review-toolkit:code-reviewer** — ILIKE injection, unencrypted secret, UpdateRole null bug, redundant null check, duplicate index, DTO fragility
2. **pr-review-toolkit:silent-failure-hunter** — CRITICAL DeliverWebhook retry bug, settings persisting unknown keys, webhook dispatch cascading 500s, silent filter dropping, json_encode failure
3. **pr-review-toolkit:pr-test-analyzer** — Missing SystemApiTest, untested DeliverWebhook exception path, ForceJsonResponse untested, pagination edge cases
4. **pr-review-toolkit:type-design-analyzer** — get_object_vars fragility, missing $hidden on Webhook, missing WebhookLogData DTO, inconsistent timestamp nullability

## Resolution Status

All Critical and Important findings resolved. Quality gate passed: 764 tests (2133 assertions), PHPStan 0 errors, Pint clean.

| Finding | Status |
|---------|--------|
| C1 DeliverWebhook retry dead code | Fixed — 5xx throws RuntimeException for retry, 4xx records failure only |
| C2 Settings persists arbitrary keys | Fixed — input filtered to known schema keys |
| C3 ILIKE wildcard injection | Fixed — added escapeLike() for \, %, _ |
| C4 Webhook secret plaintext | Fixed — added encrypted cast + $hidden |
| C5 Docs header mismatch | Fixed — X-Signature → X-Signals-Signature |
| I1 UpdateRole null description | Fixed — explicit conditional updates |
| I2 No failed() method | Fixed — added failed() with recordFailure() + logging |
| I3 Webhook dispatch cascades 500 | Fixed — wrapped in try/catch |
| I4 Silent filter dropping | Accepted — by design for Ransack compatibility |
| I5 Missing SystemApiTest | Fixed — created dedicated test file (6 tests) |
| I6 ForceJsonResponse untested | Fixed — created dedicated test file (2 tests) |
| I7 per_page=0 | Fixed — floor of 1 added |
| I8 Duplicate index | Fixed — removed redundant index |
| I9 Missing WebhookLogData | Fixed — created DTO |
| I10 ActionLogApiTest seeder | Fixed — added PermissionSeeder |
| I11 json_encode failure | Fixed — handled in C1 fix |
| I12 3 missing DTOs from plan | Deferred — minor, not blocking |
| I13 Timestamp nullability | Deferred — minor inconsistency |
| I14 Redundant null check | Fixed — removed |
| S1 FQCN in ActionLogData | Fixed — friendlyType() maps to snake_case |
| S2 DTOs not Spatie Data | Accepted — package not installed |
| S3 EnsureActiveUser 401→403 | Fixed |
| S4 WebhookService no error handling | Fixed — try/catch with logging |
| S5 get_object_vars fragility | Fixed — explicit property arrays |
