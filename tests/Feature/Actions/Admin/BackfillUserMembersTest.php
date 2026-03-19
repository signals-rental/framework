<?php

use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\User;

it('creates member records for users without one', function () {
    // Create users without members (bypass factory afterCreating)
    $user1 = User::factory()->makeOne(['name' => 'User One']);
    $user1->saveQuietly();
    $user1->update(['member_id' => null]);

    $user2 = User::factory()->makeOne(['name' => 'User Two']);
    $user2->saveQuietly();
    $user2->update(['member_id' => null]);

    $this->artisan('signals:backfill-user-members')
        ->expectsOutputToContain('Found 2 user(s) without member records')
        ->expectsOutputToContain('Done. Created 2 member record(s)')
        ->assertExitCode(0);

    $user1->refresh();
    $user2->refresh();

    expect($user1->member_id)->not->toBeNull()
        ->and($user2->member_id)->not->toBeNull();

    $member = Member::find($user1->member_id);
    expect($member->name)->toBe('User One')
        ->and($member->membership_type)->toBe(MembershipType::User)
        ->and($member->is_active)->toBeTrue();
});

it('skips users that already have member records', function () {
    User::factory()->create(['name' => 'Already Linked']);
    $memberCountBefore = Member::count();

    $this->artisan('signals:backfill-user-members')
        ->expectsOutputToContain('All users already have linked member records')
        ->assertExitCode(0);

    expect(Member::count())->toBe($memberCountBefore);
});

it('sets is_active on member to match user is_active', function () {
    $user = User::factory()->deactivated()->makeOne(['name' => 'Inactive User']);
    $user->saveQuietly();
    $user->update(['member_id' => null]);

    $this->artisan('signals:backfill-user-members')
        ->assertExitCode(0);

    $user->refresh();
    $member = Member::find($user->member_id);
    expect($member->is_active)->toBeFalse();
});
