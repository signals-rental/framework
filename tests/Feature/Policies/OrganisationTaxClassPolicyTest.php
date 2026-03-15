<?php

use App\Models\OrganisationTaxClass;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('OrganisationTaxClassPolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view organisation tax classes', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', OrganisationTaxClass::class))->toBeTrue();
        });

        it('allows user with tax-classes.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('tax-classes.view');

            expect($user->can('viewAny', OrganisationTaxClass::class))->toBeTrue();
        });

        it('denies user without tax-classes.view permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', OrganisationTaxClass::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows owner to view an organisation tax class', function () {
            $owner = User::factory()->owner()->create();
            $taxClass = OrganisationTaxClass::factory()->create();

            expect($owner->can('view', $taxClass))->toBeTrue();
        });

        it('allows user with tax-classes.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('tax-classes.view');
            $taxClass = OrganisationTaxClass::factory()->create();

            expect($user->can('view', $taxClass))->toBeTrue();
        });

        it('denies user without tax-classes.view permission', function () {
            $user = User::factory()->create();
            $taxClass = OrganisationTaxClass::factory()->create();

            expect($user->can('view', $taxClass))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows owner to create organisation tax classes', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('create', OrganisationTaxClass::class))->toBeTrue();
        });

        it('allows user with tax-classes.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('tax-classes.manage');

            expect($user->can('create', OrganisationTaxClass::class))->toBeTrue();
        });

        it('denies user without tax-classes.manage permission', function () {
            $user = User::factory()->create();

            expect($user->can('create', OrganisationTaxClass::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows owner to update an organisation tax class', function () {
            $owner = User::factory()->owner()->create();
            $taxClass = OrganisationTaxClass::factory()->create();

            expect($owner->can('update', $taxClass))->toBeTrue();
        });

        it('allows user with tax-classes.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('tax-classes.manage');
            $taxClass = OrganisationTaxClass::factory()->create();

            expect($user->can('update', $taxClass))->toBeTrue();
        });

        it('denies user without tax-classes.manage permission', function () {
            $user = User::factory()->create();
            $taxClass = OrganisationTaxClass::factory()->create();

            expect($user->can('update', $taxClass))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete an organisation tax class', function () {
            $owner = User::factory()->owner()->create();
            $taxClass = OrganisationTaxClass::factory()->create();

            expect($owner->can('delete', $taxClass))->toBeTrue();
        });

        it('allows user with tax-classes.manage permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('tax-classes.manage');
            $taxClass = OrganisationTaxClass::factory()->create();

            expect($user->can('delete', $taxClass))->toBeTrue();
        });

        it('denies user without tax-classes.manage permission', function () {
            $user = User::factory()->create();
            $taxClass = OrganisationTaxClass::factory()->create();

            expect($user->can('delete', $taxClass))->toBeFalse();
        });
    });
});
