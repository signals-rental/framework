<?php

use App\Enums\BasePeriod;

it('has correct cases', function () {
    expect(BasePeriod::cases())->toHaveCount(5);
});

it('has correct string values', function (BasePeriod $period, string $expected) {
    expect($period->value)->toBe($expected);
})->with([
    [BasePeriod::HalfHourly, 'half_hourly'],
    [BasePeriod::Hourly, 'hourly'],
    [BasePeriod::Daily, 'daily'],
    [BasePeriod::Weekly, 'weekly'],
    [BasePeriod::Monthly, 'monthly'],
]);

it('returns correct labels', function (BasePeriod $period, string $expected) {
    expect($period->label())->toBe($expected);
})->with([
    [BasePeriod::HalfHourly, 'Half-Hourly'],
    [BasePeriod::Hourly, 'Hourly'],
    [BasePeriod::Daily, 'Daily'],
    [BasePeriod::Weekly, 'Weekly'],
    [BasePeriod::Monthly, 'Monthly'],
]);

it('has a label for every case', function () {
    foreach (BasePeriod::cases() as $period) {
        expect($period->label())->toBeString()->not()->toBeEmpty();
    }
});

it('returns correct minutes per period', function (BasePeriod $period, int $minutes) {
    expect($period->minutes())->toBe($minutes);
})->with([
    [BasePeriod::HalfHourly, 30],
    [BasePeriod::Hourly, 60],
    [BasePeriod::Daily, 1440],
    [BasePeriod::Weekly, 10080],
    [BasePeriod::Monthly, 43200],
]);

it('flags clock-based periods', function (BasePeriod $period, bool $expected) {
    expect($period->isClockBased())->toBe($expected);
})->with([
    [BasePeriod::HalfHourly, true],
    [BasePeriod::Hourly, true],
    [BasePeriod::Daily, false],
    [BasePeriod::Weekly, false],
    [BasePeriod::Monthly, false],
]);
