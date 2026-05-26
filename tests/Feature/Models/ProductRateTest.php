<?php

use App\Contracts\HasSchema;
use App\Enums\RateTransactionType;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\Store;
use App\Services\SchemaBuilder;
use Carbon\CarbonInterface;

it('persists and casts its columns', function () {
    $rate = ProductRate::factory()->create([
        'transaction_type' => RateTransactionType::Rental,
        'price' => 12550,
        'currency' => 'GBP',
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-12-31',
        'priority' => 5,
    ]);

    $fresh = $rate->fresh();

    expect($fresh->transaction_type)->toBe(RateTransactionType::Rental)
        ->and($fresh->price)->toBe(12550)
        ->and($fresh->currency)->toBe('GBP')
        ->and($fresh->valid_from)->toBeInstanceOf(CarbonInterface::class)
        ->and($fresh->valid_from->toDateString())->toBe('2026-01-01')
        ->and($fresh->valid_to->toDateString())->toBe('2026-12-31')
        ->and($fresh->priority)->toBe(5);
});

it('implements the schema contract', function () {
    expect(new ProductRate)->toBeInstanceOf(HasSchema::class);
});

it('formats its price as a decimal money string', function () {
    $rate = ProductRate::factory()->make(['price' => 12550]);

    expect($rate->formatMoneyCost('price'))->toBe('125.50');
});

it('belongs to a product', function () {
    $product = Product::factory()->create();
    $rate = ProductRate::factory()->for($product)->create();

    expect($rate->product)->not->toBeNull()
        ->and($rate->product->id)->toBe($product->id);
});

it('belongs to a rate definition', function () {
    $definition = RateDefinition::factory()->create();
    $rate = ProductRate::factory()->for($definition, 'rateDefinition')->create();

    expect($rate->rateDefinition->id)->toBe($definition->id);
});

it('optionally belongs to a store', function () {
    $store = Store::factory()->create();
    $rate = ProductRate::factory()->for($store)->create();

    expect($rate->store->id)->toBe($store->id);
});

it('allows a null store (all-stores rate)', function () {
    $rate = ProductRate::factory()->create(['store_id' => null]);

    expect($rate->store)->toBeNull();
});

it('is reachable from its rate definition', function () {
    $definition = RateDefinition::factory()->create();
    ProductRate::factory()->count(3)->for($definition, 'rateDefinition')->create();

    expect($definition->productRates)->toHaveCount(3);
});

it('defines a schema', function () {
    $builder = new SchemaBuilder;

    ProductRate::defineSchema($builder);

    $fields = $builder->build();

    expect($fields)->toHaveKeys([
        'product_id',
        'rate_definition_id',
        'store_id',
        'transaction_type',
        'price',
        'currency',
        'valid_from',
        'valid_to',
        'priority',
        'created_at',
        'updated_at',
    ]);
});
