<?php

use App\Data\Products\ProductData;
use App\Enums\ProductType;
use App\Enums\StockMethod;
use App\Models\CostGroup;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductTaxClass;
use App\Models\RevenueGroup;

it('maps is_active to active key in output', function () {
    $product = Product::factory()->create(['is_active' => true]);
    $product->refresh();

    $data = ProductData::fromModel($product);
    $output = $data->toArray();

    expect($output)->toHaveKey('active', true);
    expect($output)->not->toHaveKey('is_active');
});

it('formats money fields as decimal strings', function () {
    $product = Product::factory()->create([
        'replacement_charge' => 12550,
        'sub_rental_price' => 5000,
        'purchase_price' => 99,
    ]);
    $product->refresh();

    $data = ProductData::fromModel($product);

    expect($data->replacement_charge)->toBe('125.50');
    expect($data->sub_rental_price)->toBe('50.00');
    expect($data->purchase_price)->toBe('0.99');
});

it('derives stock_method_name from stock method enum', function (StockMethod $method, string $expectedName) {
    $product = Product::factory()->create([
        'stock_method' => $method,
    ]);
    $product->refresh();

    $data = ProductData::fromModel($product);

    expect($data->stock_method)->toBe($method->value);
    expect($data->stock_method_name)->toBe($expectedName);
})->with([
    [StockMethod::Bulk, 'Bulk'],
    [StockMethod::Serialised, 'Serialised'],
]);

it('derives allowed_stock_type_name correctly', function (int $type, string $expectedName) {
    $product = Product::factory()->create([
        'allowed_stock_type' => $type,
    ]);
    $product->refresh();

    $data = ProductData::fromModel($product);

    expect($data->allowed_stock_type)->toBe($type);
    expect($data->allowed_stock_type_name)->toBe($expectedName);
})->with([
    [1, 'Rental'],
    [2, 'Sale'],
    [3, 'Both'],
]);

it('maps product_type to type label', function () {
    $product = Product::factory()->create([
        'product_type' => ProductType::Service,
    ]);
    $product->refresh();

    $data = ProductData::fromModel($product);

    expect($data->type)->toBe('Service');
});

it('formats buffer_percent as string with one decimal', function () {
    $product = Product::factory()->create([
        'buffer_percent' => 15.5,
    ]);
    $product->refresh();

    $data = ProductData::fromModel($product);

    expect($data->buffer_percent)->toBe('15.5');
});

it('includes product_group when relation is loaded', function () {
    $group = ProductGroup::factory()->create(['name' => 'Lighting']);
    $product = Product::factory()->create(['product_group_id' => $group->id]);
    $product->load('productGroup');

    $data = ProductData::fromModel($product);

    expect($data->product_group)->not->toBeNull()
        ->and($data->product_group->id)->toBe($group->id)
        ->and($data->product_group->name)->toBe('Lighting');
});

it('excludes product_group when relation is not loaded', function () {
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create(['product_group_id' => $group->id]);

    $data = ProductData::fromModel($product);

    expect($data->product_group)->toBeNull();
});

it('formats timestamps in CRMS ISO 8601 format', function () {
    $product = Product::factory()->create();
    $product->refresh();

    $data = ProductData::fromModel($product);

    expect($data->created_at)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
    expect($data->updated_at)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
});

it('returns empty custom_fields when relation is not loaded', function () {
    $product = Product::factory()->create();
    $product->refresh();

    $data = ProductData::fromModel($product);

    expect($data->custom_fields)->toBe([]);
});

it('returns null product_group_id when unset', function () {
    $product = Product::factory()->create(['product_group_id' => null]);
    $product->refresh();

    $data = ProductData::fromModel($product);

    expect($data->product_group_id)->toBeNull();
});

it('returns null icon when no icon_url set', function () {
    $product = Product::factory()->create(['icon_url' => null]);
    $product->refresh();

    $data = ProductData::fromModel($product);

    expect($data->icon)->toBeNull();
});

it('includes tax class, revenue and cost group references when relations are loaded', function () {
    $taxClass = ProductTaxClass::factory()->create(['name' => 'Standard VAT']);
    $purchaseTaxClass = ProductTaxClass::factory()->create(['name' => 'Reduced VAT']);
    $rentalRevenue = RevenueGroup::factory()->create(['name' => 'Rental Income']);
    $saleRevenue = RevenueGroup::factory()->create(['name' => 'Sale Income']);
    $subRentalCost = CostGroup::factory()->create(['name' => 'Sub-hire Cost']);
    $purchaseCost = CostGroup::factory()->create(['name' => 'Purchase Cost']);

    $product = Product::factory()->create([
        'tax_class_id' => $taxClass->id,
        'purchase_tax_class_id' => $purchaseTaxClass->id,
        'rental_revenue_group_id' => $rentalRevenue->id,
        'sale_revenue_group_id' => $saleRevenue->id,
        'sub_rental_cost_group_id' => $subRentalCost->id,
        'purchase_cost_group_id' => $purchaseCost->id,
    ]);

    $product->load([
        'taxClass', 'purchaseTaxClass', 'rentalRevenueGroup',
        'saleRevenueGroup', 'subRentalCostGroup', 'purchaseCostGroup',
    ]);

    $data = ProductData::fromModel($product);

    expect($data->tax_class)->not->toBeNull()
        ->and($data->tax_class->name)->toBe('Standard VAT')
        ->and($data->purchase_tax_class->name)->toBe('Reduced VAT')
        ->and($data->rental_revenue_group->name)->toBe('Rental Income')
        ->and($data->sale_revenue_group->name)->toBe('Sale Income')
        ->and($data->sub_rental_cost_group->name)->toBe('Sub-hire Cost')
        ->and($data->purchase_cost_group->name)->toBe('Purchase Cost');
});

it('excludes tax class and group references when relations are not loaded', function () {
    $taxClass = ProductTaxClass::factory()->create();
    $product = Product::factory()->create(['tax_class_id' => $taxClass->id]);

    $data = ProductData::fromModel($product);

    expect($data->tax_class)->toBeNull()
        ->and($data->purchase_tax_class)->toBeNull()
        ->and($data->rental_revenue_group)->toBeNull()
        ->and($data->sale_revenue_group)->toBeNull()
        ->and($data->sub_rental_cost_group)->toBeNull()
        ->and($data->purchase_cost_group)->toBeNull();
});

it('returns icon with url and thumb_url when set', function () {
    $product = Product::factory()->create([
        'icon_url' => 'https://example.com/icon.png',
        'icon_thumb_url' => 'https://example.com/icon_thumb.png',
    ]);
    $product->refresh();

    $data = ProductData::fromModel($product);

    expect($data->icon)->toBe([
        'url' => 'https://example.com/icon.png',
        'thumb_url' => 'https://example.com/icon_thumb.png',
    ]);
});
