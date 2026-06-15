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

it('coerces case-insensitive values to the canonical backing value', function (mixed $input, string $expected) {
    expect(RateTransactionType::coerce($input))->toBe($expected);
})->with([
    'lowercase backing value' => ['rental', 'rental'],
    'mixed-case backing value' => ['ReNtAl', 'rental'],
    'case name' => ['Sale', 'sale'],
    'uppercase case name' => ['SERVICE', 'service'],
]);

it('returns the original value untouched when it does not match any case', function (mixed $input) {
    expect(RateTransactionType::coerce($input))->toBe($input);
})->with([
    'unknown string' => ['lease'],
    'empty string' => [''],
]);

it('returns non-scalar input untouched', function () {
    expect(RateTransactionType::coerce(null))->toBeNull();
    expect(RateTransactionType::coerce(['rental']))->toBe(['rental']);
});
