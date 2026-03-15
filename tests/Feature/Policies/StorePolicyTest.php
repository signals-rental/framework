<?php

use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('StorePolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view stores', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', Store::class))->toBeTrue();
        });

        it('allows user with settings.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('settings.view');

            expect($user->can('viewAny', Store::class))->toBeTrue();
        });

        it('denies user without settings.view permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', Store::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows owner to view a store', function () {
            $owner = User::factory()->owner()->create();
            $store = Store::factory()->create();

            expect($owner->can('view', $store))->toBeTrue();
        });

        it('allows user with settings.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('settings.view');
            $store = Store::factory()->create();

            expect($user->can('view', $store))->toBeTrue();
        });

        it('denies user without settings.view permission', function () {
            $user = User::factory()->create();
            $store = Store::factory()->create();

            expect($user->can('view', $store))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows owner to create stores', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('create', Store::class))->toBeTrue();
        });

        it('allows user with settings.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('settings.manage');

            expect($user->can('create', Store::class))->toBeTrue();
        });

        it('denies user without settings.manage permission', function () {
            $user = User::factory()->create();

            expect($user->can('create', Store::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows owner to update a store', function () {
            $owner = User::factory()->owner()->create();
            $store = Store::factory()->create();

            expect($owner->can('update', $store))->toBeTrue();
        });

        it('allows user with settings.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('settings.manage');
            $store = Store::factory()->create();

            expect($user->can('update', $store))->toBeTrue();
        });

        it('denies user without settings.manage permission', function () {
            $user = User::factory()->create();
            $store = Store::factory()->create();

            expect($user->can('update', $store))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete a store', function () {
            $owner = User::factory()->owner()->create();
            $store = Store::factory()->create();

            expect($owner->can('delete', $store))->toBeTrue();
        });

        it('allows user with settings.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('settings.manage');
            $store = Store::factory()->create();

            expect($user->can('delete', $store))->toBeTrue();
        });

        it('denies user without settings.manage permission', function () {
            $user = User::factory()->create();
            $store = Store::factory()->create();

            expect($user->can('delete', $store))->toBeFalse();
        });
    });
});
