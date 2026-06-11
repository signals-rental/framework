<?php

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ViewSeeder;

/**
 * Regression coverage for the Ransack whitelist bypass (todo #77).
 *
 * When a list endpoint resolves a custom view (including the seeded SYSTEM
 * DEFAULT view), explicit `q` filters previously routed through
 * ViewResolver::applyFilters, which derived its allowed-field list from the
 * caller's own input — bypassing the controller's $allowedFilters whitelist.
 * That caused 500s on non-existent columns and arbitrary filtering on
 * non-whitelisted real columns. These tests exercise that view-active path.
 */
beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ViewSeeder::class); // creates the "All Products" system default view
    $this->owner = User::factory()->owner()->create();
    $this->token = $this->owner->createToken('test', ['products:read'])->plainTextToken;
});

it('ignores a filter on a non-existent column instead of returning 500 (view path)', function () {
    Product::factory()->count(3)->create();

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?q[zzz_bogus_eq]=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 3);
});

it('does not allow filtering on a non-whitelisted real column (view path)', function () {
    Product::factory()->create(['description' => 'SECRET-ALPHA']);
    Product::factory()->create(['description' => 'ordinary item']);

    // `description` is a real column but is NOT in ProductController::$allowedFilters,
    // so it must be ignored — both products should still be returned.
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?q[description_cont]=SECRET')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('still applies a whitelisted explicit filter through the view path', function () {
    Product::factory()->rental()->create();
    Product::factory()->sale()->create();

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?q[product_type_eq]=sale')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('translates the response-facing type filter alias to product_type (#80)', function () {
    Product::factory()->rental()->create();
    Product::factory()->sale()->create();

    // `type` is the response output key; the column is `product_type`.
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?q[type_eq]=sale')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('translates the type sort alias to product_type (#80)', function () {
    Product::factory()->sale()->create(['name' => 'Sale Item']);
    Product::factory()->rental()->create(['name' => 'Rental Item']);

    // sort=type -> product_type ascending: 'rental' sorts before 'sale'.
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?sort=type')
        ->assertOk()
        ->assertJsonPath('products.0.type', 'Rental');
});

it('translates the active filter alias to is_active without erroring (#80)', function () {
    Product::factory()->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);

    // `active` is the response output key; the column is `is_active`.
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?q[active_false]=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('filters by a whitelisted relation column productGroup.name (#81)', function () {
    $lighting = ProductGroup::factory()->create(['name' => 'Lighting']);
    $audio = ProductGroup::factory()->create(['name' => 'Audio']);
    Product::factory()->create(['product_group_id' => $lighting->id]);
    Product::factory()->create(['product_group_id' => $audio->id]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?q[productGroup.name_eq]=Lighting')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('ignores a relation filter on a non-whitelisted relation column (#81)', function () {
    $group = ProductGroup::factory()->create();
    Product::factory()->count(2)->create(['product_group_id' => $group->id]);

    // productGroup.description is not in the relation whitelist -> ignored, not 500.
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?q[productGroup.description_cont]=x')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});
