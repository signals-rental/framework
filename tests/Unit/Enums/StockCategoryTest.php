<?php

use App\Enums\StockCategory;

it('has correct cases', function () {
    expect(StockCategory::cases())->toHaveCount(2);
});

it('has CRMS-compatible integer values', function (StockCategory $category, int $expected) {
    expect($category->value)->toBe($expected);
})->with([
    [StockCategory::BulkStock, 10],
    [StockCategory::SerialisedStock, 50],
]);

it('returns correct labels', function (StockCategory $category, string $expected) {
    expect($category->label())->toBe($expected);
})->with([
    [StockCategory::BulkStock, 'Bulk Stock'],
    [StockCategory::SerialisedStock, 'Serialised Stock'],
]);

it('has a label for every case', function () {
    foreach (StockCategory::cases() as $category) {
        expect($category->label())->toBeString()->not()->toBeEmpty();
    }
});
