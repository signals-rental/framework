# API Infrastructure Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build complete API infrastructure (auth, query engine, rate limiting, webhooks) with concrete endpoints for settings, users, roles, system health, and action logs.

**Architecture:** Sanctum bearer token auth with `resource:action` abilities. Trait-based API controller with CRMS-compatible response wrapping. Standalone RansackFilter service for `q[field_predicate]=value` query syntax. Webhook delivery via queued jobs with HMAC-SHA256 signing and exponential backoff.

**Tech Stack:** Laravel 12, Sanctum 4, Spatie Laravel Data, Spatie Permission, Scramble (OpenAPI docs), Pest 4

---

## Task 1: Sanctum Configuration & User Model

**Files:**
- Modify: `config/auth.php` (add `api` guard)
- Modify: `app/Models/User.php` (add `HasApiTokens` trait)
- Create: `database/migrations/xxxx_create_personal_access_tokens_table.php`
- Test: `tests/Feature/Api/AuthenticationTest.php`

**Step 1: Check if personal_access_tokens migration already exists**

Run: `php artisan migrate:status 2>&1 | grep personal_access`

If it already exists, skip the migration creation step. Sanctum 4 may have published this already.

Also run: `php artisan migrate:status 2>&1 | head -30` to see what migrations exist.

**Step 2: Create the personal_access_tokens migration (if needed)**

Run: `php artisan vendor:publish --tag=sanctum-migrations --no-interaction`

If that doesn't work, create the migration manually:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
```

**Step 3: Run the migration**

Run: `php artisan migrate --no-interaction`

**Step 4: Add HasApiTokens to User model**

In `app/Models/User.php`, add the `HasApiTokens` trait from Sanctum:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;
```

**Step 5: Add API guard to config/auth.php**

In `config/auth.php`, add the `sanctum` guard after the `web` guard at line 42:

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],
```

Note: Sanctum does not need a custom guard — it uses the `sanctum` driver automatically via its middleware. The `auth:sanctum` middleware handles API auth. No changes to `config/auth.php` guards are needed.

**Step 6: Write the failing auth tests**

Create `tests/Feature/Api/AuthenticationTest.php`:

```php
<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects unauthenticated api requests', function () {
    $this->getJson('/api/v1/system/health')
        ->assertUnauthorized();
});

it('accepts authenticated api requests with valid token', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user, ['system:read']);

    $this->getJson('/api/v1/system/health')
        ->assertOk();
});

it('rejects requests with insufficient token abilities', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user, ['users:read']);

    $this->getJson('/api/v1/system/health')
        ->assertForbidden();
});

it('rejects requests from deactivated users', function () {
    $user = User::factory()->admin()->deactivated()->create();
    Sanctum::actingAs($user, ['system:read']);

    $this->getJson('/api/v1/system/health')
        ->assertUnauthorized();
});
```

**Step 7: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Api/AuthenticationTest.php --compact`
Expected: FAIL (no routes exist yet — 404s)

**Step 8: Commit**

```
feat: configure Sanctum auth and add HasApiTokens to User model
```

---

## Task 2: API Routes & Base Controller

**Files:**
- Create: `routes/api.php`
- Modify: `bootstrap/app.php` (register API routes)
- Create: `app/Http/Controllers/Api/Controller.php`
- Create: `app/Http/Middleware/EnsureActiveUser.php`

**Step 1: Create the API routes file**

Create `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\V1\ActionLogController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/ and require Sanctum authentication
| unless explicitly excluded. Each endpoint checks token abilities via
| the CheckAbilities middleware.
|
*/

Route::prefix('v1')->middleware(['auth:sanctum', 'signals.active-user'])->group(function () {
    // Settings
    Route::get('settings', [SettingsController::class, 'index']);
    Route::get('settings/{group}', [SettingsController::class, 'show']);
    Route::put('settings/{group}', [SettingsController::class, 'update']);

    // Users
    Route::apiResource('users', UserController::class);

    // Roles
    Route::apiResource('roles', RoleController::class);

    // System
    Route::get('system/health', [SystemController::class, 'health']);

    // Action Log
    Route::get('actions', [ActionLogController::class, 'index']);

    // Webhooks
    Route::apiResource('webhooks', WebhookController::class);
    Route::get('webhooks/{webhook}/logs', [WebhookController::class, 'logs']);
});
```

**Step 2: Register API routes in bootstrap/app.php**

Modify `bootstrap/app.php` to add the `api` route file. Change the `withRouting` call:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
```

Note: Laravel automatically prefixes API routes with `/api` when using the `api:` key.

**Step 3: Register active-user middleware alias**

In `bootstrap/app.php`, add the `signals.active-user` alias to the middleware aliases:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'admin' => \App\Http\Middleware\EnsureAdmin::class,
        'module' => \App\Http\Middleware\EnsureModuleEnabled::class,
        'signals.setup-required' => \App\Http\Middleware\EnsureSetupRequired::class,
        'signals.setup-complete' => \App\Http\Middleware\EnsureSetupComplete::class,
        '2fa' => \App\Http\Middleware\EnsureTwoFactorAuthenticated::class,
        'signals.active-user' => \App\Http\Middleware\EnsureActiveUser::class,
    ]);
})
```

**Step 4: Create EnsureActiveUser middleware**

Create `app/Http/Middleware/EnsureActiveUser.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    /**
     * Ensure the authenticated user account is active.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive()) {
            return response()->json([
                'message' => 'Your account has been deactivated.',
            ], 401);
        }

        return $next($request);
    }
}
```

**Step 5: Create the API base controller**

Create `app/Http/Controllers/Api/Controller.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class Controller extends BaseController
{
    /**
     * Wrap a single resource in a keyed JSON response.
     *
     * @param  array<string, mixed>|object  $data
     */
    protected function respondWith(mixed $data, string $key, int $status = 200): JsonResponse
    {
        return response()->json([$key => $data], $status);
    }

    /**
     * Wrap a paginated collection in a keyed JSON response with meta.
     *
     * @param  array<int, mixed>  $items
     */
    protected function respondWithCollection(array $items, string $key, LengthAwarePaginator $paginator, int $status = 200): JsonResponse
    {
        return response()->json([
            $key => $items,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ], $status);
    }

    /**
     * Return a standard error response.
     *
     * @param  array<string, list<string>>|null  $errors
     */
    protected function respondWithError(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $response = ['message' => $message];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Return an accepted response for async operations.
     */
    protected function respondAccepted(string $jobId): JsonResponse
    {
        return response()->json([
            'job_id' => $jobId,
            'status' => 'accepted',
        ], 202);
    }
}
```

**Step 6: Create a minimal SystemController so auth tests pass**

Create `app/Http/Controllers/Api/V1/SystemController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SystemController extends Controller
{
    /**
     * System health check.
     *
     * Returns basic system status information.
     *
     * @operationId getSystemHealth
     */
    public function health(): JsonResponse
    {
        Gate::authorize('system.read');

        return $this->respondWith([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ], 'health');
    }
}
```

**Step 7: Register the system.read permission**

Check `database/seeders/PermissionSeeder.php` and add `system.read` to the permissions list if not already present. Also add all API-related permissions:
- `settings.read`, `settings.manage`
- `users.view`, `users.invite`, `users.edit`
- `roles.manage`
- `system.read`
- `action-log.view`
- `webhooks.manage`

**Step 8: Run auth tests**

Run: `php artisan test tests/Feature/Api/AuthenticationTest.php --compact`
Expected: Tests should pass now that routes, middleware, and controller exist.

The `system.read` ability check needs a middleware or gate approach. The plan uses `Gate::authorize()` inside controllers. For token ability checking, we need to verify the token has the right ability. In the SystemController, we used `Gate::authorize('system.read')` — the owner bypasses this. For non-owner users, the permission `system.read` must be assigned to their role. Token abilities are checked separately.

We need to add token ability checking. In each controller method, check both:
1. `Gate::authorize('resource.action')` — user has the permission
2. Token has the `resource:action` ability — use `$request->user()->tokenCan('system:read')`

Create a helper method in the base API controller:

```php
/**
 * Authorize both the user's permission and token ability.
 *
 * @throws \Illuminate\Auth\Access\AuthorizationException
 */
protected function authorizeApi(string $permission, string $ability): void
{
    Gate::authorize($permission);

    $user = request()->user();
    if ($user->currentAccessToken() && ! $user->tokenCan($ability)) {
        abort(403, 'Token does not have the required ability.');
    }
}
```

Update SystemController to use `$this->authorizeApi('system.read', 'system:read')`.

**Step 9: Run tests again**

Run: `php artisan test tests/Feature/Api/AuthenticationTest.php --compact`
Expected: PASS

**Step 10: Commit**

```
feat: add API routes, base controller, and active-user middleware
```

---

## Task 3: Rate Limiting

**Files:**
- Modify: `bootstrap/app.php` (configure rate limiters)
- Create: `app/Settings/ApiSettings.php`
- Modify: `app/Providers/AppServiceProvider.php` (register ApiSettings)
- Test: `tests/Feature/Api/RateLimitingTest.php`

**Step 1: Create ApiSettings definition**

Create `app/Settings/ApiSettings.php`:

```php
<?php

namespace App\Settings;

class ApiSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'api';
    }

    public function defaults(): array
    {
        return [
            'rate_limit' => 60,
            'rate_limit_unauthenticated' => 20,
            'token_expiration_days' => 0,
        ];
    }

    public function rules(): array
    {
        return [
            'rate_limit' => ['required', 'integer', 'min:1', 'max:10000'],
            'rate_limit_unauthenticated' => ['required', 'integer', 'min:1', 'max:10000'],
            'token_expiration_days' => ['required', 'integer', 'min:0', 'max:365'],
        ];
    }

    public function types(): array
    {
        return [
            'rate_limit' => 'integer',
            'rate_limit_unauthenticated' => 'integer',
            'token_expiration_days' => 'integer',
        ];
    }
}
```

**Step 2: Register ApiSettings in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, add to the `boot()` method after the existing settings registrations. Find where `SettingsRegistry` is used and register `ApiSettings`:

Check if settings are registered in `AppServiceProvider` or elsewhere. If the registry is populated via a seeder or provider, add the `ApiSettings` registration alongside existing ones.

Look for where `SettingsRegistry` gets its definitions registered — likely in a service provider `boot()` or via a seeder. Register `ApiSettings` the same way.

**Step 3: Configure rate limiters in AppServiceProvider boot**

Add rate limiter configuration to `app/Providers/AppServiceProvider.php` in the `boot()` method:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

// In boot():
RateLimiter::for('api', function ($request) {
    $limit = (int) settings('api.rate_limit', 60);

    return $request->user()
        ? Limit::perMinute($limit)->by($request->user()->id)
        : Limit::perMinute((int) settings('api.rate_limit_unauthenticated', 20))->by($request->ip());
});
```

**Step 4: Apply throttle middleware to API routes**

Modify `routes/api.php` to add `throttle:api`:

```php
Route::prefix('v1')->middleware(['throttle:api', 'auth:sanctum', 'signals.active-user'])->group(function () {
```

**Step 5: Write rate limiting tests**

Create `tests/Feature/Api/RateLimitingTest.php`:

```php
<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns rate limit headers on api responses', function () {
    $user = User::factory()->owner()->create();
    Sanctum::actingAs($user, ['system:read']);

    $response = $this->getJson('/api/v1/system/health');

    $response->assertOk();
    $response->assertHeader('X-RateLimit-Limit');
    $response->assertHeader('X-RateLimit-Remaining');
});

it('rejects requests exceeding rate limit', function () {
    settings()->set('api.rate_limit', 2, 'integer');

    $user = User::factory()->owner()->create();
    Sanctum::actingAs($user, ['system:read']);

    $this->getJson('/api/v1/system/health')->assertOk();
    $this->getJson('/api/v1/system/health')->assertOk();
    $this->getJson('/api/v1/system/health')->assertStatus(429);
});
```

**Step 6: Run tests**

Run: `php artisan test tests/Feature/Api/RateLimitingTest.php --compact`
Expected: PASS

**Step 7: Commit**

```
feat: add API rate limiting with configurable limits via settings
```

---

## Task 4: RansackFilter Service

**Files:**
- Create: `app/Services/Api/RansackFilter.php`
- Test: `tests/Unit/Services/RansackFilterTest.php`

**Step 1: Write comprehensive RansackFilter tests**

Create `tests/Unit/Services/RansackFilterTest.php`:

```php
<?php

use App\Services\Api\RansackFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

beforeEach(function () {
    $this->filter = new RansackFilter;
});

it('applies eq predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['name_eq' => 'Ben'], ['name']);

    expect($result->toRawSql())->toContain('"name" = ');
});

it('applies not_eq predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['name_not_eq' => 'Ben'], ['name']);

    expect($result->toRawSql())->toContain('"name" != ');
});

it('applies cont predicate as ILIKE', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['name_cont' => 'ben'], ['name']);

    expect($result->toRawSql())->toContain('ilike');
});

it('applies not_cont predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['name_not_cont' => 'ben'], ['name']);

    expect($result->toRawSql())->toContain('not ilike');
});

it('applies start predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['name_start' => 'B'], ['name']);

    expect($result->toRawSql())->toContain('ilike');
});

it('applies end predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['name_end' => 'en'], ['name']);

    expect($result->toRawSql())->toContain('ilike');
});

it('applies lt predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['id_lt' => 10], ['id']);

    expect($result->toRawSql())->toContain('"id" <');
});

it('applies lteq predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['id_lteq' => 10], ['id']);

    expect($result->toRawSql())->toContain('"id" <=');
});

it('applies gt predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['id_gt' => 10], ['id']);

    expect($result->toRawSql())->toContain('"id" >');
});

it('applies gteq predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['id_gteq' => 10], ['id']);

    expect($result->toRawSql())->toContain('"id" >=');
});

it('applies null predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['deactivated_at_null' => '1'], ['deactivated_at']);

    expect($result->toRawSql())->toContain('is null');
});

it('applies not_null predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['deactivated_at_not_null' => '1'], ['deactivated_at']);

    expect($result->toRawSql())->toContain('is not null');
});

it('applies present predicate (not null and not empty)', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['name_present' => '1'], ['name']);

    $sql = $result->toRawSql();
    expect($sql)->toContain('is not null');
    expect($sql)->toContain("!= ''");
});

it('applies blank predicate (null or empty)', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['name_blank' => '1'], ['name']);

    $sql = $result->toRawSql();
    expect($sql)->toContain('is null');
    expect($sql)->toContain("= ''");
});

it('applies in predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['id_in' => '1,2,3'], ['id']);

    expect($result->toRawSql())->toContain('"id" in');
});

it('applies not_in predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['id_not_in' => '1,2,3'], ['id']);

    expect($result->toRawSql())->toContain('"id" not in');
});

it('applies true predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['is_active_true' => '1'], ['is_active']);

    expect($result->toRawSql())->toContain('"is_active" = true');
});

it('applies false predicate', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['is_active_false' => '1'], ['is_active']);

    expect($result->toRawSql())->toContain('"is_active" = false');
});

it('ignores filters on disallowed fields', function () {
    $query = User::query();
    $result = $this->filter->apply($query, ['password_eq' => 'secret'], ['name', 'email']);

    // Should not have a where clause for password
    expect($result->toRawSql())->not->toContain('password');
});

it('handles empty filter array', function () {
    $query = User::query();
    $result = $this->filter->apply($query, [], ['name']);

    expect($result->toRawSql())->not->toContain('where');
});

it('applies sort ascending', function () {
    $query = User::query();
    $result = $this->filter->applySort($query, 'name', ['name', 'email']);

    expect($result->toRawSql())->toContain('order by "name" asc');
});

it('applies sort descending with minus prefix', function () {
    $query = User::query();
    $result = $this->filter->applySort($query, '-created_at', ['created_at', 'name']);

    expect($result->toRawSql())->toContain('order by "created_at" desc');
});

it('ignores sort on disallowed fields', function () {
    $query = User::query();
    $result = $this->filter->applySort($query, 'password', ['name', 'email']);

    expect($result->toRawSql())->not->toContain('order by');
});
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/RansackFilterTest.php --compact`
Expected: FAIL (class does not exist)

**Step 3: Implement RansackFilter**

Create `app/Services/Api/RansackFilter.php`:

```php
<?php

namespace App\Services\Api;

use Illuminate\Database\Eloquent\Builder;

class RansackFilter
{
    /**
     * Known predicate suffixes ordered longest-first for greedy matching.
     *
     * @var list<string>
     */
    private const PREDICATES = [
        'not_cont',
        'not_null',
        'not_in',
        'not_eq',
        'present',
        'blank',
        'start',
        'false',
        'gteq',
        'lteq',
        'cont',
        'null',
        'true',
        'end',
        'in',
        'eq',
        'gt',
        'lt',
    ];

    /**
     * Apply Ransack-compatible filters to a query builder.
     *
     * @param  array<string, string>  $filters  Keys are "field_predicate", values are the filter value.
     * @param  list<string>  $allowedFields  Whitelist of filterable column names.
     */
    public function apply(Builder $query, array $filters, array $allowedFields): Builder
    {
        foreach ($filters as $key => $value) {
            $parsed = $this->parseKey($key);

            if ($parsed === null) {
                continue;
            }

            [$field, $predicate] = $parsed;

            if (! in_array($field, $allowedFields, true)) {
                continue;
            }

            $this->applyPredicate($query, $field, $predicate, $value);
        }

        return $query;
    }

    /**
     * Apply sorting to a query builder.
     *
     * @param  list<string>  $allowedFields
     */
    public function applySort(Builder $query, string $sort, array $allowedFields): Builder
    {
        $direction = 'asc';
        $field = $sort;

        if (str_starts_with($sort, '-')) {
            $direction = 'desc';
            $field = substr($sort, 1);
        }

        if (! in_array($field, $allowedFields, true)) {
            return $query;
        }

        return $query->orderBy($field, $direction);
    }

    /**
     * Parse a filter key into [field, predicate].
     *
     * @return array{0: string, 1: string}|null
     */
    private function parseKey(string $key): ?array
    {
        foreach (self::PREDICATES as $predicate) {
            $suffix = '_'.$predicate;
            if (str_ends_with($key, $suffix)) {
                $field = substr($key, 0, -strlen($suffix));
                if ($field !== '') {
                    return [$field, $predicate];
                }
            }
        }

        return null;
    }

    /**
     * Apply a single predicate to the query builder.
     */
    private function applyPredicate(Builder $query, string $field, string $predicate, mixed $value): void
    {
        match ($predicate) {
            'eq' => $query->where($field, '=', $value),
            'not_eq' => $query->where($field, '!=', $value),
            'lt' => $query->where($field, '<', $value),
            'lteq' => $query->where($field, '<=', $value),
            'gt' => $query->where($field, '>', $value),
            'gteq' => $query->where($field, '>=', $value),
            'cont' => $query->where($field, 'ilike', '%'.$value.'%'),
            'not_cont' => $query->where($field, 'not ilike', '%'.$value.'%'),
            'start' => $query->where($field, 'ilike', $value.'%'),
            'end' => $query->where($field, 'ilike', '%'.$value),
            'null' => $query->whereNull($field),
            'not_null' => $query->whereNotNull($field),
            'present' => $query->whereNotNull($field)->where($field, '!=', ''),
            'blank' => $query->where(fn (Builder $q) => $q->whereNull($field)->orWhere($field, '=', '')),
            'in' => $query->whereIn($field, explode(',', (string) $value)),
            'not_in' => $query->whereNotIn($field, explode(',', (string) $value)),
            'true' => $query->where($field, '=', true),
            'false' => $query->where($field, '=', false),
        };
    }
}
```

**Step 4: Run tests**

Run: `php artisan test tests/Unit/Services/RansackFilterTest.php --compact`
Expected: PASS (all 22 tests)

**Step 5: Commit**

```
feat: implement RansackFilter service with all 18 predicates
```

---

## Task 5: FiltersQueries Trait

**Files:**
- Create: `app/Http/Traits/FiltersQueries.php`

**Step 1: Create the trait**

Create `app/Http/Traits/FiltersQueries.php`:

```php
<?php

namespace App\Http\Traits;

use App\Services\Api\RansackFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait FiltersQueries
{
    /**
     * Apply Ransack-compatible filters from the request's `q` parameter.
     *
     * Controllers using this trait must define:
     * - `protected array $allowedFilters = ['name', 'email', ...]`
     *
     * @param  list<string>|null  $allowedFields  Override the default allowed filters.
     */
    protected function applyFilters(Builder $query, Request $request, ?array $allowedFields = null): Builder
    {
        $filters = $request->input('q', []);

        if (! is_array($filters) || empty($filters)) {
            return $query;
        }

        $allowed = $allowedFields ?? $this->allowedFilters ?? [];

        return app(RansackFilter::class)->apply($query, $filters, $allowed);
    }

    /**
     * Apply sorting from the request's `sort` parameter.
     *
     * Controllers using this trait must define:
     * - `protected array $allowedSorts = ['name', 'created_at', ...]`
     *
     * @param  list<string>|null  $allowedFields  Override the default allowed sorts.
     */
    protected function applySort(Builder $query, Request $request, ?array $allowedFields = null): Builder
    {
        $sort = $request->input('sort');

        if (! $sort) {
            return $query;
        }

        $allowed = $allowedFields ?? $this->allowedSorts ?? [];

        return app(RansackFilter::class)->applySort($query, $sort, $allowed);
    }

    /**
     * Paginate a query using the request's `page` and `per_page` parameters.
     */
    protected function paginateQuery(Builder $query, Request $request): LengthAwarePaginator
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        $page = max((int) $request->input('page', 1), 1);

        return $query->paginate(perPage: $perPage, page: $page);
    }
}
```

**Step 2: Commit**

```
feat: add FiltersQueries trait for API controllers
```

---

## Task 6: Settings API Controller

**Files:**
- Create: `app/Http/Controllers/Api/V1/SettingsController.php`
- Test: `tests/Feature/Api/SettingsApiTest.php`

**Step 1: Write the failing tests**

Create `tests/Feature/Api/SettingsApiTest.php`:

```php
<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
});

describe('GET /api/v1/settings', function () {
    it('lists all settings groups', function () {
        Sanctum::actingAs($this->user, ['settings:read']);

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonStructure([
                'settings' => [
                    '*' => ['group', 'settings'],
                ],
            ]);
    });

    it('requires settings:read ability', function () {
        Sanctum::actingAs($this->user, ['users:read']);

        $this->getJson('/api/v1/settings')
            ->assertForbidden();
    });

    it('rejects unauthenticated requests', function () {
        $this->getJson('/api/v1/settings')
            ->assertUnauthorized();
    });
});

describe('GET /api/v1/settings/{group}', function () {
    it('returns settings for a specific group', function () {
        Sanctum::actingAs($this->user, ['settings:read']);

        settings()->set('company.name', 'Test Corp', 'string');

        $this->getJson('/api/v1/settings/company')
            ->assertOk()
            ->assertJsonPath('setting.group', 'company')
            ->assertJsonPath('setting.settings.name', 'Test Corp');
    });

    it('returns 404 for unknown group', function () {
        Sanctum::actingAs($this->user, ['settings:read']);

        $this->getJson('/api/v1/settings/nonexistent')
            ->assertNotFound();
    });
});

describe('PUT /api/v1/settings/{group}', function () {
    it('updates settings for a group', function () {
        Sanctum::actingAs($this->user, ['settings:write']);

        $this->putJson('/api/v1/settings/security', [
            'settings' => [
                'password_min_length' => 12,
                'password_require_uppercase' => true,
            ],
        ])->assertOk();

        expect(settings('security.password_min_length'))->toBe(12);
        expect(settings('security.password_require_uppercase'))->toBeTrue();
    });

    it('requires settings:write ability', function () {
        Sanctum::actingAs($this->user, ['settings:read']);

        $this->putJson('/api/v1/settings/security', [
            'settings' => ['password_min_length' => 12],
        ])->assertForbidden();
    });

    it('validates settings against definition rules', function () {
        Sanctum::actingAs($this->user, ['settings:write']);

        $this->putJson('/api/v1/settings/security', [
            'settings' => ['password_min_length' => 2],
        ])->assertUnprocessable();
    });
});
```

**Step 2: Implement SettingsController**

Create `app/Http/Controllers/Api/V1/SettingsController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Services\SettingsRegistry;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * List all settings groups with their current values.
     *
     * @operationId listSettings
     */
    public function index(SettingsService $settings, SettingsRegistry $registry): JsonResponse
    {
        $this->authorizeApi('settings.read', 'settings:read');

        $groups = [];
        foreach ($registry->all() as $definition) {
            $group = $definition->group();
            $groups[] = [
                'group' => $group,
                'settings' => $settings->group($group),
            ];
        }

        return response()->json(['settings' => $groups]);
    }

    /**
     * Get settings for a specific group.
     *
     * @operationId getSettingsGroup
     */
    public function show(string $group, SettingsService $settings, SettingsRegistry $registry): JsonResponse
    {
        $this->authorizeApi('settings.read', 'settings:read');

        if (! $registry->has($group)) {
            return $this->respondWithError('Settings group not found.', 404);
        }

        return $this->respondWith([
            'group' => $group,
            'settings' => $settings->group($group),
        ], 'setting');
    }

    /**
     * Update settings for a specific group.
     *
     * @operationId updateSettingsGroup
     */
    public function update(string $group, Request $request, SettingsService $settings, SettingsRegistry $registry): JsonResponse
    {
        $this->authorizeApi('settings.manage', 'settings:write');

        if (! $registry->has($group)) {
            return $this->respondWithError('Settings group not found.', 404);
        }

        $definition = $registry->get($group);
        $input = $request->input('settings', []);

        // Validate only the keys that were provided
        $rules = $definition->rules();
        $applicableRules = array_intersect_key($rules, $input);

        if (! empty($applicableRules)) {
            $validator = Validator::make($input, $applicableRules);

            if ($validator->fails()) {
                return $this->respondWithError(
                    'Validation failed.',
                    422,
                    $validator->errors()->toArray(),
                );
            }
        }

        $types = $definition->types();
        $settingsToSave = [];
        foreach ($input as $key => $value) {
            $type = $types[$key] ?? 'string';
            $settingsToSave["{$group}.{$key}"] = ['value' => $value, 'type' => $type];
        }

        foreach ($settingsToSave as $fullKey => $data) {
            $settings->set($fullKey, $data['value'], $data['type']);
        }

        return $this->respondWith([
            'group' => $group,
            'settings' => $settings->group($group),
        ], 'setting');
    }
}
```

**Step 3: Run tests**

Run: `php artisan test tests/Feature/Api/SettingsApiTest.php --compact`
Expected: PASS

**Step 4: Commit**

```
feat: add settings API endpoints (list, show, update)
```

---

## Task 7: Users API Controller & DTOs

**Files:**
- Create: `app/Data/Api/UserData.php`
- Create: `app/Data/Api/CreateUserData.php`
- Create: `app/Data/Api/UpdateUserData.php`
- Create: `app/Http/Controllers/Api/V1/UserController.php`
- Test: `tests/Feature/Api/UserApiTest.php`

**Step 1: Create UserData response DTO**

Create `app/Data/Api/UserData.php`:

```php
<?php

namespace App\Data\Api;

use App\Models\User;

class UserData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly bool $is_admin,
        public readonly bool $is_owner,
        public readonly bool $is_active,
        public readonly ?string $email_verified_at,
        public readonly ?string $invited_at,
        public readonly ?string $invitation_accepted_at,
        public readonly ?string $last_login_at,
        public readonly ?string $deactivated_at,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
        /** @var list<string> */
        public readonly array $roles = [],
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            is_admin: $user->is_admin,
            is_owner: $user->is_owner,
            is_active: $user->is_active,
            email_verified_at: $user->email_verified_at?->toIso8601String(),
            invited_at: $user->invited_at?->toIso8601String(),
            invitation_accepted_at: $user->invitation_accepted_at?->toIso8601String(),
            last_login_at: $user->last_login_at?->toIso8601String(),
            deactivated_at: $user->deactivated_at?->toIso8601String(),
            created_at: $user->created_at?->toIso8601String(),
            updated_at: $user->updated_at?->toIso8601String(),
            roles: $user->getRoleNames()->toArray(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
```

**Step 2: Create CreateUserData input DTO**

Create `app/Data/Api/CreateUserData.php`:

```php
<?php

namespace App\Data\Api;

use App\Data\Admin\InviteUserData;

class CreateUserData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        /** @var list<string> */
        public readonly array $roles = [],
    ) {}

    /**
     * @param  array{name: string, email: string, roles?: list<string>}  $data
     */
    public static function from(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            roles: $data['roles'] ?? [],
        );
    }

    /**
     * Convert to InviteUserData for the shared action layer.
     */
    public function toInviteUserData(): InviteUserData
    {
        return new InviteUserData(
            name: $this->name,
            email: $this->email,
            roles: $this->roles,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
```

**Step 3: Create UpdateUserData input DTO**

Create `app/Data/Api/UpdateUserData.php`:

```php
<?php

namespace App\Data\Api;

class UpdateUserData
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        /** @var list<string>|null */
        public readonly ?array $roles = null,
    ) {}

    /**
     * @param  array{name?: string, email?: string, roles?: list<string>}  $data
     */
    public static function from(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            roles: $data['roles'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toActionData(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->roles,
        ], fn ($v) => $v !== null);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(int $userId): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$userId],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
```

**Step 4: Create UserController**

Create `app/Http/Controllers/Api/V1/UserController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Admin\DeactivateUser;
use App\Actions\Admin\InviteUser;
use App\Actions\Admin\UpdateUser;
use App\Data\Api\CreateUserData;
use App\Data\Api\UpdateUserData;
use App\Data\Api\UserData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name', 'email', 'is_admin', 'is_active', 'is_owner',
        'created_at', 'last_login_at', 'deactivated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name', 'email', 'created_at', 'last_login_at',
    ];

    /**
     * List all users with optional filtering and pagination.
     *
     * @operationId listUsers
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('users.view', 'users:read');

        $query = User::query()->with('roles');
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        $paginator = $this->paginateQuery($query, $request);

        $users = collect($paginator->items())
            ->map(fn (User $user) => UserData::fromModel($user)->toArray())
            ->all();

        return $this->respondWithCollection($users, 'users', $paginator);
    }

    /**
     * Show a single user.
     *
     * @operationId getUser
     */
    public function show(User $user): JsonResponse
    {
        $this->authorizeApi('users.view', 'users:read');

        $user->load('roles');

        return $this->respondWith(
            UserData::fromModel($user)->toArray(),
            'user',
        );
    }

    /**
     * Create (invite) a new user.
     *
     * @operationId createUser
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('users.invite', 'users:write');

        $validated = $request->validate(CreateUserData::rules());
        $dto = CreateUserData::from($validated);

        $user = (new InviteUser)($dto->toInviteUserData());
        $user->load('roles');

        return $this->respondWith(
            UserData::fromModel($user)->toArray(),
            'user',
            201,
        );
    }

    /**
     * Update an existing user.
     *
     * @operationId updateUser
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeApi('users.edit', 'users:write');

        $validated = $request->validate(UpdateUserData::rules($user->id));
        $dto = UpdateUserData::from($validated);

        $user = (new UpdateUser)($user, $dto->toActionData());
        $user->load('roles');

        return $this->respondWith(
            UserData::fromModel($user)->toArray(),
            'user',
        );
    }

    /**
     * Deactivate a user.
     *
     * @operationId deleteUser
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorizeApi('users.edit', 'users:write');

        (new DeactivateUser)($user);

        return response()->json(null, 204);
    }
}
```

**Step 5: Write tests**

Create `tests/Feature/Api/UserApiTest.php`:

```php
<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/users', function () {
    it('lists users with pagination', function () {
        User::factory()->count(3)->create();
        Sanctum::actingAs($this->owner, ['users:read']);

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonStructure([
                'users' => [['id', 'name', 'email', 'is_admin', 'is_active', 'roles']],
                'meta' => ['total', 'per_page', 'page'],
            ]);
    });

    it('filters users by name_cont', function () {
        User::factory()->create(['name' => 'Alice Smith']);
        User::factory()->create(['name' => 'Bob Jones']);
        Sanctum::actingAs($this->owner, ['users:read']);

        $this->getJson('/api/v1/users?q[name_cont]=alice')
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.name', 'Alice Smith');
    });

    it('filters users by is_active_true', function () {
        User::factory()->create(['is_active' => true, 'name' => 'Active User']);
        User::factory()->deactivated()->create(['name' => 'Deactivated User']);
        Sanctum::actingAs($this->owner, ['users:read']);

        $response = $this->getJson('/api/v1/users?q[is_active_true]=1')
            ->assertOk();

        $users = $response->json('users');
        expect(collect($users)->pluck('is_active'))->each->toBeTrue();
    });

    it('sorts users by name', function () {
        User::factory()->create(['name' => 'Charlie']);
        User::factory()->create(['name' => 'Alice']);
        Sanctum::actingAs($this->owner, ['users:read']);

        $response = $this->getJson('/api/v1/users?sort=name')
            ->assertOk();

        $names = collect($response->json('users'))->pluck('name')->values();
        expect($names->first())->toBe('Alice');
    });

    it('paginates results', function () {
        User::factory()->count(5)->create();
        Sanctum::actingAs($this->owner, ['users:read']);

        $this->getJson('/api/v1/users?per_page=2&page=1')
            ->assertOk()
            ->assertJsonCount(2, 'users')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.page', 1);
    });

    it('requires users:read ability', function () {
        Sanctum::actingAs($this->owner, ['settings:read']);

        $this->getJson('/api/v1/users')
            ->assertForbidden();
    });
});

describe('GET /api/v1/users/{id}', function () {
    it('shows a single user', function () {
        $user = User::factory()->create(['name' => 'Test User']);
        Sanctum::actingAs($this->owner, ['users:read']);

        $this->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('user.name', 'Test User')
            ->assertJsonPath('user.id', $user->id);
    });

    it('returns 404 for nonexistent user', function () {
        Sanctum::actingAs($this->owner, ['users:read']);

        $this->getJson('/api/v1/users/99999')
            ->assertNotFound();
    });
});

describe('POST /api/v1/users', function () {
    it('creates (invites) a new user', function () {
        Sanctum::actingAs($this->owner, ['users:write']);

        $this->postJson('/api/v1/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
        ])
            ->assertCreated()
            ->assertJsonPath('user.name', 'New User')
            ->assertJsonPath('user.email', 'new@example.com');

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    });

    it('validates required fields', function () {
        Sanctum::actingAs($this->owner, ['users:write']);

        $this->postJson('/api/v1/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    });

    it('validates unique email', function () {
        User::factory()->create(['email' => 'taken@example.com']);
        Sanctum::actingAs($this->owner, ['users:write']);

        $this->postJson('/api/v1/users', [
            'name' => 'Another',
            'email' => 'taken@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('requires users:write ability', function () {
        Sanctum::actingAs($this->owner, ['users:read']);

        $this->postJson('/api/v1/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
        ])->assertForbidden();
    });
});

describe('PUT /api/v1/users/{id}', function () {
    it('updates user details', function () {
        $user = User::factory()->create(['name' => 'Old Name']);
        Sanctum::actingAs($this->owner, ['users:write']);

        $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'New Name',
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'New Name');
    });
});

describe('DELETE /api/v1/users/{id}', function () {
    it('deactivates a user', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($this->owner, ['users:write']);

        $this->deleteJson("/api/v1/users/{$user->id}")
            ->assertNoContent();

        expect($user->fresh()->is_active)->toBeFalse();
    });
});
```

**Step 6: Run tests**

Run: `php artisan test tests/Feature/Api/UserApiTest.php --compact`
Expected: PASS

**Step 7: Commit**

```
feat: add users API with DTOs, filtering, pagination, and CRUD
```

---

## Task 8: Roles API Controller & DTOs

**Files:**
- Create: `app/Data/Api/RoleData.php`
- Create: `app/Data/Api/CreateRoleData.php`
- Create: `app/Data/Api/UpdateRoleData.php`
- Create: `app/Http/Controllers/Api/V1/RoleController.php`
- Test: `tests/Feature/Api/RoleApiTest.php`

**Step 1: Create RoleData response DTO**

Create `app/Data/Api/RoleData.php`:

```php
<?php

namespace App\Data\Api;

use Spatie\Permission\Models\Role;

class RoleData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $is_system,
        public readonly int $sort_order,
        /** @var list<string> */
        public readonly array $permissions = [],
        public readonly ?string $created_at = null,
        public readonly ?string $updated_at = null,
    ) {}

    public static function fromModel(Role $role): self
    {
        return new self(
            id: $role->id,
            name: $role->name,
            description: $role->description ?? null,
            is_system: (bool) ($role->is_system ?? false),
            sort_order: (int) ($role->sort_order ?? 0),
            permissions: $role->permissions->pluck('name')->toArray(),
            created_at: $role->created_at?->toIso8601String(),
            updated_at: $role->updated_at?->toIso8601String(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
```

**Step 2: Create input DTOs**

Create `app/Data/Api/CreateRoleData.php`:

```php
<?php

namespace App\Data\Api;

class CreateRoleData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        /** @var list<string> */
        public readonly array $permissions = [],
    ) {}

    /** @param array{name: string, description?: string, permissions?: list<string>} $data */
    public static function from(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            permissions: $data['permissions'] ?? [],
        );
    }

    /** @return array<string, mixed> */
    public function toActionData(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'permissions' => $this->permissions,
        ];
    }

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ];
    }
}
```

Create `app/Data/Api/UpdateRoleData.php`:

```php
<?php

namespace App\Data\Api;

class UpdateRoleData
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        /** @var list<string>|null */
        public readonly ?array $permissions = null,
    ) {}

    /** @param array{name?: string, description?: string, permissions?: list<string>} $data */
    public static function from(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            permissions: $data['permissions'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toActionData(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'permissions' => $this->permissions,
        ], fn ($v) => $v !== null);
    }

    /** @return array<string, list<string>> */
    public static function rules(int $roleId): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,'.$roleId],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ];
    }
}
```

**Step 3: Create RoleController**

Create `app/Http/Controllers/Api/V1/RoleController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Admin\CreateRole;
use App\Actions\Admin\DeleteRole;
use App\Actions\Admin\UpdateRole;
use App\Data\Api\CreateRoleData;
use App\Data\Api\RoleData;
use App\Data\Api\UpdateRoleData;
use App\Http\Controllers\Api\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * List all roles.
     *
     * @operationId listRoles
     */
    public function index(): JsonResponse
    {
        $this->authorizeApi('roles.manage', 'roles:read');

        $roles = Role::query()
            ->with('permissions')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Role $role) => RoleData::fromModel($role)->toArray())
            ->all();

        return response()->json(['roles' => $roles]);
    }

    /**
     * Show a single role with its permissions.
     *
     * @operationId getRole
     */
    public function show(Role $role): JsonResponse
    {
        $this->authorizeApi('roles.manage', 'roles:read');

        $role->load('permissions');

        return $this->respondWith(RoleData::fromModel($role)->toArray(), 'role');
    }

    /**
     * Create a new role.
     *
     * @operationId createRole
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('roles.manage', 'roles:write');

        $validated = $request->validate(CreateRoleData::rules());
        $dto = CreateRoleData::from($validated);

        $role = (new CreateRole)($dto->toActionData());
        $role->load('permissions');

        return $this->respondWith(RoleData::fromModel($role)->toArray(), 'role', 201);
    }

    /**
     * Update an existing role.
     *
     * @operationId updateRole
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorizeApi('roles.manage', 'roles:write');

        $validated = $request->validate(UpdateRoleData::rules($role->id));
        $dto = UpdateRoleData::from($validated);

        $role = (new UpdateRole)($role, $dto->toActionData());
        $role->load('permissions');

        return $this->respondWith(RoleData::fromModel($role)->toArray(), 'role');
    }

    /**
     * Delete a role.
     *
     * @operationId deleteRole
     */
    public function destroy(Role $role): JsonResponse
    {
        $this->authorizeApi('roles.manage', 'roles:write');

        (new DeleteRole)($role);

        return response()->json(null, 204);
    }
}
```

**Step 4: Write tests**

Create `tests/Feature/Api/RoleApiTest.php`:

```php
<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/roles', function () {
    it('lists all roles with permissions', function () {
        Role::create(['name' => 'Editor', 'guard_name' => 'web']);
        Sanctum::actingAs($this->owner, ['roles:read']);

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonStructure(['roles' => [['id', 'name', 'permissions']]]);
    });

    it('requires roles:read ability', function () {
        Sanctum::actingAs($this->owner, ['users:read']);

        $this->getJson('/api/v1/roles')
            ->assertForbidden();
    });
});

describe('POST /api/v1/roles', function () {
    it('creates a new role', function () {
        Sanctum::actingAs($this->owner, ['roles:write']);

        $this->postJson('/api/v1/roles', [
            'name' => 'Manager',
            'description' => 'A manager role',
            'permissions' => ['users.view', 'users.edit'],
        ])
            ->assertCreated()
            ->assertJsonPath('role.name', 'Manager');

        $this->assertDatabaseHas('roles', ['name' => 'Manager']);
    });

    it('validates unique role name', function () {
        Role::create(['name' => 'Taken', 'guard_name' => 'web']);
        Sanctum::actingAs($this->owner, ['roles:write']);

        $this->postJson('/api/v1/roles', ['name' => 'Taken'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
});

describe('DELETE /api/v1/roles/{id}', function () {
    it('deletes a non-system role', function () {
        $role = Role::create(['name' => 'Temp', 'guard_name' => 'web', 'is_system' => false]);
        Sanctum::actingAs($this->owner, ['roles:write']);

        $this->deleteJson("/api/v1/roles/{$role->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    });
});
```

**Step 5: Run tests**

Run: `php artisan test tests/Feature/Api/RoleApiTest.php --compact`
Expected: PASS

**Step 6: Commit**

```
feat: add roles API with DTOs and CRUD endpoints
```

---

## Task 9: Action Log API Controller

**Files:**
- Create: `app/Data/Api/ActionLogData.php`
- Create: `app/Http/Controllers/Api/V1/ActionLogController.php`
- Test: `tests/Feature/Api/ActionLogApiTest.php`

**Step 1: Create ActionLogData**

Create `app/Data/Api/ActionLogData.php`:

```php
<?php

namespace App\Data\Api;

use App\Models\ActionLog;

class ActionLogData
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $user_id,
        public readonly string $action,
        public readonly ?string $auditable_type,
        public readonly ?int $auditable_id,
        public readonly ?array $old_values,
        public readonly ?array $new_values,
        public readonly ?string $ip_address,
        public readonly ?string $created_at,
        public readonly ?string $user_name = null,
    ) {}

    public static function fromModel(ActionLog $log): self
    {
        return new self(
            id: $log->id,
            user_id: $log->user_id,
            action: $log->action,
            auditable_type: $log->auditable_type,
            auditable_id: $log->auditable_id,
            old_values: $log->old_values,
            new_values: $log->new_values,
            ip_address: $log->ip_address,
            created_at: $log->created_at?->toIso8601String(),
            user_name: $log->user?->name,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
```

**Step 2: Create ActionLogController**

Create `app/Http/Controllers/Api/V1/ActionLogController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\ActionLogData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\ActionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionLogController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'action', 'auditable_type', 'auditable_id', 'user_id', 'created_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'created_at', 'action',
    ];

    /**
     * List action log entries with optional filtering and pagination.
     *
     * @operationId listActions
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('action-log.view', 'action-log:read');

        $query = ActionLog::query()->with('user')->latest();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        $paginator = $this->paginateQuery($query, $request);

        $actions = collect($paginator->items())
            ->map(fn (ActionLog $log) => ActionLogData::fromModel($log)->toArray())
            ->all();

        return $this->respondWithCollection($actions, 'actions', $paginator);
    }
}
```

**Step 3: Write tests**

Create `tests/Feature/Api/ActionLogApiTest.php`:

```php
<?php

use App\Models\ActionLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/actions', function () {
    it('lists action log entries', function () {
        ActionLog::factory()->count(3)->create(['user_id' => $this->owner->id]);
        Sanctum::actingAs($this->owner, ['action-log:read']);

        $this->getJson('/api/v1/actions')
            ->assertOk()
            ->assertJsonStructure([
                'actions' => [['id', 'action', 'user_id', 'created_at']],
                'meta' => ['total', 'per_page', 'page'],
            ]);
    });

    it('filters by action type', function () {
        ActionLog::factory()->create(['action' => 'created', 'user_id' => $this->owner->id]);
        ActionLog::factory()->create(['action' => 'updated', 'user_id' => $this->owner->id]);
        Sanctum::actingAs($this->owner, ['action-log:read']);

        $this->getJson('/api/v1/actions?q[action_eq]=created')
            ->assertOk()
            ->assertJsonCount(1, 'actions');
    });

    it('requires action-log:read ability', function () {
        Sanctum::actingAs($this->owner, ['users:read']);

        $this->getJson('/api/v1/actions')
            ->assertForbidden();
    });
});
```

Note: You may need to create an ActionLog factory if it doesn't exist. Check `database/factories/ActionLogFactory.php`.

**Step 4: Run tests**

Run: `php artisan test tests/Feature/Api/ActionLogApiTest.php --compact`
Expected: PASS

**Step 5: Commit**

```
feat: add action log API endpoint with filtering and pagination
```

---

## Task 10: Webhook Models & Migrations

**Files:**
- Create: `app/Models/Webhook.php`
- Create: `app/Models/WebhookLog.php`
- Create: `database/migrations/xxxx_create_webhooks_table.php`
- Create: `database/migrations/xxxx_create_webhook_logs_table.php`
- Create: `database/factories/WebhookFactory.php`
- Create: `database/factories/WebhookLogFactory.php`

**Step 1: Create migrations**

Run: `php artisan make:migration create_webhooks_table --no-interaction`

Edit the migration:

```php
public function up(): void
{
    Schema::create('webhooks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('url', 2048);
        $table->string('secret', 64);
        $table->jsonb('events');
        $table->boolean('is_active')->default(true);
        $table->integer('consecutive_failures')->default(0);
        $table->timestamp('disabled_at')->nullable();
        $table->timestamps();

        $table->index('user_id');
        $table->index('is_active');
    });
}
```

Run: `php artisan make:migration create_webhook_logs_table --no-interaction`

Edit the migration:

```php
public function up(): void
{
    Schema::create('webhook_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
        $table->string('event');
        $table->jsonb('payload');
        $table->integer('response_code')->nullable();
        $table->text('response_body')->nullable();
        $table->integer('attempts')->default(0);
        $table->timestamp('delivered_at')->nullable();
        $table->timestamp('next_retry_at')->nullable();
        $table->timestamps();

        $table->index(['webhook_id', 'created_at']);
    });
}
```

**Step 2: Run migrations**

Run: `php artisan migrate --no-interaction`

**Step 3: Create Webhook model**

Create `app/Models/Webhook.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'url',
        'secret',
        'events',
        'is_active',
        'consecutive_failures',
        'disabled_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'consecutive_failures' => 'integer',
            'disabled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    /**
     * Check if this webhook is subscribed to a given event.
     */
    public function subscribedTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }
}
```

**Step 4: Create WebhookLog model**

Create `app/Models/WebhookLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'response_code',
        'response_body',
        'attempts',
        'delivered_at',
        'next_retry_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_code' => 'integer',
            'attempts' => 'integer',
            'delivered_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
```

**Step 5: Create factories**

Create `database/factories/WebhookFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Webhook> */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'url' => fake()->url(),
            'secret' => Str::random(32),
            'events' => ['user.created', 'user.updated'],
            'is_active' => true,
            'consecutive_failures' => 0,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'disabled_at' => now(),
        ]);
    }
}
```

Create `database/factories/WebhookLogFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebhookLog> */
class WebhookLogFactory extends Factory
{
    protected $model = WebhookLog::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'event' => 'user.created',
            'payload' => ['user' => ['id' => 1, 'name' => 'Test']],
            'response_code' => 200,
            'response_body' => 'OK',
            'attempts' => 1,
            'delivered_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'response_code' => 500,
            'response_body' => 'Internal Server Error',
            'delivered_at' => null,
        ]);
    }
}
```

**Step 6: Commit**

```
feat: add Webhook and WebhookLog models with migrations and factories
```

---

## Task 11: Webhook Delivery Job & Service

**Files:**
- Create: `app/Jobs/DeliverWebhook.php`
- Create: `app/Services/Api/WebhookService.php`
- Test: `tests/Unit/Jobs/DeliverWebhookTest.php`

**Step 1: Create WebhookService**

Create `app/Services/Api/WebhookService.php`:

```php
<?php

namespace App\Services\Api;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;

class WebhookService
{
    /**
     * Available webhook event types.
     *
     * @var list<string>
     */
    public const EVENTS = [
        'user.created',
        'user.updated',
        'user.deactivated',
        'settings.updated',
        'role.created',
        'role.updated',
        'role.deleted',
    ];

    /**
     * Generate HMAC-SHA256 signature for a webhook payload.
     */
    public static function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Dispatch webhook deliveries for a given event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $event, array $payload): void
    {
        $webhooks = Webhook::query()
            ->where('is_active', true)
            ->whereJsonContains('events', $event)
            ->get();

        foreach ($webhooks as $webhook) {
            DeliverWebhook::dispatch($webhook, $event, $payload);
        }
    }

    /**
     * Get all available webhook event types.
     *
     * @return list<string>
     */
    public function availableEvents(): array
    {
        return self::EVENTS;
    }
}
```

**Step 2: Create DeliverWebhook job**

Create `app/Jobs/DeliverWebhook.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\Api\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum retry attempts (6 retries with exponential backoff).
     */
    public int $tries = 6;

    /**
     * The webhook delivery timeout in seconds.
     */
    public int $timeout = 30;

    public function __construct(
        public Webhook $webhook,
        public string $event,
        /** @var array<string, mixed> */
        public array $payload,
    ) {
        $this->onQueue('webhooks');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(3, 5))->backoff(1),
        ];
    }

    /**
     * Calculate backoff delays for exponential retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        // Retries at: 1min, 5min, 30min, 2hr, 6hr, 12hr
        return [60, 300, 1800, 7200, 21600, 43200];
    }

    public function handle(): void
    {
        $this->webhook->refresh();

        if (! $this->webhook->is_active) {
            return;
        }

        $jsonPayload = json_encode([
            'event' => $this->event,
            'data' => $this->payload,
            'timestamp' => now()->toIso8601String(),
        ]);

        $signature = WebhookService::sign($jsonPayload, $this->webhook->secret);

        $log = WebhookLog::create([
            'webhook_id' => $this->webhook->id,
            'event' => $this->event,
            'payload' => $this->payload,
            'attempts' => $this->attempts(),
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Signals-Signature' => $signature,
                    'X-Signals-Event' => $this->event,
                    'User-Agent' => 'Signals-Webhook/1.0',
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post($this->webhook->url);

            $log->update([
                'response_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 10000),
                'delivered_at' => $response->successful() ? now() : null,
            ]);

            if ($response->successful()) {
                $this->webhook->update(['consecutive_failures' => 0]);
            } else {
                $this->recordFailure();
            }
        } catch (\Throwable $e) {
            $log->update([
                'response_body' => mb_substr($e->getMessage(), 0, 10000),
            ]);

            $this->recordFailure();

            throw $e;
        }
    }

    /**
     * Record a delivery failure and auto-disable after 3 consecutive days.
     */
    private function recordFailure(): void
    {
        $this->webhook->increment('consecutive_failures');

        // Auto-disable after 3 days of consecutive failures (roughly 18 attempts at 6/day)
        if ($this->webhook->consecutive_failures >= 18) {
            $this->webhook->update([
                'is_active' => false,
                'disabled_at' => now(),
            ]);
        }
    }
}
```

**Step 3: Write tests**

Create `tests/Unit/Jobs/DeliverWebhookTest.php`:

```php
<?php

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('generates correct HMAC-SHA256 signature', function () {
    $payload = '{"event":"user.created","data":{"id":1}}';
    $secret = 'test-secret';

    $signature = WebhookService::sign($payload, $secret);

    expect($signature)->toBe(hash_hmac('sha256', $payload, $secret));
});

it('dispatches webhook delivery to correct queue', function () {
    Queue::fake();

    $webhook = Webhook::factory()->create();

    DeliverWebhook::dispatch($webhook, 'user.created', ['id' => 1]);

    Queue::assertPushedOn('webhooks', DeliverWebhook::class);
});

it('delivers webhook and logs success', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $webhook = Webhook::factory()->create();

    (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle();

    $log = WebhookLog::where('webhook_id', $webhook->id)->first();
    expect($log)->not->toBeNull();
    expect($log->response_code)->toBe(200);
    expect($log->delivered_at)->not->toBeNull();
    expect($webhook->fresh()->consecutive_failures)->toBe(0);
});

it('records failure and increments consecutive failures', function () {
    Http::fake(['*' => Http::response('Error', 500)]);

    $webhook = Webhook::factory()->create(['consecutive_failures' => 0]);

    (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle();

    expect($webhook->fresh()->consecutive_failures)->toBe(1);
});

it('skips delivery for inactive webhooks', function () {
    Http::fake();

    $webhook = Webhook::factory()->disabled()->create();

    (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle();

    Http::assertNothingSent();
});

it('sends correct headers including signature', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $webhook = Webhook::factory()->create(['secret' => 'my-secret']);

    (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle();

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Signals-Signature')
            && $request->hasHeader('X-Signals-Event')
            && $request->header('X-Signals-Event')[0] === 'user.created';
    });
});
```

**Step 4: Run tests**

Run: `php artisan test tests/Unit/Jobs/DeliverWebhookTest.php --compact`
Expected: PASS

**Step 5: Commit**

```
feat: add webhook delivery job with HMAC signing and retry logic
```

---

## Task 12: Webhook API Controller & DTOs

**Files:**
- Create: `app/Data/Api/WebhookData.php`
- Create: `app/Data/Api/CreateWebhookData.php`
- Create: `app/Data/Api/UpdateWebhookData.php`
- Create: `app/Http/Controllers/Api/V1/WebhookController.php`
- Test: `tests/Feature/Api/WebhookApiTest.php`

**Step 1: Create DTOs**

Create `app/Data/Api/WebhookData.php`:

```php
<?php

namespace App\Data\Api;

use App\Models\Webhook;

class WebhookData
{
    public function __construct(
        public readonly int $id,
        public readonly string $url,
        /** @var list<string> */
        public readonly array $events,
        public readonly bool $is_active,
        public readonly int $consecutive_failures,
        public readonly ?string $disabled_at,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {}

    public static function fromModel(Webhook $webhook): self
    {
        return new self(
            id: $webhook->id,
            url: $webhook->url,
            events: $webhook->events ?? [],
            is_active: $webhook->is_active,
            consecutive_failures: $webhook->consecutive_failures,
            disabled_at: $webhook->disabled_at?->toIso8601String(),
            created_at: $webhook->created_at?->toIso8601String(),
            updated_at: $webhook->updated_at?->toIso8601String(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
```

Create `app/Data/Api/CreateWebhookData.php`:

```php
<?php

namespace App\Data\Api;

use App\Services\Api\WebhookService;
use Illuminate\Validation\Rule;

class CreateWebhookData
{
    public function __construct(
        public readonly string $url,
        /** @var list<string> */
        public readonly array $events,
    ) {}

    /** @param array{url: string, events: list<string>} $data */
    public static function from(array $data): self
    {
        return new self(
            url: $data['url'],
            events: $data['events'],
        );
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookService::EVENTS)],
        ];
    }
}
```

Create `app/Data/Api/UpdateWebhookData.php`:

```php
<?php

namespace App\Data\Api;

use App\Services\Api\WebhookService;
use Illuminate\Validation\Rule;

class UpdateWebhookData
{
    public function __construct(
        public readonly ?string $url = null,
        /** @var list<string>|null */
        public readonly ?array $events = null,
        public readonly ?bool $is_active = null,
    ) {}

    /** @param array{url?: string, events?: list<string>, is_active?: bool} $data */
    public static function from(array $data): self
    {
        return new self(
            url: $data['url'] ?? null,
            events: $data['events'] ?? null,
            is_active: $data['is_active'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'url' => ['sometimes', 'url', 'max:2048'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookService::EVENTS)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
```

**Step 2: Create WebhookController**

Create `app/Http/Controllers/Api/V1/WebhookController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\CreateWebhookData;
use App\Data\Api\UpdateWebhookData;
use App\Data\Api\WebhookData;
use App\Http\Controllers\Api\Controller;
use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /**
     * List all webhooks for the authenticated user.
     *
     * @operationId listWebhooks
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('webhooks.manage', 'webhooks:manage');

        $webhooks = Webhook::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Webhook $w) => WebhookData::fromModel($w)->toArray())
            ->all();

        return response()->json(['webhooks' => $webhooks]);
    }

    /**
     * Show a single webhook.
     *
     * @operationId getWebhook
     */
    public function show(Webhook $webhook, Request $request): JsonResponse
    {
        $this->authorizeApi('webhooks.manage', 'webhooks:manage');
        $this->authorizeOwnership($webhook, $request);

        return $this->respondWith(WebhookData::fromModel($webhook)->toArray(), 'webhook');
    }

    /**
     * Register a new webhook.
     *
     * @operationId createWebhook
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('webhooks.manage', 'webhooks:manage');

        $validated = $request->validate(CreateWebhookData::rules());
        $dto = CreateWebhookData::from($validated);

        $webhook = Webhook::create([
            'user_id' => $request->user()->id,
            'url' => $dto->url,
            'secret' => Str::random(32),
            'events' => $dto->events,
            'is_active' => true,
        ]);

        // Return the secret only on creation
        $data = WebhookData::fromModel($webhook)->toArray();
        $data['secret'] = $webhook->secret;

        return $this->respondWith($data, 'webhook', 201);
    }

    /**
     * Update an existing webhook.
     *
     * @operationId updateWebhook
     */
    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        $this->authorizeApi('webhooks.manage', 'webhooks:manage');
        $this->authorizeOwnership($webhook, $request);

        $validated = $request->validate(UpdateWebhookData::rules());
        $dto = UpdateWebhookData::from($validated);

        $updates = array_filter([
            'url' => $dto->url,
            'events' => $dto->events,
            'is_active' => $dto->is_active,
        ], fn ($v) => $v !== null);

        // Reset failure counter when re-enabling
        if (($dto->is_active ?? false) && ! $webhook->is_active) {
            $updates['consecutive_failures'] = 0;
            $updates['disabled_at'] = null;
        }

        $webhook->update($updates);

        return $this->respondWith(WebhookData::fromModel($webhook->fresh())->toArray(), 'webhook');
    }

    /**
     * Delete a webhook.
     *
     * @operationId deleteWebhook
     */
    public function destroy(Request $request, Webhook $webhook): JsonResponse
    {
        $this->authorizeApi('webhooks.manage', 'webhooks:manage');
        $this->authorizeOwnership($webhook, $request);

        $webhook->delete();

        return response()->json(null, 204);
    }

    /**
     * List delivery logs for a webhook.
     *
     * @operationId listWebhookLogs
     */
    public function logs(Request $request, Webhook $webhook): JsonResponse
    {
        $this->authorizeApi('webhooks.manage', 'webhooks:manage');
        $this->authorizeOwnership($webhook, $request);

        $perPage = min((int) $request->input('per_page', 20), 100);

        $paginator = WebhookLog::query()
            ->where('webhook_id', $webhook->id)
            ->latest()
            ->paginate($perPage);

        $logs = collect($paginator->items())
            ->map(fn (WebhookLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'response_code' => $log->response_code,
                'attempts' => $log->attempts,
                'delivered_at' => $log->delivered_at?->toIso8601String(),
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();

        return $this->respondWithCollection($logs, 'logs', $paginator);
    }

    /**
     * Ensure the webhook belongs to the authenticated user.
     */
    private function authorizeOwnership(Webhook $webhook, Request $request): void
    {
        if ($webhook->user_id !== $request->user()->id && ! $request->user()->isOwner()) {
            abort(403, 'You do not own this webhook.');
        }
    }
}
```

**Step 3: Write tests**

Create `tests/Feature/Api/WebhookApiTest.php`:

```php
<?php

use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookLog;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/webhooks', function () {
    it('lists webhooks for the authenticated user', function () {
        Webhook::factory()->count(2)->create(['user_id' => $this->owner->id]);
        Webhook::factory()->create(); // another user's webhook
        Sanctum::actingAs($this->owner, ['webhooks:manage']);

        $this->getJson('/api/v1/webhooks')
            ->assertOk()
            ->assertJsonCount(2, 'webhooks');
    });
});

describe('POST /api/v1/webhooks', function () {
    it('creates a webhook and returns the secret', function () {
        Sanctum::actingAs($this->owner, ['webhooks:manage']);

        $response = $this->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/webhook',
            'events' => ['user.created', 'user.updated'],
        ])->assertCreated();

        $response->assertJsonPath('webhook.url', 'https://example.com/webhook');
        $response->assertJsonStructure(['webhook' => ['id', 'url', 'events', 'secret']]);

        $this->assertDatabaseHas('webhooks', [
            'user_id' => $this->owner->id,
            'url' => 'https://example.com/webhook',
        ]);
    });

    it('validates required fields', function () {
        Sanctum::actingAs($this->owner, ['webhooks:manage']);

        $this->postJson('/api/v1/webhooks', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url', 'events']);
    });

    it('validates event names', function () {
        Sanctum::actingAs($this->owner, ['webhooks:manage']);

        $this->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/webhook',
            'events' => ['invalid.event'],
        ])->assertUnprocessable();
    });
});

describe('PUT /api/v1/webhooks/{id}', function () {
    it('updates a webhook', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        Sanctum::actingAs($this->owner, ['webhooks:manage']);

        $this->putJson("/api/v1/webhooks/{$webhook->id}", [
            'url' => 'https://new-url.com/hook',
        ])
            ->assertOk()
            ->assertJsonPath('webhook.url', 'https://new-url.com/hook');
    });

    it('resets failure count when re-enabling', function () {
        $webhook = Webhook::factory()->disabled()->create([
            'user_id' => $this->owner->id,
            'consecutive_failures' => 10,
        ]);
        Sanctum::actingAs($this->owner, ['webhooks:manage']);

        $this->putJson("/api/v1/webhooks/{$webhook->id}", [
            'is_active' => true,
        ])->assertOk();

        expect($webhook->fresh()->consecutive_failures)->toBe(0);
        expect($webhook->fresh()->disabled_at)->toBeNull();
    });
});

describe('DELETE /api/v1/webhooks/{id}', function () {
    it('deletes a webhook', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        Sanctum::actingAs($this->owner, ['webhooks:manage']);

        $this->deleteJson("/api/v1/webhooks/{$webhook->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    });
});

describe('GET /api/v1/webhooks/{id}/logs', function () {
    it('lists delivery logs for a webhook', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
        WebhookLog::factory()->count(3)->create(['webhook_id' => $webhook->id]);
        Sanctum::actingAs($this->owner, ['webhooks:manage']);

        $this->getJson("/api/v1/webhooks/{$webhook->id}/logs")
            ->assertOk()
            ->assertJsonCount(3, 'logs')
            ->assertJsonStructure([
                'logs' => [['id', 'event', 'response_code', 'attempts', 'delivered_at']],
                'meta' => ['total', 'per_page', 'page'],
            ]);
    });
});
```

**Step 4: Run tests**

Run: `php artisan test tests/Feature/Api/WebhookApiTest.php --compact`
Expected: PASS

**Step 5: Commit**

```
feat: add webhook API with registration, delivery logs, and CRUD
```

---

## Task 13: Admin API Token Management Page

**Files:**
- Create: `resources/views/livewire/admin/settings/api.blade.php`
- Modify: `routes/web.php` (add admin API route)
- Test: `tests/Feature/Admin/Settings/ApiSettingsTest.php`

**Step 1: Add the route**

In `routes/web.php`, add within the admin group (after line 75, within the Account section):

```php
// API
Volt::route('admin/settings/api', 'admin.settings.api')->name('admin.settings.api');
```

**Step 2: Create the Volt component**

Create `resources/views/livewire/admin/settings/api.blade.php` as a Volt single-file component following the pattern from `users.blade.php`. The page should:

- List existing API tokens with name, abilities, last used, and created dates
- Allow creating new tokens with name and ability checkboxes
- Show the token value once on creation (cannot be shown again)
- Allow revoking tokens
- Show API settings (rate limit, token expiration)

Follow the Volt pattern used in existing admin pages (anonymous class, `#[Layout('components.layouts.app')]`, `<x-admin.layout>` wrapper, Flux UI components).

The component should use `$this->user = auth()->user()` and `$this->user->tokens()` for Sanctum token management.

Available abilities to display as checkboxes:
```php
$abilities = [
    'settings:read' => 'Read settings',
    'settings:write' => 'Write settings',
    'users:read' => 'Read users',
    'users:write' => 'Write users',
    'roles:read' => 'Read roles',
    'roles:write' => 'Write roles',
    'webhooks:manage' => 'Manage webhooks',
    'system:read' => 'Read system info',
    'action-log:read' => 'Read action logs',
];
```

**Step 3: Write tests**

Create `tests/Feature/Admin/Settings/ApiSettingsTest.php`:

```php
<?php

use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('renders the api settings page', function () {
    $this->get(route('admin.settings.api'))
        ->assertOk()
        ->assertSee('API Tokens');
});

it('can create a new api token', function () {
    Volt::test('admin.settings.api')
        ->set('tokenName', 'My Token')
        ->set('selectedAbilities', ['users:read', 'settings:read'])
        ->call('createToken')
        ->assertHasNoErrors();

    expect($this->owner->tokens()->count())->toBe(1);
});

it('shows the token value after creation', function () {
    $component = Volt::test('admin.settings.api')
        ->set('tokenName', 'My Token')
        ->set('selectedAbilities', ['users:read'])
        ->call('createToken');

    $component->assertSet('showTokenValue', true);
});

it('can revoke a token', function () {
    $token = $this->owner->createToken('Test', ['users:read']);

    Volt::test('admin.settings.api')
        ->call('revokeToken', $token->accessToken->id)
        ->assertHasNoErrors();

    expect($this->owner->tokens()->count())->toBe(0);
});

it('requires admin access', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('admin.settings.api'))
        ->assertForbidden();
});
```

**Step 4: Run tests**

Run: `php artisan test tests/Feature/Admin/Settings/ApiSettingsTest.php --compact`
Expected: PASS

**Step 5: Commit**

```
feat: add admin API token management page
```

---

## Task 14: Scramble Configuration & API Documentation

**Files:**
- Modify: `config/scramble.php`
- Modify: `docs/api/overview.md` (update with concrete endpoint info)
- Modify: `docs/documentation.json` (add API token management page reference)
- Create: `docs/api/authentication.md`
- Modify: `docs/platform/admin-panel.md` (add API section)

**Step 1: Update Scramble config**

In `config/scramble.php`, update the info section:

```php
'info' => [
    'version' => env('API_VERSION', '1.0.0'),
    'description' => 'The Signals API provides programmatic access to all platform features. Authenticate with Bearer tokens, filter with Ransack-compatible syntax, and receive CRMS-compatible response shapes.',
],
```

Update the UI section:

```php
'ui' => [
    'title' => 'Signals API',
    'theme' => 'light',
    'hide_try_it' => false,
    'hide_schemas' => false,
    'logo' => '',
    'try_it_credentials_policy' => 'include',
    'layout' => 'responsive',
],
```

**Step 2: Create authentication docs page**

Create `docs/api/authentication.md`:

```markdown
---
title: Authentication
description: How to authenticate with the Signals API using bearer tokens.
---

## Authentication

The Signals API uses **Bearer tokens** issued via Laravel Sanctum. Generate tokens from the admin panel at **Admin > API** or programmatically via the API.

### Creating a Token

Navigate to **Admin > Settings > API** in the web interface. Click **Create Token**, name your token, select the abilities it should have, and click **Generate**. The token value is shown once — copy it immediately.

### Using a Token

Include the token in the `Authorization` header of every API request:

    Authorization: Bearer {your-token}

All requests must also include `Accept: application/json`.

### Token Abilities

Tokens are scoped with abilities that control what endpoints they can access:

| Ability | Description |
|---------|-------------|
| `settings:read` | Read settings |
| `settings:write` | Update settings |
| `users:read` | List and view users |
| `users:write` | Create, update, and deactivate users |
| `roles:read` | List and view roles |
| `roles:write` | Create, update, and delete roles |
| `webhooks:manage` | Manage webhook registrations |
| `system:read` | View system health |
| `action-log:read` | View the audit trail |

### Rate Limiting

API requests are rate-limited. Default limits:

| Context | Limit |
|---------|-------|
| Authenticated | 60 requests/minute |
| Unauthenticated | 20 requests/minute |

Rate limit headers are included in every response:

- `X-RateLimit-Limit` — maximum requests per window
- `X-RateLimit-Remaining` — requests remaining
- `Retry-After` — seconds until limit resets (on 429)

Limits are configurable in **Admin > Settings > API**.
```

**Step 3: Update documentation manifest**

Update `docs/documentation.json` to add the new API page:

```json
{
    "title": "API",
    "slug": "api",
    "pages": [
        { "title": "API Overview", "slug": "overview" },
        { "title": "Authentication", "slug": "authentication" }
    ]
}
```

**Step 4: Update admin-panel.md**

Add an "API" section to `docs/platform/admin-panel.md` after the System section, documenting the API token management page.

**Step 5: Commit**

```
feat: update Scramble config and add API documentation pages
```

---

## Task 15: Register Permissions & Settings, Wire Events

**Files:**
- Modify: `database/seeders/PermissionSeeder.php` (add API permissions)
- Modify: `app/Providers/AppServiceProvider.php` (register ApiSettings, RateLimiter)
- Modify: relevant action classes to dispatch webhook events

**Step 1: Add API permissions to PermissionSeeder**

Check the existing `PermissionSeeder::permissions()` method and add these permissions if not present:

- `settings.read`
- `settings.manage`
- `webhooks.manage`
- `system.read`
- `action-log.view`

(The `users.*` and `roles.*` permissions likely already exist.)

**Step 2: Register ApiSettings and RateLimiter in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, add to the `boot()` method:

```php
use App\Settings\ApiSettings;
use App\Services\SettingsRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

// In boot(), after configureDefaults():
$this->configureApiRateLimiting();

// New method:
protected function configureApiRateLimiting(): void
{
    RateLimiter::for('api', function ($request) {
        $limit = (int) settings('api.rate_limit', 60);

        return $request->user()
            ? Limit::perMinute($limit)->by($request->user()->id)
            : Limit::perMinute((int) settings('api.rate_limit_unauthenticated', 20))->by($request->ip());
    });
}
```

Register `ApiSettings` with the `SettingsRegistry`. Find where other settings definitions are registered (likely in a provider or seeder) and add:

```php
$registry->register(new \App\Settings\ApiSettings);
```

**Step 3: Wire webhook dispatch into existing actions**

In `app/Actions/Admin/InviteUser.php`, dispatch webhook event after the user is created:

```php
app(\App\Services\Api\WebhookService::class)->dispatch('user.created', [
    'user' => \App\Data\Api\UserData::fromModel($user)->toArray(),
]);
```

Similarly add webhook dispatches to `UpdateUser`, `DeactivateUser`, `CreateRole`, `UpdateRole`, `DeleteRole`, and settings update actions.

**Step 4: Run the full API test suite**

Run: `php artisan test tests/Feature/Api/ tests/Unit/Services/RansackFilterTest.php tests/Unit/Jobs/DeliverWebhookTest.php --compact`
Expected: ALL PASS

**Step 5: Commit**

```
feat: register API permissions, settings, rate limiting, and webhook event dispatch
```

---

## Task 16: Quality Gate

**Step 1: Run full test suite**

Run: `php artisan test --parallel --compact --exclude-group=env-writing && php artisan test --compact --group=env-writing`
Expected: ALL PASS (no regressions)

**Step 2: Format code**

Run: `vendor/bin/pint --dirty --format agent`
Expected: No formatting issues, or auto-fixed

**Step 3: Static analysis**

Run: `vendor/bin/phpstan analyse`
Expected: 0 errors

**Step 4: Fix any issues found, re-run steps 1-3 until clean**

**Step 5: Commit any fixes**

```
chore: fix formatting and static analysis issues
```

---

## Summary of Deliverables

| # | Component | Files Created/Modified |
|---|-----------|----------------------|
| 1 | Sanctum auth | User model, migration |
| 2 | API routes & base controller | routes/api.php, bootstrap/app.php, Controller, Middleware |
| 3 | Rate limiting | ApiSettings, AppServiceProvider |
| 4 | RansackFilter | Service + 22 unit tests |
| 5 | FiltersQueries trait | Trait |
| 6 | Settings API | Controller + tests |
| 7 | Users API | Controller, 3 DTOs, tests |
| 8 | Roles API | Controller, 3 DTOs, tests |
| 9 | Action Log API | Controller, DTO, tests |
| 10 | Webhook models | 2 models, 2 migrations, 2 factories |
| 11 | Webhook delivery | Job, Service, tests |
| 12 | Webhook API | Controller, 3 DTOs, tests |
| 13 | Admin API page | Volt component, tests |
| 14 | Documentation | Scramble config, docs pages |
| 15 | Wiring | Permissions, settings registration, event dispatch |
| 16 | Quality gate | Tests, Pint, PHPStan |
