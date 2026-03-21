<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('ProductPolicy', function () {
    describe('viewAny', function () {
        it('allows owner to view products', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('viewAny', Product::class))->toBeTrue();
        });

        it('allows user with products.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('products.view');

            expect($user->can('viewAny', Product::class))->toBeTrue();
        });

        it('denies user without products.view permission', function () {
            $user = User::factory()->create();

            expect($user->can('viewAny', Product::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows owner to view a product', function () {
            $owner = User::factory()->owner()->create();
            $product = Product::factory()->create();

            expect($owner->can('view', $product))->toBeTrue();
        });

        it('allows user with products.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('products.view');
            $product = Product::factory()->create();

            expect($user->can('view', $product))->toBeTrue();
        });

        it('denies user without products.view permission', function () {
            $user = User::factory()->create();
            $product = Product::factory()->create();

            expect($user->can('view', $product))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows owner to create products', function () {
            $owner = User::factory()->owner()->create();

            expect($owner->can('create', Product::class))->toBeTrue();
        });

        it('allows user with products.create permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('products.create');

            expect($user->can('create', Product::class))->toBeTrue();
        });

        it('denies user without products.create permission', function () {
            $user = User::factory()->create();

            expect($user->can('create', Product::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows owner to update a product', function () {
            $owner = User::factory()->owner()->create();
            $product = Product::factory()->create();

            expect($owner->can('update', $product))->toBeTrue();
        });

        it('allows user with products.edit permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('products.edit');
            $product = Product::factory()->create();

            expect($user->can('update', $product))->toBeTrue();
        });

        it('denies user without products.edit permission', function () {
            $user = User::factory()->create();
            $product = Product::factory()->create();

            expect($user->can('update', $product))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete a product', function () {
            $owner = User::factory()->owner()->create();
            $product = Product::factory()->create();

            expect($owner->can('delete', $product))->toBeTrue();
        });

        it('allows user with products.delete permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('products.delete');
            $product = Product::factory()->create();

            expect($user->can('delete', $product))->toBeTrue();
        });

        it('denies user without products.delete permission', function () {
            $user = User::factory()->create();
            $product = Product::factory()->create();

            expect($user->can('delete', $product))->toBeFalse();
        });
    });
});
