<?php

use App\Models\Accessory;
use App\Models\Product;

it('belongs to a product', function () {
    $accessory = Accessory::factory()->create();

    expect($accessory->product)->toBeInstanceOf(Product::class);
});

it('belongs to an accessory product', function () {
    $accessory = Accessory::factory()->create();

    expect($accessory->accessoryProduct)->toBeInstanceOf(Product::class);
});

it('links two different products', function () {
    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create();

    $accessory = Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $accessoryProduct->id,
    ]);

    expect($accessory->product->id)->toBe($product->id);
    expect($accessory->accessoryProduct->id)->toBe($accessoryProduct->id);
});

it('casts quantity to decimal', function () {
    $accessory = Accessory::factory()->create(['quantity' => 2.50]);

    $accessory->refresh();

    expect($accessory->quantity)->toBe('2.50');
});

it('casts included to boolean', function () {
    $accessory = Accessory::factory()->create(['included' => true]);

    expect($accessory->included)->toBeTrue();
    expect($accessory->included)->toBeBool();
});

it('casts zero_priced to boolean', function () {
    $accessory = Accessory::factory()->create(['zero_priced' => false]);

    expect($accessory->zero_priced)->toBeFalse();
    expect($accessory->zero_priced)->toBeBool();
});

it('casts sort_order to integer', function () {
    $accessory = Accessory::factory()->create(['sort_order' => 5]);

    expect($accessory->sort_order)->toBeInt();
    expect($accessory->sort_order)->toBe(5);
});

it('enforces unique product and accessory product combination', function () {
    $product = Product::factory()->create();
    $accessoryProduct = Product::factory()->create();

    Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $accessoryProduct->id,
    ]);

    expect(fn () => Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $accessoryProduct->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('defaults included to true', function () {
    $accessory = Accessory::factory()->create();

    expect($accessory->included)->toBeTrue();
});

it('defaults zero_priced to true', function () {
    $accessory = Accessory::factory()->create();

    expect($accessory->zero_priced)->toBeTrue();
});

it('creates an optional accessory via factory', function () {
    $accessory = Accessory::factory()->optional()->create();

    expect($accessory->included)->toBeFalse();
});

it('creates a priced accessory via factory', function () {
    $accessory = Accessory::factory()->priced()->create();

    expect($accessory->zero_priced)->toBeFalse();
});
