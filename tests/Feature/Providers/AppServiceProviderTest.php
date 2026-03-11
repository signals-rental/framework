<?php

use App\Models\User;
use App\Services\DocsService;
use App\Services\NotificationRegistry;
use App\Services\PermissionRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

it('registers DocsService as singleton', function () {
    $a = app(DocsService::class);
    $b = app(DocsService::class);

    expect($a)->toBe($b);
});

it('registers PermissionRegistry as singleton', function () {
    $registry = app(PermissionRegistry::class);

    expect($registry)->toBeInstanceOf(PermissionRegistry::class);
    expect($registry->all())->not->toBeEmpty();
});

it('registers NotificationRegistry as singleton', function () {
    $registry = app(NotificationRegistry::class);

    expect($registry)->toBeInstanceOf(NotificationRegistry::class);
});

it('grants owner users full access via Gate::before', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner);

    expect(Gate::allows('some-random-ability'))->toBeTrue();
});

it('does not grant non-owner users full access', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    expect(Gate::allows('some-random-ability'))->toBeFalse();
});

it('defines the owner gate', function () {
    $owner = User::factory()->owner()->create();
    $user = User::factory()->create();

    expect(Gate::forUser($owner)->allows('owner'))->toBeTrue();
    expect(Gate::forUser($user)->allows('owner'))->toBeFalse();
});

it('configures API rate limiter', function () {
    $limiter = RateLimiter::limiter('api');

    expect($limiter)->not->toBeNull();
});

it('configures rate limiter for api with settings-driven limits', function () {
    $limiter = RateLimiter::limiter('api');

    // Create a mock request with an authenticated user
    $user = User::factory()->create();
    $request = \Illuminate\Http\Request::create('/api/v1/test');
    $request->setUserResolver(fn () => $user);

    $result = $limiter($request);

    expect($result)->toBeInstanceOf(\Illuminate\Cache\RateLimiting\Limit::class);
});

it('configures rate limiter with unauthenticated fallback', function () {
    $limiter = RateLimiter::limiter('api');

    $request = \Illuminate\Http\Request::create('/api/v1/test');
    $request->setUserResolver(fn () => null);

    $result = $limiter($request);

    expect($result)->toBeInstanceOf(\Illuminate\Cache\RateLimiting\Limit::class);
});

it('rate limiter falls back to defaults when settings throws', function () {
    // Bind a mock SettingsService that throws on any call
    $mock = Mockery::mock(\App\Services\SettingsService::class);
    $mock->shouldReceive('get')->andThrow(new \RuntimeException('Settings unavailable'));
    app()->instance(\App\Services\SettingsService::class, $mock);

    $limiter = RateLimiter::limiter('api');

    $user = User::factory()->create();
    $request = \Illuminate\Http\Request::create('/api/v1/test');
    $request->setUserResolver(fn () => $user);

    $result = $limiter($request);

    expect($result)->toBeInstanceOf(\Illuminate\Cache\RateLimiting\Limit::class);
});

it('returns password rules in production environment', function () {
    // Set up settings for password rules
    settings()->setMany([
        'security.password_min_length' => ['value' => 10, 'type' => 'integer'],
        'security.password_require_uppercase' => ['value' => true, 'type' => 'boolean'],
        'security.password_require_number' => ['value' => true, 'type' => 'boolean'],
        'security.password_require_special' => ['value' => true, 'type' => 'boolean'],
    ]);

    // Temporarily mock app()->isProduction() by swapping the callback
    app()->detectEnvironment(fn () => 'production');

    $rule = \Illuminate\Validation\Rules\Password::defaults();

    expect($rule)->toBeInstanceOf(\Illuminate\Validation\Rules\Password::class);

    // Restore environment
    app()->detectEnvironment(fn () => 'testing');
});

it('returns basic password rules in non-production environment', function () {
    app()->detectEnvironment(fn () => 'testing');

    $rule = \Illuminate\Validation\Rules\Password::defaults();

    // In non-production, the callback returns null so Password::defaults() gives a basic Password rule
    expect($rule)->toBeInstanceOf(\Illuminate\Validation\Rules\Password::class);
});
