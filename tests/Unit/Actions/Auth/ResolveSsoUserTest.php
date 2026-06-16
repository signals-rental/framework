<?php

use App\Actions\Auth\ResolveSsoUser;
use App\Exceptions\Auth\SsoAccessDeniedException;
use App\Models\OAuthIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

// DatabaseMigrations: the action queries and writes users and oauth identities.
uses(TestCase::class, DatabaseMigrations::class);

beforeEach(function () {
    $this->resolve = app(ResolveSsoUser::class);
});

/**
 * Build a fake authenticated Socialite user with a controllable raw payload.
 *
 * @param  array<string, mixed>  $raw
 */
function fakeSocialiteUser(string $id, ?string $email, array $raw = []): SocialiteUser
{
    $user = new SocialiteUser;
    $user->id = $id;
    $user->email = $email;
    $user->setRaw($raw);

    return $user;
}

// ─── existing link ───────────────────────────────────────────────

it('returns the linked user when an oauth identity already exists', function () {
    $user = User::factory()->create();
    OAuthIdentity::factory()->google()->create([
        'user_id' => $user->id,
        'provider_id' => 'google-123',
    ]);

    $resolved = ($this->resolve)('google', fakeSocialiteUser('google-123', $user->email, ['email_verified' => true]));

    expect($resolved->id)->toBe($user->id);
});

it('matches the existing link by (provider, provider_id) ignoring the incoming email', function () {
    $user = User::factory()->create(['email' => 'current@example.com']);
    OAuthIdentity::factory()->google()->create([
        'user_id' => $user->id,
        'provider_id' => 'google-stable',
        'email' => 'old@example.com',
    ]);

    // Email changed at the IdP, but the stable subject id still resolves the user.
    $resolved = ($this->resolve)('google', fakeSocialiteUser('google-stable', 'changed@example.com'));

    expect($resolved->id)->toBe($user->id);
});

it('does not create a duplicate identity for an existing link', function () {
    $user = User::factory()->create();
    OAuthIdentity::factory()->google()->create([
        'user_id' => $user->id,
        'provider_id' => 'google-123',
    ]);

    ($this->resolve)('google', fakeSocialiteUser('google-123', $user->email, ['email_verified' => true]));

    expect(OAuthIdentity::query()->where('user_id', $user->id)->count())->toBe(1);
});

// ─── auto-link by verified email ─────────────────────────────────

it('auto-links by verified google email and creates an oauth identity', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);

    $resolved = ($this->resolve)('google', fakeSocialiteUser('google-new', 'staff@example.com', ['email_verified' => true]));

    expect($resolved->id)->toBe($user->id);

    $this->assertDatabaseHas('oauth_identities', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-new',
        'email' => 'staff@example.com',
    ]);
});

it('auto-links a microsoft user by present email without an email_verified flag', function () {
    $user = User::factory()->create(['email' => 'ms-staff@example.com']);

    // Microsoft does not return an email_verified field; a present email is trusted.
    $resolved = ($this->resolve)('microsoft', fakeSocialiteUser('ms-1', 'ms-staff@example.com'));

    expect($resolved->id)->toBe($user->id);

    $this->assertDatabaseHas('oauth_identities', [
        'user_id' => $user->id,
        'provider' => 'microsoft',
        'provider_id' => 'ms-1',
    ]);
});

// ─── deny: unknown email ─────────────────────────────────────────

it('denies a verified email that matches no signals user', function () {
    ($this->resolve)('google', fakeSocialiteUser('google-x', 'stranger@example.com', ['email_verified' => true]));
})->throws(SsoAccessDeniedException::class);

// ─── deny: unverified / missing email ────────────────────────────

it('denies an unverified google email', function () {
    User::factory()->create(['email' => 'staff@example.com']);

    ($this->resolve)('google', fakeSocialiteUser('google-unv', 'staff@example.com', ['email_verified' => false]));
})->throws(SsoAccessDeniedException::class);

it('denies a google login when the email_verified flag is absent', function () {
    User::factory()->create(['email' => 'staff@example.com']);

    ($this->resolve)('google', fakeSocialiteUser('google-noflag', 'staff@example.com', []));
})->throws(SsoAccessDeniedException::class);

it('denies a microsoft login with an empty email', function () {
    ($this->resolve)('microsoft', fakeSocialiteUser('ms-empty', ''));
})->throws(SsoAccessDeniedException::class);

// ─── deny: inactive user ─────────────────────────────────────────

it('denies an inactive user matched via the by-email path', function () {
    User::factory()->deactivated()->create(['email' => 'inactive@example.com']);

    ($this->resolve)('google', fakeSocialiteUser('google-inactive', 'inactive@example.com', ['email_verified' => true]));
})->throws(SsoAccessDeniedException::class);

it('does not link an inactive user matched by email', function () {
    $user = User::factory()->deactivated()->create(['email' => 'inactive@example.com']);

    try {
        ($this->resolve)('google', fakeSocialiteUser('google-inactive', 'inactive@example.com', ['email_verified' => true]));
    } catch (SsoAccessDeniedException) {
        // expected
    }

    expect(OAuthIdentity::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('denies an inactive user matched via an existing link', function () {
    $user = User::factory()->deactivated()->create();
    OAuthIdentity::factory()->google()->create([
        'user_id' => $user->id,
        'provider_id' => 'google-linked-inactive',
    ]);

    ($this->resolve)('google', fakeSocialiteUser('google-linked-inactive', $user->email, ['email_verified' => true]));
})->throws(SsoAccessDeniedException::class);

// ─── enumeration resistance: generic visitor message, distinct log reason ───

it('shows the same generic visitor reason for unknown and inactive users', function () {
    // Unknown email: no matching Signals user.
    $unknown = null;
    try {
        ($this->resolve)('google', fakeSocialiteUser('g-enum-unknown', 'stranger@example.com', ['email_verified' => true]));
    } catch (SsoAccessDeniedException $e) {
        $unknown = $e;
    }

    // Inactive user matched by verified email.
    User::factory()->deactivated()->create(['email' => 'inactive@example.com']);
    $inactive = null;
    try {
        ($this->resolve)('google', fakeSocialiteUser('g-enum-inactive', 'inactive@example.com', ['email_verified' => true]));
    } catch (SsoAccessDeniedException $e) {
        $inactive = $e;
    }

    expect($unknown)->not->toBeNull()
        ->and($inactive)->not->toBeNull()
        // Visitor-facing reason is identical → no account/status enumeration.
        ->and($inactive->reason)->toBe($unknown->reason)
        ->and($unknown->reason)->toBe('We could not sign you in with that account. Please contact your administrator.')
        // Server-side log reason still distinguishes the two causes.
        ->and($unknown->logReason)->toBe('no_matching_user')
        ->and($inactive->logReason)->toBe('inactive_user');
});

it('carries a distinct log reason for an inactive user matched via an existing link', function () {
    $user = User::factory()->deactivated()->create();
    OAuthIdentity::factory()->google()->create([
        'user_id' => $user->id,
        'provider_id' => 'g-enum-linked-inactive',
    ]);

    try {
        ($this->resolve)('google', fakeSocialiteUser('g-enum-linked-inactive', $user->email, ['email_verified' => true]));
        $this->fail('Expected SsoAccessDeniedException.');
    } catch (SsoAccessDeniedException $e) {
        expect($e->logReason)->toBe('inactive_user')
            ->and($e->reason)->toBe('We could not sign you in with that account. Please contact your administrator.');
    }
});

// ─── deny: orphaned identity (null user) ─────────────────────────

it('denies when the matched identity has no user (orphaned row)', function () {
    $user = User::factory()->create();
    OAuthIdentity::factory()->google()->create([
        'user_id' => $user->id,
        'provider_id' => 'google-orphan',
    ]);

    // Drop the user without cascading, leaving the identity's user relation null.
    Schema::disableForeignKeyConstraints();
    $user->delete();
    Schema::enableForeignKeyConstraints();

    ($this->resolve)('google', fakeSocialiteUser('google-orphan', 'gone@example.com', ['email_verified' => true]));
})->throws(SsoAccessDeniedException::class);

// ─── email-domain allow-list ─────────────────────────────────────

it('allows any domain when the allow-list is empty', function () {
    settings()->set('sso.allowed_email_domains', [], 'json');
    $user = User::factory()->create(['email' => 'staff@anything.example']);

    $resolved = ($this->resolve)('google', fakeSocialiteUser('g-empty-allow', 'staff@anything.example', ['email_verified' => true]));

    expect($resolved->id)->toBe($user->id);
});

it('allows an in-list domain when the allow-list is set (google)', function () {
    settings()->set('sso.allowed_email_domains', ['owned.example'], 'json');
    $user = User::factory()->create(['email' => 'staff@owned.example']);

    $resolved = ($this->resolve)('google', fakeSocialiteUser('g-allow', 'staff@owned.example', ['email_verified' => true]));

    expect($resolved->id)->toBe($user->id);
});

it('allows an in-list domain when the allow-list is set (microsoft)', function () {
    settings()->set('sso.allowed_email_domains', ['owned.example'], 'json');
    $user = User::factory()->create(['email' => 'staff@owned.example']);

    $resolved = ($this->resolve)('microsoft', fakeSocialiteUser('ms-allow', 'staff@owned.example'));

    expect($resolved->id)->toBe($user->id);
});

it('denies an out-of-list domain when the allow-list is set (google)', function () {
    settings()->set('sso.allowed_email_domains', ['owned.example'], 'json');
    User::factory()->create(['email' => 'staff@evil.example']);

    ($this->resolve)('google', fakeSocialiteUser('g-deny', 'staff@evil.example', ['email_verified' => true]));
})->throws(SsoAccessDeniedException::class);

it('denies an out-of-list domain when the allow-list is set (microsoft)', function () {
    settings()->set('sso.allowed_email_domains', ['owned.example'], 'json');
    User::factory()->create(['email' => 'staff@evil.example']);

    ($this->resolve)('microsoft', fakeSocialiteUser('ms-deny', 'staff@evil.example'));
})->throws(SsoAccessDeniedException::class);

it('matches the allow-list domain case-insensitively', function () {
    // Allow-list stored mixed-case; the IdP email domain arrives upper-case.
    // Use an existing link so the domain gate (case-insensitive) is the only thing under test.
    settings()->set('sso.allowed_email_domains', ['Owned.Example'], 'json');
    $user = User::factory()->create(['email' => 'staff@owned.example']);
    OAuthIdentity::factory()->google()->create([
        'user_id' => $user->id,
        'provider_id' => 'g-case',
    ]);

    $resolved = ($this->resolve)('google', fakeSocialiteUser('g-case', 'Staff@OWNED.example', ['email_verified' => true]));

    expect($resolved->id)->toBe($user->id);
});

it('gates the existing-link path on the allow-list (now-disallowed domain)', function () {
    settings()->set('sso.allowed_email_domains', ['owned.example'], 'json');
    $user = User::factory()->create(['email' => 'staff@owned.example']);
    OAuthIdentity::factory()->google()->create([
        'user_id' => $user->id,
        'provider_id' => 'g-linked-disallowed',
    ]);

    // A linked identity whose current IdP email is on a now-disallowed domain is denied.
    ($this->resolve)('google', fakeSocialiteUser('g-linked-disallowed', 'staff@evil.example', ['email_verified' => true]));
})->throws(SsoAccessDeniedException::class);

it('denies a missing email when the allow-list is non-empty', function () {
    settings()->set('sso.allowed_email_domains', ['owned.example'], 'json');

    ($this->resolve)('microsoft', fakeSocialiteUser('ms-noemail', null));
})->throws(SsoAccessDeniedException::class);

it('still verifies google email via the raw payload with an allow-list set', function () {
    settings()->set('sso.allowed_email_domains', ['owned.example'], 'json');
    User::factory()->create(['email' => 'staff@owned.example']);

    // In-list domain but unverified → denied by the verification gate, not the domain gate.
    ($this->resolve)('google', fakeSocialiteUser('g-unv-allow', 'staff@owned.example', ['email_verified' => false]));
})->throws(SsoAccessDeniedException::class);
