<?php

use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Regression coverage for todo #82 — serialising activity participants must not
 * trigger an N+1 on participant members.
 */
beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;
});

it('eager-loads participant members without an N+1 (#82)', function () {
    $activity = Activity::factory()->create();
    Member::factory()->count(3)->create()->each(function (Member $member) use ($activity): void {
        ActivityParticipant::factory()->create([
            'activity_id' => $activity->id,
            'member_id' => $member->id,
        ]);
    });

    DB::enableQueryLog();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/activities/{$activity->id}?include=participants")
        ->assertOk();

    $memberQueries = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_contains($q['query'], 'from "members"'))
        ->count();

    DB::disableQueryLog();

    // One batched query for all participant members, not one per participant.
    expect($memberQueries)->toBeLessThanOrEqual(1);

    expect($response->json('activity.participants'))->toHaveCount(3);
    expect($response->json('activity.participants.0.member_name'))->not->toBeEmpty();
});
