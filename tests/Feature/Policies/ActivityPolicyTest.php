<?php

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('ActivityPolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view activities', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', Activity::class))->toBeTrue();
        });

        it('allows user with activities.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('activities.view');

            expect($user->can('viewAny', Activity::class))->toBeTrue();
        });

        it('denies user without activities.view permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', Activity::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows owner to view an activity', function () {
            $owner = User::factory()->owner()->create();
            $activity = Activity::factory()->create();

            expect($owner->can('view', $activity))->toBeTrue();
        });

        it('allows user with activities.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('activities.view');
            $activity = Activity::factory()->create();

            expect($user->can('view', $activity))->toBeTrue();
        });

        it('denies user without activities.view permission', function () {
            $user = User::factory()->create();
            $activity = Activity::factory()->create();

            expect($user->can('view', $activity))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows owner to create activities', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('create', Activity::class))->toBeTrue();
        });

        it('allows user with activities.create permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('activities.create');

            expect($user->can('create', Activity::class))->toBeTrue();
        });

        it('denies user without activities.create permission', function () {
            $user = User::factory()->create();

            expect($user->can('create', Activity::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows owner to update an activity', function () {
            $owner = User::factory()->owner()->create();
            $activity = Activity::factory()->create();

            expect($owner->can('update', $activity))->toBeTrue();
        });

        it('allows user with activities.edit permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('activities.edit');
            $activity = Activity::factory()->create();

            expect($user->can('update', $activity))->toBeTrue();
        });

        it('denies user without activities.edit permission', function () {
            $user = User::factory()->create();
            $activity = Activity::factory()->create();

            expect($user->can('update', $activity))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete an activity', function () {
            $owner = User::factory()->owner()->create();
            $activity = Activity::factory()->create();

            expect($owner->can('delete', $activity))->toBeTrue();
        });

        it('allows user with activities.delete permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('activities.delete');
            $activity = Activity::factory()->create();

            expect($user->can('delete', $activity))->toBeTrue();
        });

        it('denies user without activities.delete permission', function () {
            $user = User::factory()->create();
            $activity = Activity::factory()->create();

            expect($user->can('delete', $activity))->toBeFalse();
        });
    });

    describe('complete', function () {
        it('allows owner to complete an activity', function () {
            $owner = User::factory()->owner()->create();
            $activity = Activity::factory()->create();

            expect($owner->can('complete', $activity))->toBeTrue();
        });

        it('allows user with activities.complete permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('activities.complete');
            $activity = Activity::factory()->create();

            expect($user->can('complete', $activity))->toBeTrue();
        });

        it('denies user without activities.complete permission', function () {
            $user = User::factory()->create();
            $activity = Activity::factory()->create();

            expect($user->can('complete', $activity))->toBeFalse();
        });
    });
});
