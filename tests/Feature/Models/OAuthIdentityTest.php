<?php

use App\Models\OAuthIdentity;
use App\Models\User;
use Illuminate\Database\QueryException;

it('builds an oauth identity via its factory', function () {
    $identity = OAuthIdentity::factory()->create();

    expect($identity->user)->toBeInstanceOf(User::class)
        ->and($identity->provider)->toBeIn(['google', 'microsoft'])
        ->and($identity->provider_id)->not->toBeEmpty()
        ->and($identity->email)->not->toBeEmpty();
});

it('supports google and microsoft factory states', function () {
    $google = OAuthIdentity::factory()->google()->create();
    $microsoft = OAuthIdentity::factory()->microsoft()->create();

    expect($google->provider)->toBe('google')
        ->and($microsoft->provider)->toBe('microsoft');
});

it('belongs to a user that has many oauth identities', function () {
    $user = User::factory()->create();

    $google = OAuthIdentity::factory()->google()->for($user)->create();
    $microsoft = OAuthIdentity::factory()->microsoft()->for($user)->create();

    expect($user->oauthIdentities)->toHaveCount(2)
        ->and($user->oauthIdentities->pluck('id'))
        ->toContain($google->id)
        ->toContain($microsoft->id)
        ->and($google->user->is($user))->toBeTrue();
});

it('enforces a unique provider and provider_id pair', function () {
    OAuthIdentity::factory()->create([
        'provider' => 'google',
        'provider_id' => 'subject-123',
    ]);

    OAuthIdentity::factory()->create([
        'provider' => 'google',
        'provider_id' => 'subject-123',
    ]);
})->throws(QueryException::class);

it('allows the same provider_id across different providers', function () {
    $google = OAuthIdentity::factory()->create([
        'provider' => 'google',
        'provider_id' => 'shared-subject',
    ]);

    $microsoft = OAuthIdentity::factory()->create([
        'provider' => 'microsoft',
        'provider_id' => 'shared-subject',
    ]);

    expect($google->exists)->toBeTrue()
        ->and($microsoft->exists)->toBeTrue();
});

it('cascades deletion when its user is deleted', function () {
    $identity = OAuthIdentity::factory()->create();

    $identity->user->delete();

    expect(OAuthIdentity::query()->whereKey($identity->id)->exists())->toBeFalse();
});
