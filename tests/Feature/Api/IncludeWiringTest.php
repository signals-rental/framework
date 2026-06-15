<?php

use App\Models\Country;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\StockLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Regression coverage for todo #78 — includes that were whitelisted in
 * $allowedIncludes but never serialized by the response DTO. Each include must
 * now actually surface data.
 */
beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->token = $this->owner->createToken('test', ['products:read', 'stock:read', 'rates:read'])->plainTextToken;
});

it('includes country_of_origin on a product', function () {
    $country = Country::factory()->create(['name' => 'Germany']);
    $product = Product::factory()->create(['country_of_origin_id' => $country->id]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/products/{$product->id}?include=countryOfOrigin")
        ->assertOk()
        ->assertJsonPath('product.country_of_origin.id', $country->id)
        ->assertJsonPath('product.country_of_origin.name', 'Germany');
});

it('includes stock_levels on a product', function () {
    $product = Product::factory()->create();
    $stock = StockLevel::factory()->create(['product_id' => $product->id]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/products/{$product->id}?include=stockLevels")
        ->assertOk()
        ->assertJsonPath('product.stock_levels.0.id', $stock->id);
});

it('includes children on a product group', function () {
    $parent = ProductGroup::factory()->create();
    $child = ProductGroup::factory()->create(['parent_id' => $parent->id, 'name' => 'Child Group']);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/product_groups/{$parent->id}?include=children")
        ->assertOk()
        ->assertJsonPath('product_group.children.0.id', $child->id)
        ->assertJsonPath('product_group.children.0.name', 'Child Group');
});

it('includes cloned_from on a rate definition', function () {
    $source = RateDefinition::factory()->create(['name' => 'Source Rate']);
    $clone = RateDefinition::factory()->create(['cloned_from_id' => $source->id]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/rate_definitions/{$clone->id}?include=clonedFrom")
        ->assertOk()
        ->assertJsonPath('rate_definition.cloned_from.id', $source->id)
        ->assertJsonPath('rate_definition.cloned_from.name', 'Source Rate');
});

it('includes product_rates on a rate definition', function () {
    $definition = RateDefinition::factory()->create();
    $product = Product::factory()->create();
    $rate = ProductRate::factory()->create([
        'rate_definition_id' => $definition->id,
        'product_id' => $product->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/rate_definitions/{$definition->id}?include=productRates")
        ->assertOk()
        ->assertJsonPath('rate_definition.product_rates.0.id', $rate->id);
});

it('includes rates on a product', function () {
    $product = Product::factory()->create();
    $rate = ProductRate::factory()->create(['product_id' => $product->id]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/products/{$product->id}?include=rates")
        ->assertOk()
        ->assertJsonPath('product.rates.0.id', $rate->id);
});

it('omits rates on a product when not requested', function () {
    $product = Product::factory()->create();
    ProductRate::factory()->create(['product_id' => $product->id]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('product.rates', null);
});

it('includes member on a stock level', function () {
    $member = Member::factory()->create(['name' => 'Acme Hire']);
    $product = Product::factory()->create();
    $stock = StockLevel::factory()->create(['product_id' => $product->id, 'member_id' => $member->id]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/stock_levels/{$stock->id}?include=member")
        ->assertOk()
        ->assertJsonPath('stock_level.member.id', $member->id)
        ->assertJsonPath('stock_level.member.name', 'Acme Hire');
});
