<?php

use App\Enums\RateTransactionType;

it('has correct cases', function () {
    expect(RateTransactionType::cases())->toHaveCount(3);
});

it('has correct string values', function (RateTransactionType $type, string $expected) {
    expect($type->value)->toBe($expected);
})->with([
    [RateTransactionType::Rental, 'rental'],
    [RateTransactionType::Sale, 'sale'],
    [RateTransactionType::Service, 'service'],
]);

it('returns correct labels', function (RateTransactionType $type, string $expected) {
    expect($type->label())->toBe($expected);
})->with([
    [RateTransactionType::Rental, 'Rental'],
    [RateTransactionType::Sale, 'Sale'],
    [RateTransactionType::Service, 'Service'],
]);

it('has a label for every case', function () {
    foreach (RateTransactionType::cases() as $type) {
        expect($type->label())->toBeString()->not()->toBeEmpty();
    }
});
