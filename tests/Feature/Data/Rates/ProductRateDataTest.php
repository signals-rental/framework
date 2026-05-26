<?php

use App\Data\Rates\CreateProductRateData;
use App\Data\Rates\ProductRateData;
use App\Data\Rates\UpdateProductRateData;
use App\Enums\RateTransactionType;
use App\Models\ProductRate;
use Illuminate\Validation\ValidationException;

it('builds a create DTO from valid input', function () {
    $data = CreateProductRateData::from([
        'product_id' => 1,
        'rate_definition_id' => 2,
        'transaction_type' => 'rental',
        'price' => 12550,
        'currency' => 'GBP',
        'priority' => 5,
    ]);

    expect($data->product_id)->toBe(1)
        ->and($data->transaction_type)->toBe(RateTransactionType::Rental)
        ->and($data->price)->toBe(12550)
        ->and($data->store_id)->toBeNull()
        ->and($data->priority)->toBe(5);
});

it('requires product, rate definition, transaction type, price and currency', function (array $payload) {
    CreateProductRateData::validate($payload);
})->throws(ValidationException::class)->with([
    'missing product' => [['rate_definition_id' => 2, 'transaction_type' => 'rental', 'price' => 100, 'currency' => 'GBP']],
    'invalid transaction type' => [['product_id' => 1, 'rate_definition_id' => 2, 'transaction_type' => 'nope', 'price' => 100, 'currency' => 'GBP']],
    'negative price' => [['product_id' => 1, 'rate_definition_id' => 2, 'transaction_type' => 'rental', 'price' => -5, 'currency' => 'GBP']],
]);

it('treats all update fields as optional', function () {
    $data = UpdateProductRateData::from(['price' => 999]);

    expect($data->price)->toBe(999)
        ->and($data->transaction_type)->toBeNull();
});

it('validates update input against its rules', function () {
    expect(fn () => UpdateProductRateData::validate(['price' => -1]))->toThrow(ValidationException::class);

    $data = UpdateProductRateData::validate(['priority' => 3]);

    expect($data['priority'])->toBe(3);
});

it('includes the rate definition reference when the relation is loaded', function () {
    $rate = ProductRate::factory()->create();
    $rate->load('rateDefinition');

    $dto = ProductRateData::fromModel($rate);

    expect($dto->rate_definition)->not->toBeNull()
        ->and($dto->rate_definition->id)->toBe($rate->rate_definition_id);
});

it('serialises a product rate to a response DTO with money as a decimal string', function () {
    $rate = ProductRate::factory()->create([
        'transaction_type' => RateTransactionType::Rental,
        'price' => 12550,
        'currency' => 'GBP',
        'valid_from' => '2026-06-01',
        'valid_to' => '2026-08-31',
        'priority' => 3,
    ]);

    $dto = ProductRateData::fromModel($rate);

    expect($dto->id)->toBe($rate->id)
        ->and($dto->transaction_type)->toBe('rental')
        ->and($dto->transaction_type_name)->toBe('Rental')
        ->and($dto->price)->toBe('125.50')
        ->and($dto->currency)->toBe('GBP')
        ->and($dto->valid_from)->toBe('2026-06-01')
        ->and($dto->valid_to)->toBe('2026-08-31')
        ->and($dto->priority)->toBe(3);
});

it('serialises null validity dates', function () {
    $rate = ProductRate::factory()->create(['valid_from' => null, 'valid_to' => null]);

    $dto = ProductRateData::fromModel($rate);

    expect($dto->valid_from)->toBeNull()
        ->and($dto->valid_to)->toBeNull();
});
