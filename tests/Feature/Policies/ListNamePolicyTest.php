<?php

use App\Models\ListName;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('ListNamePolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view list names', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', ListName::class))->toBeTrue();
        });

        it('allows user with list-values.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('list-values.view');

            expect($user->can('viewAny', ListName::class))->toBeTrue();
        });

        it('denies user without list-values.view permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', ListName::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows owner to view a list name', function () {
            $owner = User::factory()->owner()->create();
            $listName = ListName::factory()->create();

            expect($owner->can('view', $listName))->toBeTrue();
        });

        it('allows user with list-values.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('list-values.view');
            $listName = ListName::factory()->create();

            expect($user->can('view', $listName))->toBeTrue();
        });

        it('denies user without list-values.view permission', function () {
            $user = User::factory()->create();
            $listName = ListName::factory()->create();

            expect($user->can('view', $listName))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows owner to create list names', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('create', ListName::class))->toBeTrue();
        });

        it('allows user with list-values.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('list-values.manage');

            expect($user->can('create', ListName::class))->toBeTrue();
        });

        it('denies user without list-values.manage permission', function () {
            $user = User::factory()->create();

            expect($user->can('create', ListName::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows owner to update a list name', function () {
            $owner = User::factory()->owner()->create();
            $listName = ListName::factory()->create();

            expect($owner->can('update', $listName))->toBeTrue();
        });

        it('allows user with list-values.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('list-values.manage');
            $listName = ListName::factory()->create();

            expect($user->can('update', $listName))->toBeTrue();
        });

        it('denies user without list-values.manage permission', function () {
            $user = User::factory()->create();
            $listName = ListName::factory()->create();

            expect($user->can('update', $listName))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete a list name', function () {
            $owner = User::factory()->owner()->create();
            $listName = ListName::factory()->create();

            expect($owner->can('delete', $listName))->toBeTrue();
        });

        it('allows user with list-values.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('list-values.manage');
            $listName = ListName::factory()->create();

            expect($user->can('delete', $listName))->toBeTrue();
        });

        it('denies user without list-values.manage permission', function () {
            $user = User::factory()->create();
            $listName = ListName::factory()->create();

            expect($user->can('delete', $listName))->toBeFalse();
        });
    });
});
