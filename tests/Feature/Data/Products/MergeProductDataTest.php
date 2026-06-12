<?php

use App\Data\Products\MergeProductData;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

it('valid data passes validation', function () {
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    $data = MergeProductData::validateAndCreate([
        'primary_id' => $productA->id,
        'secondary_id' => $productB->id,
    ]);

    expect($data)->toBeInstanceOf(MergeProductData::class)
        ->and($data->primary_id)->toBe($productA->id)
        ->and($data->secondary_id)->toBe($productB->id);
});

it('rejects same ID for primary and secondary', function () {
    $product = Product::factory()->create();

    MergeProductData::validateAndCreate([
        'primary_id' => $product->id,
        'secondary_id' => $product->id,
    ]);
})->throws(ValidationException::class);

it('rejects a self-merge with a secondary_id error and the custom message', function () {
    $product = Product::factory()->create();

    try {
        MergeProductData::validateAndCreate([
            'primary_id' => $product->id,
            'secondary_id' => $product->id,
        ]);

        $this->fail('Expected a ValidationException for a self-merge.');
    } catch (ValidationException $e) {
        expect($e->validator->errors()->keys())->toContain('secondary_id')
            ->and($e->validator->errors()->first('secondary_id'))
            ->toBe('A product cannot be merged into itself.');
    }
});

it('rejects nonexistent product IDs for primary_id', function () {
    $product = Product::factory()->create();

    MergeProductData::validateAndCreate([
        'primary_id' => 999999,
        'secondary_id' => $product->id,
    ]);
})->throws(ValidationException::class);

it('rejects nonexistent product IDs for secondary_id', function () {
    $product = Product::factory()->create();

    MergeProductData::validateAndCreate([
        'primary_id' => $product->id,
        'secondary_id' => 999999,
    ]);
})->throws(ValidationException::class);

it('rejects missing primary_id', function () {
    $product = Product::factory()->create();

    MergeProductData::validateAndCreate([
        'secondary_id' => $product->id,
    ]);
})->throws(ValidationException::class);

it('rejects missing secondary_id', function () {
    $product = Product::factory()->create();

    MergeProductData::validateAndCreate([
        'primary_id' => $product->id,
    ]);
})->throws(ValidationException::class);

it('rejects a soft-deleted secondary_id', function () {
    $primary = Product::factory()->create();
    $secondary = Product::factory()->create();
    $secondary->delete();

    MergeProductData::validateAndCreate([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]);
})->throws(ValidationException::class);

it('rejects a soft-deleted primary_id', function () {
    $primary = Product::factory()->create();
    $secondary = Product::factory()->create();
    $primary->delete();

    MergeProductData::validateAndCreate([
        'primary_id' => $primary->id,
        'secondary_id' => $secondary->id,
    ]);
})->throws(ValidationException::class);
