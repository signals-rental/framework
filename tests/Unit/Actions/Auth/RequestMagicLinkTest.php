<?php

use App\Actions\Auth\RequestMagicLink;
use App\Data\Auth\RequestMagicLinkData;
use App\Models\MagicLinkToken;
use App\Models\User;
use App\Notifications\MagicLinkLoginNotification;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// DatabaseMigrations: the action queries users and writes magic-link tokens,
// and reads the settings() store from the DB.
uses(TestCase::class, DatabaseMigrations::class);

beforeEach(function () {
    Notification::fake();
    settings()->set('security.magic_link_enabled', true, 'boolean');
    // Throttling uses the cache store, which persists across tests in a process.
    RateLimiter::clear('magic-link.request|staff@example.com|127.0.0.1');
    RateLimiter::clear('magic-link.request|sales@example.com|127.0.0.1');
    RateLimiter::clear('magic-link.request.ip|127.0.0.1');
    $this->request = app(RequestMagicLink::class);
});

// ─── eligible user ───────────────────────────────────────────────

it('mints a token and queues the notification for an eligible active user', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);

    ($this->request)(new RequestMagicLinkData(email: 'staff@example.com'));

    expect(MagicLinkToken::query()->where('user_id', $user->id)->count())->toBe(1);

    $token = MagicLinkToken::query()->where('user_id', $user->id)->first();
    expect($token->consumed_at)->toBeNull();
    expect($token->expires_at->isFuture())->toBeTrue();

    Notification::assertSentTo($user, MagicLinkLoginNotification::class);
});

it('stores only the sha256 hash of the secret, never the plaintext', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);

    ($this->request)(new RequestMagicLinkData(email: 'staff@example.com'));

    $token = MagicLinkToken::query()->where('user_id', $user->id)->first();

    // 64-char plaintext → sha256 is 64 hex chars; the stored value is the hash.
    expect($token->token_hash)->toHaveLength(64);
    expect(ctype_xdigit($token->token_hash))->toBeTrue();
});

it('queues a notification carrying a notification that verifies against the stored hash', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);

    ($this->request)(new RequestMagicLinkData(email: 'staff@example.com'));

    Notification::assertSentTo($user, MagicLinkLoginNotification::class, function ($notification) use ($user) {
        $secret = (new ReflectionClass($notification))->getProperty('secret')->getValue($notification);
        $token = MagicLinkToken::query()->where('user_id', $user->id)->first();

        return hash('sha256', $secret) === $token->token_hash;
    });
});

it('matches the stored email case-insensitively', function () {
    // Stored mixed-case; the submitted address arrives lowercased. Postgres-style
    // exact-match would silently no-op and the user would never get a link.
    $user = User::factory()->create(['email' => 'Foo@example.com']);

    RateLimiter::clear('magic-link.request|foo@example.com|127.0.0.1');

    ($this->request)(new RequestMagicLinkData(email: 'foo@example.com'));

    expect(MagicLinkToken::query()->where('user_id', $user->id)->count())->toBe(1);
    Notification::assertSentTo($user, MagicLinkLoginNotification::class);

    RateLimiter::clear('magic-link.request|foo@example.com|127.0.0.1');
});

it('matches the stored email when the submitted address is uppercased', function () {
    $user = User::factory()->create(['email' => 'foo@example.com']);

    RateLimiter::clear('magic-link.request|foo@example.com|127.0.0.1');

    ($this->request)(new RequestMagicLinkData(email: 'FOO@EXAMPLE.COM'));

    expect(MagicLinkToken::query()->where('user_id', $user->id)->count())->toBe(1);
    Notification::assertSentTo($user, MagicLinkLoginNotification::class);

    RateLimiter::clear('magic-link.request|foo@example.com|127.0.0.1');
});

// ─── no-op cases ─────────────────────────────────────────────────

it('does nothing for an unknown email', function () {
    ($this->request)(new RequestMagicLinkData(email: 'nobody@example.com'));

    expect(MagicLinkToken::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('does nothing for an inactive user', function () {
    $user = User::factory()->deactivated()->create(['email' => 'inactive@example.com']);

    ($this->request)(new RequestMagicLinkData(email: 'inactive@example.com'));

    expect(MagicLinkToken::query()->where('user_id', $user->id)->count())->toBe(0);
    Notification::assertNothingSent();
});

it('does nothing when the feature toggle is off', function () {
    settings()->set('security.magic_link_enabled', false, 'boolean');
    $user = User::factory()->create(['email' => 'staff@example.com']);

    ($this->request)(new RequestMagicLinkData(email: 'staff@example.com'));

    expect(MagicLinkToken::query()->where('user_id', $user->id)->count())->toBe(0);
    Notification::assertNothingSent();
});

it('does nothing for an SSO-enforced user', function () {
    Role::findOrCreate('Sales', 'web');
    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');

    $user = User::factory()->create(['email' => 'sales@example.com']);
    $user->assignRole('Sales');

    ($this->request)(new RequestMagicLinkData(email: 'sales@example.com'));

    expect(MagicLinkToken::query()->where('user_id', $user->id)->count())->toBe(0);
    Notification::assertNothingSent();
});

it('still mints a token for an Owner assigned to an SSO-enforced role', function () {
    // The Owner is the break-glass account: SsoEnforcement::isEnforcedFor() returns
    // false for owners regardless of their roles, so magic-link must remain
    // available even when an enforced role would otherwise lock other users out.
    Role::findOrCreate('Sales', 'web');
    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');

    $owner = User::factory()->owner()->create(['email' => 'owner-sales@example.com']);
    $owner->assignRole('Sales');

    RateLimiter::clear('magic-link.request|owner-sales@example.com|127.0.0.1');

    ($this->request)(new RequestMagicLinkData(email: 'owner-sales@example.com'));

    expect(MagicLinkToken::query()->where('user_id', $owner->id)->count())->toBe(1);
    Notification::assertSentTo($owner, MagicLinkLoginNotification::class);

    RateLimiter::clear('magic-link.request|owner-sales@example.com|127.0.0.1');
});

// ─── superseding outstanding tokens ──────────────────────────────

it('invalidates the user\'s prior unconsumed tokens on a new request', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);
    $old = MagicLinkToken::factory()->for($user)->create();

    ($this->request)(new RequestMagicLinkData(email: 'staff@example.com'));

    expect($old->fresh()->consumed_at)->not->toBeNull();
    expect(MagicLinkToken::query()->where('user_id', $user->id)->whereNull('consumed_at')->count())->toBe(1);
});

// ─── throttling ──────────────────────────────────────────────────

it('stops sending once the per-email+IP cap is reached', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);

    // First 3 requests are within the cap; the 4th is throttled and no-ops.
    ($this->request)(new RequestMagicLinkData(email: 'staff@example.com'));
    ($this->request)(new RequestMagicLinkData(email: 'staff@example.com'));
    ($this->request)(new RequestMagicLinkData(email: 'staff@example.com'));
    ($this->request)(new RequestMagicLinkData(email: 'staff@example.com'));

    // Each successful request supersedes the previous, so at most one unconsumed
    // token survives; the throttled 4th call mints nothing.
    expect(MagicLinkToken::query()->where('user_id', $user->id)->count())->toBe(3);
    Notification::assertSentToTimes($user, MagicLinkLoginNotification::class, 3);
});

it('returns silently (does not throw) when throttled', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);

    foreach (range(1, 3) as $ignored) {
        ($this->request)(new RequestMagicLinkData(email: 'staff@example.com'));
    }

    expect(fn () => ($this->request)(new RequestMagicLinkData(email: 'staff@example.com')))->not->toThrow(Exception::class);
});

it('caps total sends per IP across many distinct emails', function () {
    // 12 active, eligible users — each below the per-email cap on its own, so only
    // the broader per-IP cap (10) can stop the fan-out from a single IP.
    $users = collect(range(1, 12))->map(
        fn (int $i): User => User::factory()->create(['email' => "fanout{$i}@example.com"])
    );

    foreach ($users as $i => $user) {
        RateLimiter::clear('magic-link.request|'.strtolower($user->email).'|127.0.0.1');
        ($this->request)(new RequestMagicLinkData(email: $user->email));
    }

    // The first 10 requests mint and notify; the IP cap silences the rest.
    expect(MagicLinkToken::query()->count())->toBe(10);
    Notification::assertSentTimes(MagicLinkLoginNotification::class, 10);
})->after(function () {
    foreach (range(1, 12) as $i) {
        RateLimiter::clear("magic-link.request|fanout{$i}@example.com|127.0.0.1");
    }
});

it('returns silently (does not throw) when the per-IP cap is reached', function () {
    foreach (range(1, 10) as $i) {
        $user = User::factory()->create(['email' => "ipcap{$i}@example.com"]);
        RateLimiter::clear('magic-link.request|'.strtolower($user->email).'|127.0.0.1');
        ($this->request)(new RequestMagicLinkData(email: $user->email));
    }

    $blocked = User::factory()->create(['email' => 'ipcap-blocked@example.com']);

    expect(fn () => ($this->request)(new RequestMagicLinkData(email: $blocked->email)))->not->toThrow(Exception::class);
    expect(MagicLinkToken::query()->where('user_id', $blocked->id)->count())->toBe(0);
})->after(function () {
    foreach (range(1, 10) as $i) {
        RateLimiter::clear("magic-link.request|ipcap{$i}@example.com|127.0.0.1");
    }
    RateLimiter::clear('magic-link.request|ipcap-blocked@example.com|127.0.0.1');
});

afterEach(function () {
    RateLimiter::clear('magic-link.request|staff@example.com|127.0.0.1');
    RateLimiter::clear('magic-link.request.ip|127.0.0.1');
});
