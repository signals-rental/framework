<?php

use App\Enums\DayType;

it('has correct cases', function () {
    expect(DayType::cases())->toHaveCount(2);
});

it('has correct string values', function (DayType $type, string $expected) {
    expect($type->value)->toBe($expected);
})->with([
    [DayType::Clock, 'clock'],
    [DayType::Business, 'business'],
]);

it('returns correct labels', function (DayType $type, string $expected) {
    expect($type->label())->toBe($expected);
})->with([
    [DayType::Clock, 'Clock'],
    [DayType::Business, 'Business Hours'],
]);

it('has a label for every case', function () {
    foreach (DayType::cases() as $type) {
        expect($type->label())->toBeString()->not()->toBeEmpty();
    }
});
