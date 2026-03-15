<?php

use App\Models\Member;
use App\Models\Membership;
use App\Models\Store;
use App\Models\User;

it('returns null for owners (unrestricted access)', function () {
    $user = User::factory()->owner()->create();

    expect($user->accessibleStoreIds())->toBeNull();
});

it('returns null for admins (unrestricted access)', function () {
    $user = User::factory()->admin()->create();

    expect($user->accessibleStoreIds())->toBeNull();
});

it('returns empty array for users without a member', function () {
    $user = User::factory()->create(['member_id' => null]);

    expect($user->accessibleStoreIds())->toBe([]);
});

it('returns store IDs from member memberships', function () {
    $member = Member::factory()->create();
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();

    Membership::factory()->create(['member_id' => $member->id, 'store_id' => $storeA->id]);
    Membership::factory()->create(['member_id' => $member->id, 'store_id' => $storeB->id]);

    $user = User::factory()->create(['member_id' => $member->id]);

    $result = $user->accessibleStoreIds();

    expect($result)->toBeArray()
        ->and($result)->toContain($storeA->id)
        ->and($result)->toContain($storeB->id)
        ->and($result)->toHaveCount(2);
});

it('returns null when a membership has null store_id (all stores)', function () {
    $member = Member::factory()->create();
    Membership::factory()->create(['member_id' => $member->id, 'store_id' => null]);

    $user = User::factory()->create(['member_id' => $member->id]);

    expect($user->accessibleStoreIds())->toBeNull();
});
