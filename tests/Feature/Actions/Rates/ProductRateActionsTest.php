<?php

use App\Actions\Rates\CreateProductRate;
use App\Actions\Rates\DeleteProductRate;
use App\Actions\Rates\UpdateProductRate;
use App\Data\Rates\CreateProductRateData;
use App\Data\Rates\ProductRateData;
use App\Data\Rates\UpdateProductRateData;
use App\Enums\RateTransactionType;
use App\Events\AuditableEvent;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\User;
use App\Services\RateEngine\ProductRateOverlapChecker;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 */
function productRateData(array $overrides = []): CreateProductRateData
{
    return CreateProductRateData::from(array_merge([
        'product_id' => Product::factory()->create()->id,
        'rate_definition_id' => RateDefinition::factory()->create()->id,
        'transaction_type' => 'rental',
        'price' => 5000,
        'currency' => 'GBP',
        'priority' => 0,
    ], $overrides));
}

it('creates a product rate', function () {
    Event::fake([AuditableEvent::class]);

    $result = (new CreateProductRate)(productRateData());

    expect($result)->toBeInstanceOf(ProductRateData::class)
        ->and($result->price)->toBe('50.00');

    $this->assertDatabaseHas('product_rates', ['id' => $result->id, 'price' => 5000]);
    Event::assertDispatched(AuditableEvent::class);
});

it('forbids creating a product rate without permission', function () {
    $this->actingAs(User::factory()->create());

    expect(fn () => (new CreateProductRate)(productRateData()))
        ->toThrow(AuthorizationException::class);
});

it('updates a product rate', function () {
    $rate = ProductRate::factory()->create(['price' => 1000]);

    $result = (new UpdateProductRate)($rate, UpdateProductRateData::from(['price' => 8000, 'priority' => 9]));

    expect($result->price)->toBe('80.00')
        ->and($result->priority)->toBe(9);
});

it('deletes a product rate', function () {
    Event::fake([AuditableEvent::class]);
    $rate = ProductRate::factory()->create();

    (new DeleteProductRate)($rate);

    $this->assertDatabaseMissing('product_rates', ['id' => $rate->id]);
    Event::assertDispatched(AuditableEvent::class);
});

it('creates overlapping rates without blocking (overlaps are intentional)', function () {
    $product = Product::factory()->create();
    $definition = RateDefinition::factory()->create();

    $first = (new CreateProductRate)(productRateData([
        'product_id' => $product->id,
        'rate_definition_id' => $definition->id,
        'priority' => 1,
    ]));

    // Same product/store/type/priority with an overlapping (open) window: allowed.
    $second = (new CreateProductRate)(productRateData([
        'product_id' => $product->id,
        'rate_definition_id' => $definition->id,
        'priority' => 1,
    ]));

    expect($first->id)->not->toBe($second->id)
        ->and(ProductRate::query()->count())->toBe(2);
});

it('detects a same-priority overlapping rate via the overlap checker', function () {
    $product = Product::factory()->create();
    ProductRate::factory()->for($product)->withPriority(1)->create([
        'transaction_type' => RateTransactionType::Rental,
        'store_id' => null,
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-12-31',
    ]);

    $overlaps = app(ProductRateOverlapChecker::class)->overlapping(
        $product->id,
        null,
        RateTransactionType::Rental,
        1,
        '2026-06-01',
        '2027-01-31',
    );

    expect($overlaps)->toHaveCount(1);
});

it('does not flag a different priority or a non-overlapping window', function () {
    $product = Product::factory()->create();
    ProductRate::factory()->for($product)->withPriority(1)->create([
        'transaction_type' => RateTransactionType::Rental,
        'store_id' => null,
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-06-30',
    ]);

    $checker = app(ProductRateOverlapChecker::class);

    // Different priority -> no overlap.
    expect($checker->overlapping($product->id, null, RateTransactionType::Rental, 2, null, null))->toHaveCount(0);

    // Same priority but a window starting after the existing one ends -> no overlap.
    expect($checker->overlapping($product->id, null, RateTransactionType::Rental, 1, '2026-07-01', '2026-12-31'))->toHaveCount(0);
});

it('excludes a given rate id from the overlap check (for updates)', function () {
    $product = Product::factory()->create();
    $rate = ProductRate::factory()->for($product)->withPriority(1)->create([
        'transaction_type' => RateTransactionType::Rental,
        'store_id' => null,
        'valid_from' => null,
        'valid_to' => null,
    ]);

    $overlaps = app(ProductRateOverlapChecker::class)->overlapping(
        $product->id,
        null,
        RateTransactionType::Rental,
        1,
        null,
        null,
        exceptId: $rate->id,
    );

    expect($overlaps)->toHaveCount(0);
});
