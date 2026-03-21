<?php

use App\Enums\StockMethod;

it('has correct cases', function () {
    expect(StockMethod::cases())->toHaveCount(2);
});

it('has CRMS-compatible integer values', function (StockMethod $method, int $expected) {
    expect($method->value)->toBe($expected);
})->with([
    [StockMethod::Bulk, 1],
    [StockMethod::Serialised, 2],
]);

it('returns correct labels', function (StockMethod $method, string $expected) {
    expect($method->label())->toBe($expected);
})->with([
    [StockMethod::Bulk, 'Bulk'],
    [StockMethod::Serialised, 'Serialised'],
]);

it('has a label for every case', function () {
    foreach (StockMethod::cases() as $method) {
        expect($method->label())->toBeString()->not()->toBeEmpty();
    }
});
