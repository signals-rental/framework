<?php

use App\Models\Opportunity;
use App\Models\User;
use App\Policies\OpportunityPolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/**
 * OpportunityPolicy is registered (auto-discovered) for future model-based
 * Gate::authorize('update', $opportunity) checks. Today the action classes use
 * string-based gates, so this test anchors the policy's permission mapping so it
 * cannot silently drift out of sync with the real permission names.
 */
beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('OpportunityPolicy', function () {
    it('grants every ability to an owner', function () {
        $owner = User::factory()->owner()->create();
        $opportunity = Opportunity::factory()->create();

        expect($owner->can('viewAny', Opportunity::class))->toBeTrue()
            ->and($owner->can('view', $opportunity))->toBeTrue()
            ->and($owner->can('create', Opportunity::class))->toBeTrue()
            ->and($owner->can('update', $opportunity))->toBeTrue()
            ->and($owner->can('delete', $opportunity))->toBeTrue();
    });

    it('maps each ability to the correct opportunities.* permission', function () {
        $opportunity = Opportunity::factory()->create();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('opportunities.view');
        $creator = User::factory()->create();
        $creator->givePermissionTo('opportunities.create');
        $editor = User::factory()->create();
        $editor->givePermissionTo('opportunities.edit');
        $deleter = User::factory()->create();
        $deleter->givePermissionTo('opportunities.delete');

        expect($viewer->can('view', $opportunity))->toBeTrue()
            ->and($creator->can('create', Opportunity::class))->toBeTrue()
            ->and($editor->can('update', $opportunity))->toBeTrue()
            ->and($deleter->can('delete', $opportunity))->toBeTrue();
    });

    it('denies a user without the corresponding permission', function () {
        $user = User::factory()->create();
        $opportunity = Opportunity::factory()->create();

        expect($user->can('view', $opportunity))->toBeFalse()
            ->and($user->can('create', Opportunity::class))->toBeFalse()
            ->and($user->can('update', $opportunity))->toBeFalse()
            ->and($user->can('delete', $opportunity))->toBeFalse();
    });

    it('uses opportunities.create as the manage permission', function () {
        $policy = new OpportunityPolicy;
        $method = new ReflectionMethod($policy, 'managePermission');

        expect($method->invoke($policy))->toBe('opportunities.create');
    });
});
