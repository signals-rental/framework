<?php

use App\Enums\LineItemTransactionType;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\RevenueGroup;
use App\Services\Opportunities\OpportunityAutoGroupResolver;

it('returns null revenue group and Other for non-product lines', function () {
    $item = new OpportunityItem([
        'itemable_id' => null,
        'itemable_type' => null,
    ]);

    [$revenueGroupId, $label] = app(OpportunityAutoGroupResolver::class)->resolve($item);

    expect($revenueGroupId)->toBeNull()
        ->and($label)->toBe('Other');
});

it('derives rental revenue_group_id and Ungrouped label for an ungrouped product', function () {
    $rentalGroup = RevenueGroup::factory()->create();
    $product = Product::factory()->create([
        'product_group_id' => null,
        'rental_revenue_group_id' => $rentalGroup->id,
    ]);

    $item = new OpportunityItem([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Rental,
    ]);

    [$revenueGroupId, $label] = app(OpportunityAutoGroupResolver::class)->resolve($item);

    expect($revenueGroupId)->toBe($rentalGroup->id)
        ->and($label)->toBe('Ungrouped');
});

it('returns null group and Other when the linked product no longer exists', function () {
    $item = new OpportunityItem([
        'itemable_id' => 999999, // no such product; on-demand find returns null
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Rental,
    ]);

    [$revenueGroupId, $label] = app(OpportunityAutoGroupResolver::class)->resolve($item);

    expect($revenueGroupId)->toBeNull()
        ->and($label)->toBe('Other');
});

it('returns the other legacy section key when the linked product is missing', function () {
    $item = new OpportunityItem([
        'itemable_id' => 888888,
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Rental,
    ]);

    [$key, $label] = app(OpportunityAutoGroupResolver::class)->resolveLegacySectionKey($item);

    expect($key)->toBe('auto:other')
        ->and($label)->toBe('Other');
});

it('derives sale revenue_group_id and a parent-group label for grouped products', function () {
    $parent = ProductGroup::factory()->create(['name' => 'AV']);
    $group = ProductGroup::factory()->create(['name' => 'Lighting', 'parent_id' => $parent->id]);
    $saleGroup = RevenueGroup::factory()->create();
    $product = Product::factory()->create([
        'product_group_id' => $group->id,
        'sale_revenue_group_id' => $saleGroup->id,
    ]);

    $item = new OpportunityItem([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Sale,
    ]);

    [$revenueGroupId, $label] = app(OpportunityAutoGroupResolver::class)->resolve($item, [$product->id => $product->load('productGroup.parent')]);

    expect($revenueGroupId)->toBe($saleGroup->id)
        ->and($label)->toBe('AV · Lighting');
});
