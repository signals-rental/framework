<?php

use App\Models\CustomField;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('CustomFieldPolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view custom fields', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', CustomField::class))->toBeTrue();
        });

        it('allows user with custom-fields.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('custom-fields.view');

            expect($user->can('viewAny', CustomField::class))->toBeTrue();
        });

        it('denies user without custom-fields.view permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', CustomField::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows owner to view a custom field', function () {
            $owner = User::factory()->owner()->create();
            $customField = CustomField::factory()->create();

            expect($owner->can('view', $customField))->toBeTrue();
        });

        it('allows user with custom-fields.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('custom-fields.view');
            $customField = CustomField::factory()->create();

            expect($user->can('view', $customField))->toBeTrue();
        });

        it('denies user without custom-fields.view permission', function () {
            $user = User::factory()->create();
            $customField = CustomField::factory()->create();

            expect($user->can('view', $customField))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows owner to create custom fields', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('create', CustomField::class))->toBeTrue();
        });

        it('allows user with custom-fields.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('custom-fields.manage');

            expect($user->can('create', CustomField::class))->toBeTrue();
        });

        it('denies user without custom-fields.manage permission', function () {
            $user = User::factory()->create();

            expect($user->can('create', CustomField::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows owner to update a custom field', function () {
            $owner = User::factory()->owner()->create();
            $customField = CustomField::factory()->create();

            expect($owner->can('update', $customField))->toBeTrue();
        });

        it('allows user with custom-fields.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('custom-fields.manage');
            $customField = CustomField::factory()->create();

            expect($user->can('update', $customField))->toBeTrue();
        });

        it('denies user without custom-fields.manage permission', function () {
            $user = User::factory()->create();
            $customField = CustomField::factory()->create();

            expect($user->can('update', $customField))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete a custom field', function () {
            $owner = User::factory()->owner()->create();
            $customField = CustomField::factory()->create();

            expect($owner->can('delete', $customField))->toBeTrue();
        });

        it('allows user with custom-fields.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('custom-fields.manage');
            $customField = CustomField::factory()->create();

            expect($user->can('delete', $customField))->toBeTrue();
        });

        it('denies user without custom-fields.manage permission', function () {
            $user = User::factory()->create();
            $customField = CustomField::factory()->create();

            expect($user->can('delete', $customField))->toBeFalse();
        });
    });
});
