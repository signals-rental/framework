<?php

use App\Models\MagicLinkToken;
use App\Models\User;

it('builds a magic link token via its factory', function () {
    $token = MagicLinkToken::factory()->create();

    expect($token->user)->toBeInstanceOf(User::class)
        ->and($token->token_hash)->not->toBeEmpty()
        ->and($token->expires_at->isFuture())->toBeTrue()
        ->and($token->consumed_at)->toBeNull();
});

it('belongs to a user that has many magic link tokens', function () {
    $user = User::factory()->create();

    $first = MagicLinkToken::factory()->for($user)->create();
    $second = MagicLinkToken::factory()->for($user)->create();

    expect($user->magicLinkTokens)->toHaveCount(2)
        ->and($user->magicLinkTokens->pluck('id'))
        ->toContain($first->id)
        ->toContain($second->id)
        ->and($first->user->is($user))->toBeTrue();
});

it('cascades deletion when its user is deleted', function () {
    $token = MagicLinkToken::factory()->create();

    $token->user->delete();

    expect(MagicLinkToken::query()->whereKey($token->id)->exists())->toBeFalse();
});

it('reports a fresh token as usable', function () {
    $token = MagicLinkToken::factory()->create();

    expect($token->isExpired())->toBeFalse()
        ->and($token->isConsumed())->toBeFalse()
        ->and($token->isUsable())->toBeTrue();
});

it('reports an expired token as not usable', function () {
    $token = MagicLinkToken::factory()->expired()->create();

    expect($token->isExpired())->toBeTrue()
        ->and($token->isConsumed())->toBeFalse()
        ->and($token->isUsable())->toBeFalse();
});

it('reports a consumed token as not usable', function () {
    $token = MagicLinkToken::factory()->consumed()->create();

    expect($token->isExpired())->toBeFalse()
        ->and($token->isConsumed())->toBeTrue()
        ->and($token->isUsable())->toBeFalse();
});
