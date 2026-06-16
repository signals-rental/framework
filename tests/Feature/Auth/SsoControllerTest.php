<?php

use App\Models\OAuthIdentity;
use App\Models\User;
use App\Services\Auth\SsoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

/**
 * Build a Socialite Two\User stand-in for a provider response.
 *
 * @param  array<string, mixed>  $raw
 */
function ssoSocialiteUser(string $id, string $email, array $raw = ['email_verified' => true]): SocialiteUser
{
    $user = new SocialiteUser;
    $user->map(['id' => $id, 'email' => $email]);
    $user->setRaw($raw);

    return $user;
}

/**
 * Swap the SsoService in the container with a mock that never performs real OAuth.
 *
 * `available()` is stubbed for the given provider and `driver()` returns a mocked
 * Socialite provider whose `redirect()`/`user()` behave per the closures supplied.
 */
function mockSso(string $provider, bool $available, ?SocialiteUser $socialiteUser = null, ?Throwable $userThrows = null): void
{
    $driver = Mockery::mock(SocialiteProvider::class);
    $driver->shouldReceive('redirect')->andReturn(redirect('https://provider.example/oauth'));

    if ($userThrows !== null) {
        $driver->shouldReceive('user')->andThrow($userThrows);
    } elseif ($socialiteUser !== null) {
        $driver->shouldReceive('user')->andReturn($socialiteUser);
    }

    $service = Mockery::mock(SsoService::class);
    $service->shouldReceive('available')->with($provider)->andReturn($available);
    $service->shouldReceive('available')->andReturn($available);
    $service->shouldReceive('driver')->with($provider)->andReturn($driver);

    app()->instance(SsoService::class, $service);

    // The router caches the resolved controller instance on the Route object for the
    // lifetime of the test application, so a controller built during an earlier request
    // keeps its original constructor-injected SsoService. Flush the cached controllers on
    // the SSO routes so the next request rebuilds the controller with this fresh mock —
    // without this, a second mockSso() call in the same test is silently ignored.
    foreach (['sso.redirect', 'sso.callback'] as $name) {
        app('router')->getRoutes()->getByName($name)?->flushController();
    }
}

// ─── redirect() ──────────────────────────────────────────────────

it('redirects an available provider to the consent screen', function () {
    mockSso('google', available: true);

    $this->get(route('sso.redirect', ['provider' => 'google']))
        ->assertRedirect('https://provider.example/oauth');
});

it('404s when the provider is disabled or unconfigured on redirect', function () {
    mockSso('google', available: false);

    $this->get(route('sso.redirect', ['provider' => 'google']))
        ->assertNotFound();
});

it('404s for an unknown provider via the route constraint', function () {
    $this->get('/auth/foo/redirect')->assertNotFound();
    $this->get('/auth/foo/callback')->assertNotFound();
});

it('registers the provider where-constraint on both sso routes', function () {
    foreach (['sso.redirect', 'sso.callback'] as $name) {
        $route = app('router')->getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route->wheres)->toHaveKey('provider')
            ->and($route->wheres['provider'])->toBe('google|microsoft');
    }
});

// ─── callback() — success paths ──────────────────────────────────

it('logs in via an existing identity link and redirects to dashboard', function () {
    $user = User::factory()->create();
    OAuthIdentity::factory()->google()->for($user)->create(['provider_id' => 'g-123']);

    mockSso('google', available: true, socialiteUser: ssoSocialiteUser('g-123', $user->email));

    $this->get(route('sso.callback', ['provider' => 'google']))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('auto-links by verified email and creates an oauth identity', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);

    mockSso('google', available: true, socialiteUser: ssoSocialiteUser('g-new', 'staff@example.com'));

    $this->get(route('sso.callback', ['provider' => 'google']))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('oauth_identities', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'g-new',
        'email' => 'staff@example.com',
    ]);
});

it('records an audit log entry for a successful sso login', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);

    mockSso('google', available: true, socialiteUser: ssoSocialiteUser('g-audit', 'staff@example.com'));

    $this->get(route('sso.callback', ['provider' => 'google']));

    $this->assertDatabaseHas('action_logs', [
        'user_id' => $user->id,
        'action' => 'auth.sso_login',
        'auditable_type' => $user->getMorphClass(),
        'auditable_id' => $user->id,
    ]);
});

// ─── callback() — deny paths ─────────────────────────────────────

it('denies an unknown email and redirects to login without authenticating', function () {
    mockSso('google', available: true, socialiteUser: ssoSocialiteUser('g-unknown', 'nobody@example.com'));

    $this->get(route('sso.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            // Generic, non-enumerable visitor message — identical to the inactive case.
            'email' => 'We could not sign you in with that account. Please contact your administrator.',
        ]);

    $this->assertGuest();
});

it('denies an inactive user and redirects to login without authenticating', function () {
    $user = User::factory()->deactivated()->create(['email' => 'inactive@example.com']);

    mockSso('google', available: true, socialiteUser: ssoSocialiteUser('g-inactive', 'inactive@example.com'));

    $this->get(route('sso.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            // Identical to the unknown-email case so a visitor cannot tell them apart.
            'email' => 'We could not sign you in with that account. Please contact your administrator.',
        ]);

    $this->assertGuest();
});

it('shows the visitor an identical message for unknown and inactive accounts', function () {
    // Unknown email.
    mockSso('google', available: true, socialiteUser: ssoSocialiteUser('g-enum-unknown', 'nobody@example.com'));
    $this->get(route('sso.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $unknownMessage = session('errors')->get('email')[0];

    // Inactive matched user.
    User::factory()->deactivated()->create(['email' => 'inactive@example.com']);
    mockSso('google', available: true, socialiteUser: ssoSocialiteUser('g-enum-inactive', 'inactive@example.com'));
    $this->get(route('sso.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $inactiveMessage = session('errors')->get('email')[0];

    expect($inactiveMessage)->toBe($unknownMessage)
        ->and($unknownMessage)->toBe('We could not sign you in with that account. Please contact your administrator.');
});

it('logs the specific access-denied reason server-side for diagnostics', function () {
    $log = Log::spy();

    // Unknown email → no_matching_user.
    mockSso('google', available: true, socialiteUser: ssoSocialiteUser('g-log-unknown', 'nobody@example.com'));
    $this->get(route('sso.callback', ['provider' => 'google']));

    $log->shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'sso.access_denied'
            && ($context['provider'] ?? null) === 'google'
            && ($context['log_reason'] ?? null) === 'no_matching_user')
        ->once();

    // Inactive user → inactive_user (distinct server-side cause for the same visitor message).
    User::factory()->deactivated()->create(['email' => 'inactive@example.com']);
    mockSso('google', available: true, socialiteUser: ssoSocialiteUser('g-log-inactive', 'inactive@example.com'));
    $this->get(route('sso.callback', ['provider' => 'google']));

    $log->shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'sso.access_denied'
            && ($context['log_reason'] ?? null) === 'inactive_user')
        ->once();
});

it('404s on callback when the provider is unavailable', function () {
    mockSso('google', available: false);

    $this->get(route('sso.callback', ['provider' => 'google']))
        ->assertNotFound();

    $this->assertGuest();
});

// ─── callback() — 2FA reuse ──────────────────────────────────────

it('hands a 2FA-enabled user to the challenge without authenticating', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'mfa@example.com']);
    OAuthIdentity::factory()->google()->for($user)->create(['provider_id' => 'g-mfa']);

    mockSso('google', available: true, socialiteUser: ssoSocialiteUser('g-mfa', 'mfa@example.com'));

    $this->get(route('sso.callback', ['provider' => 'google']))
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    expect(Session::get('two_factor_user_id'))->toBe($user->id);
    expect(Session::get('sso_provider'))->toBe('google');
});

it('audits the sso login after the full sso → 2FA challenge flow', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'mfa-audit@example.com']);
    OAuthIdentity::factory()->google()->for($user)->create(['provider_id' => 'g-mfa-audit']);

    mockSso('google', available: true, socialiteUser: ssoSocialiteUser('g-mfa-audit', 'mfa-audit@example.com'));

    // Step 1: SSO callback hands off to the challenge and stashes the provider.
    $this->get(route('sso.callback', ['provider' => 'google']))
        ->assertRedirect(route('two-factor.challenge'));

    // No SSO login is audited until the second factor completes the login.
    $this->assertDatabaseMissing('action_logs', [
        'user_id' => $user->id,
        'action' => 'auth.sso_login',
    ]);

    // Step 2: complete the challenge with a valid TOTP code.
    $code = app(Google2FA::class)->getCurrentOtp((string) $user->two_factor_secret);

    Volt::test('auth.two-factor-challenge')
        ->set('code', $code)
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);

    $this->assertDatabaseHas('action_logs', [
        'user_id' => $user->id,
        'action' => 'auth.sso_login',
        'auditable_type' => $user->getMorphClass(),
        'auditable_id' => $user->id,
    ]);

    // The provider key is cleared so a later password+2FA login isn't mis-audited.
    expect(Session::get('sso_provider'))->toBeNull();
});

// ─── callback() — OAuth handshake failure ────────────────────────

it('redirects to login with a generic error when the handshake fails', function () {
    mockSso('google', available: true, userThrows: new InvalidStateException);

    $this->get(route('sso.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs a warning when the oauth handshake fails', function () {
    $log = Log::spy();

    mockSso('google', available: true, userThrows: new InvalidStateException);

    $this->get(route('sso.callback', ['provider' => 'google']));

    $log->shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'sso.callback_failed'
            && ($context['provider'] ?? null) === 'google')
        ->once();
});
