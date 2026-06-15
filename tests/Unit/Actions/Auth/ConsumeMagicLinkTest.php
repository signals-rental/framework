<?php

use App\Actions\Auth\ConsumeMagicLink;
use App\Data\Auth\ConsumeMagicLinkData;
use App\Exceptions\Auth\InvalidMagicLinkException;
use App\Models\MagicLinkToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// DatabaseMigrations: the action reads/writes magic-link tokens and reads the
// settings() store from the DB.
uses(TestCase::class, DatabaseMigrations::class);

beforeEach(function () {
    settings()->set('security.magic_link_enabled', true, 'boolean');
    $this->consume = app(ConsumeMagicLink::class);
});

// ─── success ─────────────────────────────────────────────────────

it('returns the user and marks the token consumed on success', function () {
    $user = User::factory()->create();
    [$secret, $token] = mintMagicLinkToken($user);

    $resolved = ($this->consume)(new ConsumeMagicLinkData(secret: $secret));

    expect($resolved->id)->toBe($user->id);
    expect($token->fresh()->consumed_at)->not->toBeNull();
});

it('is single-use: a second consume of the same secret throws', function () {
    $user = User::factory()->create();
    [$secret] = mintMagicLinkToken($user);

    ($this->consume)(new ConsumeMagicLinkData(secret: $secret));

    expect(fn () => ($this->consume)(new ConsumeMagicLinkData(secret: $secret)))->toThrow(InvalidMagicLinkException::class);
});

it('loses the atomic claim when the token is consumed between read and update', function () {
    $user = User::factory()->create();
    [$secret, $token] = mintMagicLinkToken($user);

    // Simulate a concurrent click winning the race: the row is marked consumed
    // after this action reads it but before its conditional UPDATE runs. The
    // claim must then affect zero rows and surface as invalid.
    DB::listen(function ($query) use ($token): void {
        if (str_contains($query->sql, 'select') && str_contains($query->sql, 'magic_link_tokens')) {
            MagicLinkToken::query()->whereKey($token->id)->update(['consumed_at' => now()]);
        }
    });

    expect(fn () => ($this->consume)(new ConsumeMagicLinkData(secret: $secret)))->toThrow(InvalidMagicLinkException::class);
});

it('lets exactly one of two duplicate claims succeed (atomic single-use)', function () {
    $user = User::factory()->create();
    [$secret] = mintMagicLinkToken($user);

    // The first consume claims the row; a second consume of the same secret —
    // standing in for a duplicate/parallel click — must fail the atomic claim.
    $resolved = ($this->consume)(new ConsumeMagicLinkData(secret: $secret));
    expect($resolved->id)->toBe($user->id);

    expect(fn () => ($this->consume)(new ConsumeMagicLinkData(secret: $secret)))->toThrow(InvalidMagicLinkException::class);
});

// ─── failure paths (all throw the same exception) ────────────────

it('throws for an unknown token', function () {
    expect(fn () => ($this->consume)(new ConsumeMagicLinkData(secret: 'does-not-exist')))->toThrow(InvalidMagicLinkException::class);
});

it('throws for an expired token', function () {
    $user = User::factory()->create();
    [$secret] = mintMagicLinkToken($user, now()->subMinute());

    expect(fn () => ($this->consume)(new ConsumeMagicLinkData(secret: $secret)))->toThrow(InvalidMagicLinkException::class);
});

it('throws for an already-consumed token', function () {
    $user = User::factory()->create();
    $secret = Str::random(64);
    MagicLinkToken::factory()->for($user)->consumed()->create([
        'token_hash' => hash('sha256', $secret),
    ]);

    expect(fn () => ($this->consume)(new ConsumeMagicLinkData(secret: $secret)))->toThrow(InvalidMagicLinkException::class);
});

it('throws for an inactive user', function () {
    $user = User::factory()->deactivated()->create();
    [$secret] = mintMagicLinkToken($user);

    expect(fn () => ($this->consume)(new ConsumeMagicLinkData(secret: $secret)))->toThrow(InvalidMagicLinkException::class);
});

it('throws when the feature toggle has been turned off after minting', function () {
    $user = User::factory()->create();
    [$secret] = mintMagicLinkToken($user);

    settings()->set('security.magic_link_enabled', false, 'boolean');

    expect(fn () => ($this->consume)(new ConsumeMagicLinkData(secret: $secret)))->toThrow(InvalidMagicLinkException::class);
});

it('throws when SSO has been enforced after minting', function () {
    Role::findOrCreate('Sales', 'web');
    $user = User::factory()->create();
    $user->assignRole('Sales');
    [$secret] = mintMagicLinkToken($user);

    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');

    expect(fn () => ($this->consume)(new ConsumeMagicLinkData(secret: $secret)))->toThrow(InvalidMagicLinkException::class);
});

it('does not mark the token consumed when validation fails', function () {
    $user = User::factory()->deactivated()->create();
    [$secret, $token] = mintMagicLinkToken($user);

    try {
        ($this->consume)(new ConsumeMagicLinkData(secret: $secret));
    } catch (InvalidMagicLinkException) {
        // expected
    }

    expect($token->fresh()->consumed_at)->toBeNull();
});
