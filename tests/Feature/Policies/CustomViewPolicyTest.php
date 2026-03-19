<?php

use App\Models\CustomView;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('CustomViewPolicy', function () {
    describe('viewAny', function () {
        it('allows any authenticated user', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', CustomView::class))->toBeTrue();
        });
    });

    describe('view', function () {
        it('allows any authenticated user', function () {
            $user = User::factory()->create();
            $view = CustomView::factory()->create();

            expect($user->can('view', $view))->toBeTrue();
        });
    });

    describe('create', function () {
        it('allows any authenticated user', function () {
            $user = User::factory()->create();

            expect($user->can('create', CustomView::class))->toBeTrue();
        });
    });

    describe('update', function () {
        it('allows owner of a personal view to update it', function () {
            $user = User::factory()->create();
            $view = CustomView::factory()->create([
                'visibility' => 'personal',
                'user_id' => $user->id,
            ]);

            expect($user->can('update', $view))->toBeTrue();
        });

        it('denies non-owner from updating a personal view', function () {
            $owner = User::factory()->create();
            $other = User::factory()->create();
            $view = CustomView::factory()->create([
                'visibility' => 'personal',
                'user_id' => $owner->id,
            ]);

            expect($other->can('update', $view))->toBeFalse();
        });

        it('allows user with settings.manage to update a shared view', function () {
            $admin = User::factory()->create();
            $admin->assignRole('Admin');
            $view = CustomView::factory()->shared()->create();

            expect($admin->can('update', $view))->toBeTrue();
        });

        it('denies user without settings.manage from updating a shared view', function () {
            $user = User::factory()->create();
            $view = CustomView::factory()->shared()->create();

            expect($user->can('update', $view))->toBeFalse();
        });

        it('allows user with settings.manage to update a system view', function () {
            $admin = User::factory()->create();
            $admin->assignRole('Admin');
            $view = CustomView::factory()->system()->create();

            expect($admin->can('update', $view))->toBeTrue();
        });
    });

    describe('delete', function () {
        it('denies deletion of system views even for admin with settings.manage', function () {
            $admin = User::factory()->create();
            $admin->assignRole('Admin');
            $view = CustomView::factory()->system()->create();

            expect($admin->can('delete', $view))->toBeFalse();
        });

        it('allows owner of a personal view to delete it', function () {
            $user = User::factory()->create();
            $view = CustomView::factory()->create([
                'visibility' => 'personal',
                'user_id' => $user->id,
            ]);

            expect($user->can('delete', $view))->toBeTrue();
        });

        it('denies non-owner from deleting a personal view', function () {
            $owner = User::factory()->create();
            $other = User::factory()->create();
            $view = CustomView::factory()->create([
                'visibility' => 'personal',
                'user_id' => $owner->id,
            ]);

            expect($other->can('delete', $view))->toBeFalse();
        });

        it('allows user with settings.manage to delete a shared view', function () {
            $admin = User::factory()->create();
            $admin->assignRole('Admin');
            $view = CustomView::factory()->shared()->create();

            expect($admin->can('delete', $view))->toBeTrue();
        });

        it('denies user without settings.manage from deleting a shared view', function () {
            $user = User::factory()->create();
            $view = CustomView::factory()->shared()->create();

            expect($user->can('delete', $view))->toBeFalse();
        });
    });
});
