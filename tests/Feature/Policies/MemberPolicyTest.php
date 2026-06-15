<?php

use App\Models\Member;
use App\Models\User;
use App\Policies\MemberPolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('MemberPolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view members', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', Member::class))->toBeTrue();
        });

        it('allows user with members.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('members.view');

            expect($user->can('viewAny', Member::class))->toBeTrue();
        });

        it('denies user without members.view permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', Member::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows owner to view a member', function () {
            $owner = User::factory()->owner()->create();
            $member = Member::factory()->create();

            expect($owner->can('view', $member))->toBeTrue();
        });

        it('allows user with members.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('members.view');
            $member = Member::factory()->create();

            expect($user->can('view', $member))->toBeTrue();
        });

        it('denies user without members.view permission', function () {
            $user = User::factory()->create();
            $member = Member::factory()->create();

            expect($user->can('view', $member))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows owner to create members', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('create', Member::class))->toBeTrue();
        });

        it('allows user with members.create permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('members.create');

            expect($user->can('create', Member::class))->toBeTrue();
        });

        it('denies user without members.create permission', function () {
            $user = User::factory()->create();

            expect($user->can('create', Member::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows owner to update a member', function () {
            $owner = User::factory()->owner()->create();
            $member = Member::factory()->create();

            expect($owner->can('update', $member))->toBeTrue();
        });

        it('allows user with members.edit permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('members.edit');
            $member = Member::factory()->create();

            expect($user->can('update', $member))->toBeTrue();
        });

        it('denies user without members.edit permission', function () {
            $user = User::factory()->create();
            $member = Member::factory()->create();

            expect($user->can('update', $member))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete a member', function () {
            $owner = User::factory()->owner()->create();
            $member = Member::factory()->create();

            expect($owner->can('delete', $member))->toBeTrue();
        });

        it('allows user with members.delete permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('members.delete');
            $member = Member::factory()->create();

            expect($user->can('delete', $member))->toBeTrue();
        });

        it('denies user without members.delete permission', function () {
            $user = User::factory()->create();
            $member = Member::factory()->create();

            expect($user->can('delete', $member))->toBeFalse();
        });
    });

    describe('managePermission', function () {
        it('returns the members.create permission', function () {
            $policy = new MemberPolicy;
            $method = (new ReflectionMethod($policy, 'managePermission'));

            expect($method->invoke($policy))->toBe('members.create');
        });
    });

    describe('seeded role matrix', function () {
        // Expected member abilities per seeded role (see RoleSeeder). Each entry
        // maps a role to the abilities it should be granted; everything else is
        // denied. Owner is excluded — it bypasses policies via the is_owner flag.
        $roleMatrix = [
            'Admin' => ['view' => true, 'create' => true, 'update' => true, 'delete' => true],
            'Operations Manager' => ['view' => true, 'create' => true, 'update' => true, 'delete' => true],
            'Sales' => ['view' => true, 'create' => true, 'update' => true, 'delete' => true],
            'Warehouse' => ['view' => true, 'create' => false, 'update' => false, 'delete' => false],
            'Read Only' => ['view' => true, 'create' => false, 'update' => false, 'delete' => false],
        ];

        $cases = [];
        foreach ($roleMatrix as $role => $abilities) {
            foreach ($abilities as $ability => $allowed) {
                $verb = $allowed ? 'allows' : 'denies';
                $cases["{$role} role {$verb} members.{$ability}"] = [$role, $ability, $allowed];
            }
        }

        it('grants the expected member abilities per seeded role', function (string $role, string $ability, bool $allowed) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $member = Member::factory()->create();

            $subject = $ability === 'create' ? Member::class : $member;

            expect($user->can($ability, $subject))->toBe($allowed);
        })->with($cases);
    });
});
