<?php

use App\Models\Activity;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\StockLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Regression coverage for the 2026-06-15 UAT query-layer defect cluster:
 *  1. ?include= using CRMS-aligned snake_case names was a no-op.
 *  2. Enum-backed filters were case-sensitive.
 *  3. rate_definitions strategy/preset filter aliases were silently ignored.
 *  4. Product sort was not applied.
 */
beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->token = $this->owner->createToken('test', [
        'products:read', 'stock:read', 'rates:read', 'activities:read',
    ])->plainTextToken;
});

// --- Defect 1: ?include= using CRMS snake_case names ---

it('includes stock_levels on a product via snake_case include', function () {
    $product = Product::factory()->create();
    $stock = StockLevel::factory()->create(['product_id' => $product->id]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/products/{$product->id}?include=stock_levels")
        ->assertOk()
        ->assertJsonPath('product.stock_levels.0.id', $stock->id);
});

it('resolves snake_case includes alongside an extra include without erroring', function () {
    $product = Product::factory()->create();
    $stock = StockLevel::factory()->create(['product_id' => $product->id]);
    $definition = RateDefinition::factory()->create();
    ProductRate::factory()->create([
        'product_id' => $product->id,
        'rate_definition_id' => $definition->id,
    ]);

    // `rates` is whitelisted/eager-loaded; `stock_levels` resolves to the
    // stockLevels relation. The response must still serialise stock_levels.
    // (`rates` serialisation requires a ProductData property — tracked separately.)
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/products/{$product->id}?include=rates,stock_levels")
        ->assertOk()
        ->assertJsonPath('product.stock_levels.0.id', $stock->id);
});

it('includes regarding and owner on an activity via include', function () {
    $member = Member::factory()->create(['name' => 'Acme Hire']);
    $activity = Activity::factory()->create([
        'owned_by' => $this->owner->id,
        'regarding_type' => Member::class,
        'regarding_id' => $member->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/activities/{$activity->id}?include=regarding,owner")
        ->assertOk()
        ->assertJsonPath('activity.regarding.id', $member->id)
        ->assertJsonPath('activity.regarding.name', 'Acme Hire')
        ->assertJsonPath('activity.owner.id', $this->owner->id);
});

// --- Defect 2: enum filter case-insensitivity ---

it('filters products by enum value case-insensitively', function () {
    Product::factory()->count(3)->create(['product_type' => 'rental']);
    Product::factory()->count(2)->create(['product_type' => 'sale']);

    $lower = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?q[type_eq]=rental')
        ->assertOk()
        ->json('meta.total');

    $titlecase = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?q[type_eq]=Rental')
        ->assertOk()
        ->json('meta.total');

    expect($lower)->toBe(3)
        ->and($titlecase)->toBe(3);
});

// --- Defect 3: rate_definitions strategy/preset filter aliases ---

it('filters rate definitions by strategy alias', function () {
    RateDefinition::factory()->count(2)->create(['calculation_strategy' => 'fixed', 'base_period' => null]);
    RateDefinition::factory()->count(3)->create(['calculation_strategy' => 'period']);

    $total = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/rate_definitions?q[strategy_eq]=fixed')
        ->assertOk()
        ->json('meta.total');

    expect($total)->toBe(2);
});

it('filters rate definitions by preset alias', function () {
    $target = RateDefinition::factory()->create(['preset_slug' => 'daily-flat']);
    RateDefinition::factory()->create(['preset_slug' => 'weekly-tiered']);
    RateDefinition::factory()->create(['preset_slug' => 'monthly-hybrid']);
    RateDefinition::factory()->count(2)->create(['preset_slug' => null]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/rate_definitions?q[preset_eq]=daily-flat')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('rate_definitions.0.id'))->toBe($target->id);
});

// --- Defect 4: product sort ---

it('sorts products by name descending via sort param', function () {
    Product::factory()->create(['name' => 'Alpha']);
    Product::factory()->create(['name' => 'Zulu']);
    Product::factory()->create(['name' => 'Mike']);

    $names = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?sort=-name')
        ->assertOk()
        ->json('products.*.name');

    expect($names)->toBe(['Zulu', 'Mike', 'Alpha']);
});

it('sorts products by name descending via Ransack q[s] param', function () {
    Product::factory()->create(['name' => 'Alpha']);
    Product::factory()->create(['name' => 'Zulu']);
    Product::factory()->create(['name' => 'Mike']);

    $names = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?q[s]=name desc')
        ->assertOk()
        ->json('products.*.name');

    expect($names)->toBe(['Zulu', 'Mike', 'Alpha']);
});

// --- Review follow-ups: alias-collision guard, include whitelist, enum in / unknown ---

it('does not mangle the real preset_slug column key (alias collision guard)', function () {
    $target = RateDefinition::factory()->create(['preset_slug' => 'daily-flat']);
    RateDefinition::factory()->create(['preset_slug' => 'weekly-tiered']);

    // The `preset` alias must NOT rewrite `preset_slug_eq` to `preset_slug_slug_eq`.
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/rate_definitions?q[preset_slug_eq]=daily-flat')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('rate_definitions.0.id'))->toBe($target->id);
});

it('ignores an un-whitelisted include without erroring or leaking it', function () {
    $product = Product::factory()->create();
    $stock = StockLevel::factory()->create(['product_id' => $product->id]);

    // A bogus include is dropped by the whitelist gate; the whitelisted one still resolves.
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/products/{$product->id}?include=secretRelation,stock_levels")
        ->assertOk()
        ->assertJsonPath('product.stock_levels.0.id', $stock->id);
});

it('filters products by enum case-insensitively with the in predicate', function () {
    Product::factory()->count(3)->create(['product_type' => 'rental']);
    Product::factory()->count(2)->create(['product_type' => 'sale']);

    $total = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?q[type_in][]=Rental&q[type_in][]=SALE')
        ->assertOk()
        ->json('meta.total');

    expect($total)->toBe(5);
});

it('returns zero results (not 500) for an unknown enum filter value', function () {
    Product::factory()->count(3)->create(['product_type' => 'rental']);

    $total = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/products?q[type_eq]=Nonexistent')
        ->assertOk()
        ->json('meta.total');

    expect($total)->toBe(0);
});
