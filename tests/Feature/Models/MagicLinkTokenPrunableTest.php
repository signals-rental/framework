<?php

use App\Models\MagicLinkToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Prunable;

// The model:prune command instantiates models without their dependencies, so the
// scheduled prune of MagicLinkToken is exercised directly through the command.

it('prunes consumed tokens', function () {
    $user = User::factory()->create();
    $consumed = MagicLinkToken::factory()->for($user)->consumed()->create();

    $this->artisan('model:prune', ['--model' => [MagicLinkToken::class]])
        ->assertSuccessful();

    expect(MagicLinkToken::query()->whereKey($consumed->id)->exists())->toBeFalse();
});

it('prunes tokens expired more than 30 days ago', function () {
    $user = User::factory()->create();
    $longExpired = MagicLinkToken::factory()->for($user)->create([
        'expires_at' => now()->subDays(31),
    ]);

    $this->artisan('model:prune', ['--model' => [MagicLinkToken::class]])
        ->assertSuccessful();

    expect(MagicLinkToken::query()->whereKey($longExpired->id)->exists())->toBeFalse();
});

it('keeps fresh, unconsumed tokens', function () {
    $user = User::factory()->create();
    $fresh = MagicLinkToken::factory()->for($user)->create();

    $this->artisan('model:prune', ['--model' => [MagicLinkToken::class]])
        ->assertSuccessful();

    expect(MagicLinkToken::query()->whereKey($fresh->id)->exists())->toBeTrue();
});

it('keeps recently-expired, unconsumed tokens within the 30-day grace window', function () {
    $user = User::factory()->create();
    // Expired (so no longer usable) but inside the 30-day grace window.
    $recentlyExpired = MagicLinkToken::factory()->for($user)->create([
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('model:prune', ['--model' => [MagicLinkToken::class]])
        ->assertSuccessful();

    expect(MagicLinkToken::query()->whereKey($recentlyExpired->id)->exists())->toBeTrue();
});

it('reports the model as prunable', function () {
    expect(class_uses_recursive(MagicLinkToken::class))
        ->toContain(Prunable::class);
});
