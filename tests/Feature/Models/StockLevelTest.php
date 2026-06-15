<?php

use App\Enums\AllowedStockType;
use App\Enums\StockCategory;
use App\Models\Activity;
use App\Models\Member;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\SchemaRegistry;
use Carbon\CarbonImmutable;

it('belongs to a product', function () {
    $stockLevel = StockLevel::factory()->create();

    expect($stockLevel->product)->toBeInstanceOf(Product::class);
});

it('belongs to a store', function () {
    $stockLevel = StockLevel::factory()->create();

    expect($stockLevel->store)->toBeInstanceOf(Store::class);
});

it('optionally belongs to a member', function () {
    $member = Member::factory()->create();
    $stockLevel = StockLevel::factory()->create(['member_id' => $member->id]);

    expect($stockLevel->member)->toBeInstanceOf(Member::class);
    expect($stockLevel->member->id)->toBe($member->id);
});

it('returns null when no member is assigned', function () {
    $stockLevel = StockLevel::factory()->create(['member_id' => null]);

    expect($stockLevel->member)->toBeNull();
});

it('belongs to a container stock level', function () {
    $container = StockLevel::factory()->create();
    $item = StockLevel::factory()->create([
        'container_stock_level_id' => $container->id,
    ]);

    expect($item->container)->toBeInstanceOf(StockLevel::class);
    expect($item->container->id)->toBe($container->id);
});

it('has many contained items', function () {
    $container = StockLevel::factory()->create();
    StockLevel::factory()->count(3)->create([
        'container_stock_level_id' => $container->id,
    ]);

    expect($container->containedItems)->toHaveCount(3);
    expect($container->containedItems->first())->toBeInstanceOf(StockLevel::class);
});

it('scopes to a specific product', function () {
    $product = Product::factory()->create();
    StockLevel::factory()->count(2)->create(['product_id' => $product->id]);
    StockLevel::factory()->create();

    $results = StockLevel::query()->forProduct($product->id)->get();

    expect($results)->toHaveCount(2);
});

it('scopes to a specific store', function () {
    $store = Store::factory()->create();
    StockLevel::factory()->count(2)->create(['store_id' => $store->id]);
    StockLevel::factory()->create();

    $results = StockLevel::query()->forStore($store->id)->get();

    expect($results)->toHaveCount(2);
});

it('scopes to available stock', function () {
    StockLevel::factory()->create([
        'quantity_held' => 10,
        'quantity_allocated' => 3,
        'quantity_unavailable' => 2,
    ]);
    StockLevel::factory()->create([
        'quantity_held' => 5,
        'quantity_allocated' => 3,
        'quantity_unavailable' => 2,
    ]);

    $available = StockLevel::query()->available()->get();

    expect($available)->toHaveCount(1);
    expect((float) $available->first()->quantity_held)->toBe(10.00);
});

it('scopes each availability chip to its correct subset', function () {
    // Available: held exceeds allocated + unavailable.
    $available = StockLevel::factory()->create([
        'quantity_held' => 10,
        'quantity_allocated' => 2,
        'quantity_unavailable' => 0,
    ]);
    // Fully allocated: allocated >= held, nothing quarantined.
    $allocated = StockLevel::factory()->create([
        'quantity_held' => 5,
        'quantity_allocated' => 5,
        'quantity_unavailable' => 0,
    ]);
    // Quarantined: some unavailable and no remaining availability.
    $quarantined = StockLevel::factory()->create([
        'quantity_held' => 4,
        'quantity_allocated' => 0,
        'quantity_unavailable' => 4,
    ]);

    expect(StockLevel::query()->count())->toBe(3);

    $availableIds = StockLevel::query()->available()->pluck('id');
    expect($availableIds->all())->toBe([$available->id]);

    $allocatedIds = StockLevel::query()->allocated()->pluck('id');
    expect($allocatedIds->all())->toBe([$allocated->id]);

    $quarantinedIds = StockLevel::query()->quarantined()->pluck('id');
    expect($quarantinedIds->all())->toBe([$quarantined->id]);
});

it('does not classify a quarantined level as allocated (chips are disjoint)', function () {
    // allocated >= held but also quarantined — must count as quarantined only.
    $level = StockLevel::factory()->create([
        'quantity_held' => 5,
        'quantity_allocated' => 5,
        'quantity_unavailable' => 2,
    ]);

    expect(StockLevel::query()->quarantined()->pluck('id')->all())->toBe([$level->id])
        ->and(StockLevel::query()->allocated()->exists())->toBeFalse();
});

it('scopes to serialized stock', function () {
    StockLevel::factory()->serialised()->create();
    StockLevel::factory()->bulk()->create();

    $serialized = StockLevel::query()->serialized()->get();

    expect($serialized)->toHaveCount(1);
    expect($serialized->first()->stock_category)->toBe(StockCategory::SerialisedStock);
});

it('scopes to bulk stock', function () {
    StockLevel::factory()->serialised()->create();
    StockLevel::factory()->bulk()->create();

    $bulk = StockLevel::query()->bulk()->get();

    expect($bulk)->toHaveCount(1);
    expect($bulk->first()->stock_category)->toBe(StockCategory::BulkStock);
});

it('casts quantity fields to decimal', function () {
    $stockLevel = StockLevel::factory()->create([
        'quantity_held' => 10.50,
        'quantity_allocated' => 3.25,
        'quantity_unavailable' => 1.75,
        'quantity_on_order' => 5.00,
    ]);

    $stockLevel->refresh();

    expect($stockLevel->quantity_held)->toBe('10.50');
    expect($stockLevel->quantity_allocated)->toBe('3.25');
    expect($stockLevel->quantity_unavailable)->toBe('1.75');
    expect($stockLevel->quantity_on_order)->toBe('5.00');
});

it('casts stock_type and stock_category to enums', function () {
    $stockLevel = StockLevel::factory()->create([
        'stock_type' => 1,
        'stock_category' => 50,
    ]);

    expect($stockLevel->stock_type)->toBe(AllowedStockType::Rental);
    expect($stockLevel->stock_category)->toBe(StockCategory::SerialisedStock);
});

it('casts datetime fields', function () {
    $now = now();
    $stockLevel = StockLevel::factory()->create([
        'starts_at' => $now,
        'ends_at' => $now->copy()->addDays(7),
        'last_count_at' => $now->copy()->subDay(),
    ]);

    $stockLevel->refresh();

    expect($stockLevel->starts_at)->toBeInstanceOf(CarbonImmutable::class);
    expect($stockLevel->ends_at)->toBeInstanceOf(CarbonImmutable::class);
    expect($stockLevel->last_count_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('creates a serialised stock level via factory', function () {
    $stockLevel = StockLevel::factory()->serialised()->create();

    expect($stockLevel->stock_category)->toBe(StockCategory::SerialisedStock);
    expect((float) $stockLevel->quantity_held)->toBe(1.00);
    expect($stockLevel->serial_number)->not->toBeNull();
    expect($stockLevel->asset_number)->not->toBeNull();
    expect($stockLevel->item_name)->not->toBeNull();
});

it('creates a bulk stock level via factory', function () {
    $stockLevel = StockLevel::factory()->bulk()->create();

    expect($stockLevel->stock_category)->toBe(StockCategory::BulkStock);
    expect((float) $stockLevel->quantity_held)->toBeGreaterThanOrEqual(5);
});

it('creates an allocated stock level via factory', function () {
    $stockLevel = StockLevel::factory()->allocated()->create();

    expect((float) $stockLevel->quantity_allocated)->toBeGreaterThanOrEqual(1);
});

it('has activities via morphMany', function () {
    $stockLevel = StockLevel::factory()->create();
    Activity::factory()->forStockLevel($stockLevel)->count(2)->create();

    expect($stockLevel->activities)->toHaveCount(2)
        ->and($stockLevel->activities->first())->toBeInstanceOf(Activity::class);
});

it('defines its schema with core field definitions', function () {
    $schema = (new SchemaRegistry)->resolve(StockLevel::class);

    expect($schema)->toHaveKeys([
        'product_id', 'store_id', 'member_id', 'item_name', 'asset_number',
        'serial_number', 'barcode', 'location', 'stock_type', 'stock_category',
        'quantity_held', 'container_mode', 'starts_at', 'ends_at', 'last_count_at',
    ]);
    expect($schema['product_id']->relationType)->toBe('belongsTo')
        ->and($schema['product_id']->required)->toBeTrue()
        ->and($schema['item_name']->searchable)->toBeTrue();
});
