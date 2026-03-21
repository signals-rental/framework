<?php

use App\Enums\ProductType;

it('has correct cases', function () {
    expect(ProductType::cases())->toHaveCount(4);
});

it('has correct string values', function (ProductType $type, string $expected) {
    expect($type->value)->toBe($expected);
})->with([
    [ProductType::Rental, 'rental'],
    [ProductType::Sale, 'sale'],
    [ProductType::Service, 'service'],
    [ProductType::LossAndDamage, 'loss_and_damage'],
]);

it('returns correct labels', function (ProductType $type, string $expected) {
    expect($type->label())->toBe($expected);
})->with([
    [ProductType::Rental, 'Rental'],
    [ProductType::Sale, 'Sale'],
    [ProductType::Service, 'Service'],
    [ProductType::LossAndDamage, 'Loss & Damage'],
]);

it('has a label for every case', function () {
    foreach (ProductType::cases() as $type) {
        expect($type->label())->toBeString()->not()->toBeEmpty();
    }
});
