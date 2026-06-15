<?php

use App\Data\Products\CreateStockLevelData;
use App\Data\Products\UpdateStockLevelData;
use App\Models\Member;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Validation\ValidationException;

it('accepts an active member id when creating a stock level', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();
    $member = Member::factory()->create();

    $data = CreateStockLevelData::validateAndCreate([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'member_id' => $member->id,
    ]);

    expect($data)->toBeInstanceOf(CreateStockLevelData::class)
        ->and($data->member_id)->toBe($member->id);
});

it('rejects an archived (soft-deleted) member id when creating a stock level', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();
    $member = Member::factory()->create();
    $member->delete();

    try {
        CreateStockLevelData::validateAndCreate([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'member_id' => $member->id,
        ]);

        $this->fail('Expected a ValidationException for an archived member.');
    } catch (ValidationException $e) {
        expect($e->validator->errors()->keys())->toContain('member_id');
    }
});

it('rejects an archived (soft-deleted) member id when updating a stock level', function () {
    $member = Member::factory()->create();
    $member->delete();

    UpdateStockLevelData::validateAndCreate([
        'member_id' => $member->id,
    ]);
})->throws(ValidationException::class);

it('accepts an active member id when updating a stock level', function () {
    $member = Member::factory()->create();

    $data = UpdateStockLevelData::validateAndCreate([
        'member_id' => $member->id,
    ]);

    expect($data->member_id)->toBe($member->id);
});

it('still allows a null member id', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();

    $data = CreateStockLevelData::validateAndCreate([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'member_id' => null,
    ]);

    expect($data->member_id)->toBeNull();
});
