<?php

use App\Models\StockLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('StockLevelPolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view stock levels', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', StockLevel::class))->toBeTrue();
        });

        it('allows user with stock.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('stock.view');

            expect($user->can('viewAny', StockLevel::class))->toBeTrue();
        });

        it('denies user without stock.view permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', StockLevel::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows owner to view a stock level', function () {
            $owner = User::factory()->owner()->create();
            $stockLevel = StockLevel::factory()->create();

            expect($owner->can('view', $stockLevel))->toBeTrue();
        });

        it('allows user with stock.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('stock.view');
            $stockLevel = StockLevel::factory()->create();

            expect($user->can('view', $stockLevel))->toBeTrue();
        });

        it('denies user without stock.view permission', function () {
            $user = User::factory()->create();
            $stockLevel = StockLevel::factory()->create();

            expect($user->can('view', $stockLevel))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows owner to create stock levels', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('create', StockLevel::class))->toBeTrue();
        });

        it('allows user with stock.adjust permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('stock.adjust');

            expect($user->can('create', StockLevel::class))->toBeTrue();
        });

        it('denies user without stock.adjust permission', function () {
            $user = User::factory()->create();

            expect($user->can('create', StockLevel::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows owner to update a stock level', function () {
            $owner = User::factory()->owner()->create();
            $stockLevel = StockLevel::factory()->create();

            expect($owner->can('update', $stockLevel))->toBeTrue();
        });

        it('allows user with stock.adjust permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('stock.adjust');
            $stockLevel = StockLevel::factory()->create();

            expect($user->can('update', $stockLevel))->toBeTrue();
        });

        it('denies user without stock.adjust permission', function () {
            $user = User::factory()->create();
            $stockLevel = StockLevel::factory()->create();

            expect($user->can('update', $stockLevel))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete a stock level', function () {
            $owner = User::factory()->owner()->create();
            $stockLevel = StockLevel::factory()->create();

            expect($owner->can('delete', $stockLevel))->toBeTrue();
        });

        it('allows user with stock.adjust permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('stock.adjust');
            $stockLevel = StockLevel::factory()->create();

            expect($user->can('delete', $stockLevel))->toBeTrue();
        });

        it('denies user without stock.adjust permission', function () {
            $user = User::factory()->create();
            $stockLevel = StockLevel::factory()->create();

            expect($user->can('delete', $stockLevel))->toBeFalse();
        });
    });
});
