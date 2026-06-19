<?php

use App\Enums\AllowedStockType;
use App\Enums\ProductType;
use App\Enums\StockMethod;
use App\Models\Accessory;
use App\Models\Activity;
use App\Models\CostGroup;
use App\Models\Country;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductTaxClass;
use App\Models\RevenueGroup;
use App\Models\StockLevel;
use App\Services\SchemaRegistry;

it('has correct fillable attributes', function () {
    $product = new Product;

    expect($product->getFillable())->toContain(
        'name',
        'product_type',
        'description',
        'product_group_id',
        'stock_method',
        'barcode',
        'sku',
        'replacement_charge',
        'is_active',
        'accessory_only',
        'discountable',
        'tag_list',
    );
});

it('creates a product with factory defaults', function () {
    $product = Product::factory()->create(['name' => 'Test Product']);

    expect($product->name)->toBe('Test Product')
        ->and($product->product_type)->toBe(ProductType::Rental)
        ->and($product->stock_method)->toBe(StockMethod::Bulk)
        ->and($product->is_active)->toBeTrue();
});

it('casts product_type to ProductType enum', function () {
    $product = Product::factory()->create();

    expect($product->product_type)->toBeInstanceOf(ProductType::class);
});

it('casts stock_method to StockMethod enum', function () {
    $product = Product::factory()->create();

    expect($product->stock_method)->toBeInstanceOf(StockMethod::class);
});

it('casts boolean attributes correctly', function () {
    $product = Product::factory()->create([
        'is_active' => true,
        'accessory_only' => false,
        'system' => false,
        'discountable' => true,
    ]);

    expect($product->is_active)->toBeBool()->toBeTrue()
        ->and($product->accessory_only)->toBeBool()->toBeFalse()
        ->and($product->system)->toBeBool()->toBeFalse()
        ->and($product->discountable)->toBeBool()->toBeTrue();
});

it('casts tag_list as array', function () {
    $product = Product::factory()->create(['tag_list' => ['lighting', 'outdoor']]);
    $product->refresh();

    expect($product->tag_list)->toBe(['lighting', 'outdoor']);
});

it('soft deletes a product', function () {
    $product = Product::factory()->create();
    $product->delete();

    expect(Product::query()->count())->toBe(0)
        ->and(Product::withTrashed()->count())->toBe(1);
});

it('belongs to a product group', function () {
    $group = ProductGroup::factory()->create(['name' => 'Audio']);
    $product = Product::factory()->create(['product_group_id' => $group->id]);

    expect($product->productGroup)->toBeInstanceOf(ProductGroup::class)
        ->and($product->productGroup->id)->toBe($group->id);
});

it('belongs to a tax class', function () {
    $taxClass = ProductTaxClass::factory()->create();
    $product = Product::factory()->create(['tax_class_id' => $taxClass->id]);

    expect($product->taxClass)->toBeInstanceOf(ProductTaxClass::class)
        ->and($product->taxClass->id)->toBe($taxClass->id);
});

it('belongs to a purchase tax class', function () {
    $taxClass = ProductTaxClass::factory()->create();
    $product = Product::factory()->create(['purchase_tax_class_id' => $taxClass->id]);

    expect($product->purchaseTaxClass)->toBeInstanceOf(ProductTaxClass::class)
        ->and($product->purchaseTaxClass->id)->toBe($taxClass->id);
});

it('belongs to a rental revenue group', function () {
    $group = RevenueGroup::factory()->create();
    $product = Product::factory()->create(['rental_revenue_group_id' => $group->id]);

    expect($product->rentalRevenueGroup)->toBeInstanceOf(RevenueGroup::class)
        ->and($product->rentalRevenueGroup->id)->toBe($group->id);
});

it('belongs to a sale revenue group', function () {
    $group = RevenueGroup::factory()->create();
    $product = Product::factory()->create(['sale_revenue_group_id' => $group->id]);

    expect($product->saleRevenueGroup)->toBeInstanceOf(RevenueGroup::class)
        ->and($product->saleRevenueGroup->id)->toBe($group->id);
});

it('belongs to a sub-rental cost group', function () {
    $group = CostGroup::factory()->create();
    $product = Product::factory()->create(['sub_rental_cost_group_id' => $group->id]);

    expect($product->subRentalCostGroup)->toBeInstanceOf(CostGroup::class)
        ->and($product->subRentalCostGroup->id)->toBe($group->id);
});

it('belongs to a purchase cost group', function () {
    $group = CostGroup::factory()->create();
    $product = Product::factory()->create(['purchase_cost_group_id' => $group->id]);

    expect($product->purchaseCostGroup)->toBeInstanceOf(CostGroup::class)
        ->and($product->purchaseCostGroup->id)->toBe($group->id);
});

it('belongs to a country of origin', function () {
    $country = Country::factory()->create();
    $product = Product::factory()->create(['country_of_origin_id' => $country->id]);

    expect($product->countryOfOrigin)->toBeInstanceOf(Country::class)
        ->and($product->countryOfOrigin->id)->toBe($country->id);
});

it('scopes products to the given group via the inGroup scope', function () {
    $group = ProductGroup::factory()->create();
    $otherGroup = ProductGroup::factory()->create();

    $inGroup = Product::factory()->create(['product_group_id' => $group->id]);
    Product::factory()->create(['product_group_id' => $otherGroup->id]);

    $results = Product::query()->inGroup($group->id)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($inGroup->id);
});

it('scopes to active products', function () {
    Product::factory()->create();
    Product::factory()->inactive()->create();

    expect(Product::query()->active()->count())->toBe(1);
});

it('scopes products by type', function () {
    Product::factory()->rental()->create();
    Product::factory()->sale()->create();
    Product::factory()->service()->create();

    expect(Product::query()->ofType(ProductType::Rental)->count())->toBe(1)
        ->and(Product::query()->ofType(ProductType::Sale)->count())->toBe(1)
        ->and(Product::query()->ofType(ProductType::Service)->count())->toBe(1);
});

it('scopes to rental products', function () {
    Product::factory()->rental()->create();
    Product::factory()->sale()->create();

    expect(Product::query()->rental()->count())->toBe(1);
});

it('scopes to sale products', function () {
    Product::factory()->rental()->create();
    Product::factory()->sale()->create();

    expect(Product::query()->sale()->count())->toBe(1);
});

it('scopes to service products', function () {
    Product::factory()->service()->create();
    Product::factory()->rental()->create();

    expect(Product::query()->service()->count())->toBe(1);
});

it('creates a rental product with factory state', function () {
    $product = Product::factory()->rental()->create();

    expect($product->product_type)->toBe(ProductType::Rental);
});

it('creates a sale product with factory state', function () {
    $product = Product::factory()->sale()->create();

    expect($product->product_type)->toBe(ProductType::Sale);
});

it('creates a service product with factory state', function () {
    $product = Product::factory()->service()->create();

    expect($product->product_type)->toBe(ProductType::Service);
});

it('creates a serialised product with factory state', function () {
    $product = Product::factory()->serialised()->create();

    expect($product->stock_method)->toBe(StockMethod::Serialised);
});

it('creates an inactive product with factory state', function () {
    $product = Product::factory()->inactive()->create();

    expect($product->is_active)->toBeFalse();
});

it('creates a product with group using factory state', function () {
    $product = Product::factory()->withGroup()->create();

    expect($product->product_group_id)->not()->toBeNull()
        ->and($product->productGroup)->toBeInstanceOf(ProductGroup::class);
});

it('formats money cost from minor units to decimal string', function () {
    $product = Product::factory()->create([
        'replacement_charge' => 10050,
        'purchase_price' => 0,
        'sub_rental_price' => 999,
    ]);

    expect($product->formatMoneyCost('replacement_charge'))->toBe('100.50')
        ->and($product->formatMoneyCost('purchase_price'))->toBe('0.00')
        ->and($product->formatMoneyCost('sub_rental_price'))->toBe('9.99');
});

it('formats large minor-unit amounts without float drift and round-trips losslessly', function () {
    // Amounts in the range where float division (value / 100) starts losing
    // precision; brick/money minor-unit conversion must stay exact.
    $cases = [
        99999999999 => '999999999.99',
        100000000001 => '1000000000.01',
        2199999999999 => '21999999999.99',
        1 => '0.01',
    ];

    foreach ($cases as $minor => $expected) {
        $product = Product::factory()->create(['replacement_charge' => $minor]);

        $formatted = $product->formatMoneyCost('replacement_charge');

        expect($formatted)->toBe($expected)
            // Round-trip: parse the decimal string back to minor units exactly.
            ->and((int) round(((float) $formatted) * 100))->toBe($minor);
    }
});

it('nullifies product_group_id when group is deleted', function () {
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create(['product_group_id' => $group->id]);

    $group->delete();
    $product->refresh();

    expect($product->product_group_id)->toBeNull();
});

it('has many stock levels', function () {
    $product = Product::factory()->create();
    StockLevel::factory()->count(2)->create(['product_id' => $product->id]);

    expect($product->stockLevels)->toHaveCount(2)
        ->and($product->stockLevels->first())->toBeInstanceOf(StockLevel::class);
});

it('has many accessories', function () {
    $product = Product::factory()->create();
    Accessory::factory()->count(3)->create(['product_id' => $product->id]);

    expect($product->accessories)->toHaveCount(3)
        ->and($product->accessories->first())->toBeInstanceOf(Accessory::class);
});

it('has many accessoryOf records (used as an accessory)', function () {
    $product = Product::factory()->create();
    Accessory::factory()->count(2)->create(['accessory_product_id' => $product->id]);

    expect($product->accessoryOf)->toHaveCount(2)
        ->and($product->accessoryOf->first())->toBeInstanceOf(Accessory::class);
});

it('has activities via morphMany', function () {
    $product = Product::factory()->create();
    Activity::factory()->forProduct($product)->count(2)->create();

    expect($product->activities)->toHaveCount(2)
        ->and($product->activities->first())->toBeInstanceOf(Activity::class);
});

it('scopes to archived products', function () {
    $archived = Product::factory()->create();
    $archived->delete();
    Product::factory()->create();

    expect(Product::query()->archived()->count())->toBe(1);
});

it('scopes to include archived products', function () {
    $archived = Product::factory()->create();
    $archived->delete();
    Product::factory()->create();

    expect(Product::query()->withArchived()->count())->toBe(2);
});

it('derives a human-readable name for an allowed stock type', function () {
    expect(Product::stockTypeName(AllowedStockType::Rental->value))->toBe(AllowedStockType::Rental->label())
        ->and(Product::stockTypeName(AllowedStockType::Sale->value))->toBe(AllowedStockType::Sale->label());
});

it('returns Unknown for an invalid stock type value', function () {
    expect(Product::stockTypeName(99999))->toBe('Unknown');
});

it('defines its schema with core field definitions', function () {
    $schema = (new SchemaRegistry)->resolve(Product::class);

    expect($schema)->toHaveKeys([
        'name', 'description', 'product_type', 'stock_method', 'is_active',
        'accessory_only', 'discountable', 'barcode', 'sku', 'weight',
        'replacement_charge', 'buffer_percent', 'product_group_id', 'tax_class_id',
        'country_of_origin_id', 'tag_list', 'sub_rental_price', 'purchase_price',
    ]);
    expect($schema['name']->required)->toBeTrue()
        ->and($schema['product_group_id']->relationType)->toBe('belongsTo');
});
