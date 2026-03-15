<?php

use App\Models\CustomFieldGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('CustomFieldGroupPolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view custom field groups', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', CustomFieldGroup::class))->toBeTrue();
        });

        it('allows user with custom-fields.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('custom-fields.view');

            expect($user->can('viewAny', CustomFieldGroup::class))->toBeTrue();
        });

        it('denies user without custom-fields.view permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', CustomFieldGroup::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows owner to view a custom field group', function () {
            $owner = User::factory()->owner()->create();
            $group = CustomFieldGroup::factory()->create();

            expect($owner->can('view', $group))->toBeTrue();
        });

        it('allows user with custom-fields.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('custom-fields.view');
            $group = CustomFieldGroup::factory()->create();

            expect($user->can('view', $group))->toBeTrue();
        });

        it('denies user without custom-fields.view permission', function () {
            $user = User::factory()->create();
            $group = CustomFieldGroup::factory()->create();

            expect($user->can('view', $group))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows owner to create custom field groups', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('create', CustomFieldGroup::class))->toBeTrue();
        });

        it('allows user with custom-fields.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('custom-fields.manage');

            expect($user->can('create', CustomFieldGroup::class))->toBeTrue();
        });

        it('denies user without custom-fields.manage permission', function () {
            $user = User::factory()->create();

            expect($user->can('create', CustomFieldGroup::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows owner to update a custom field group', function () {
            $owner = User::factory()->owner()->create();
            $group = CustomFieldGroup::factory()->create();

            expect($owner->can('update', $group))->toBeTrue();
        });

        it('allows user with custom-fields.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('custom-fields.manage');
            $group = CustomFieldGroup::factory()->create();

            expect($user->can('update', $group))->toBeTrue();
        });

        it('denies user without custom-fields.manage permission', function () {
            $user = User::factory()->create();
            $group = CustomFieldGroup::factory()->create();

            expect($user->can('update', $group))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete a custom field group', function () {
            $owner = User::factory()->owner()->create();
            $group = CustomFieldGroup::factory()->create();

            expect($owner->can('delete', $group))->toBeTrue();
        });

        it('allows user with custom-fields.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('custom-fields.manage');
            $group = CustomFieldGroup::factory()->create();

            expect($user->can('delete', $group))->toBeTrue();
        });

        it('denies user without custom-fields.manage permission', function () {
            $user = User::factory()->create();
            $group = CustomFieldGroup::factory()->create();

            expect($user->can('delete', $group))->toBeFalse();
        });
    });
});
