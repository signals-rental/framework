<?php

use App\Actions\Auth\ResolveSsoUser;
use App\Exceptions\Auth\SsoAccessDeniedException;
use App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->resolve = app(ResolveSsoUser::class);
});

/**
 * Build a fake authenticated Socialite user with a controllable raw payload.
 *
 * @param  array<string, mixed>  $raw
 */
function covSocialiteUser(string $id, ?string $email, array $raw = []): SocialiteUser
{
    $user = new SocialiteUser;
    $user->id = $id;
    $user->email = $email;
    $user->setRaw($raw);

    return $user;
}

// ─── allowedEmailDomains(): non-array setting → empty list (line 123) ──

it('treats a non-array allowed-domains setting as no allow-list', function () {
    // A malformed (string) setting must degrade to "no allow-list" rather than blow
    // up — any domain is then permitted and resolution proceeds normally.
    settings()->set('sso.allowed_email_domains', 'not-an-array', 'string');

    $user = User::factory()->create(['email' => 'staff@anywhere.example']);

    $resolved = ($this->resolve)('google', covSocialiteUser('g-nonarray', 'staff@anywhere.example', ['email_verified' => true]));

    expect($resolved->id)->toBe($user->id);
});

// ─── allowedEmailDomains(): skips non-string entries (line 130) ───────

it('ignores non-string entries in the allowed-domains list', function () {
    // The list contains a non-string entry (an int) which must be skipped while the
    // valid string domain is still honoured.
    settings()->set('sso.allowed_email_domains', [123, 'owned.example'], 'json');

    $user = User::factory()->create(['email' => 'staff@owned.example']);

    $resolved = ($this->resolve)('google', covSocialiteUser('g-mixed', 'staff@owned.example', ['email_verified' => true]));

    expect($resolved->id)->toBe($user->id);
});

it('denies a domain absent from a list that contained non-string noise', function () {
    settings()->set('sso.allowed_email_domains', [123, 'owned.example'], 'json');
    User::factory()->create(['email' => 'staff@evil.example']);

    ($this->resolve)('google', covSocialiteUser('g-mixed-deny', 'staff@evil.example', ['email_verified' => true]));
})->throws(SsoAccessDeniedException::class);

// ─── domainOf(): email without an @ → null domain → denied (line 152) ──

it('denies an email that carries no domain when the allow-list is set', function () {
    // A domain-less email (no @) cannot be trusted against a non-empty allow-list:
    // domainOf() returns null and the gate denies sign-in.
    settings()->set('sso.allowed_email_domains', ['owned.example'], 'json');

    ($this->resolve)('google', covSocialiteUser('g-noat', 'not-an-email-address', ['email_verified' => true]));
})->throws(SsoAccessDeniedException::class);
