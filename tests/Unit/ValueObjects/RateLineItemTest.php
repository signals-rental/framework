<?php

use App\ValueObjects\RateLineItem;

it('exposes the supplied values as readonly properties', function () {
    $item = new RateLineItem(
        periodFrom: 3,
        periodTo: 5,
        label: 'Days 3-5',
        multiplier: '0.5',
        unitPriceMinor: 5000,
        lineTotalMinor: 15000,
    );

    expect($item->periodFrom)->toBe(3)
        ->and($item->periodTo)->toBe(5)
        ->and($item->label)->toBe('Days 3-5')
        ->and($item->multiplier)->toBe('0.5')
        ->and($item->unitPriceMinor)->toBe(5000)
        ->and($item->lineTotalMinor)->toBe(15000);
});

it('serialises to a snake-cased array', function () {
    $item = new RateLineItem(
        periodFrom: 1,
        periodTo: 2,
        label: 'Days 1-2',
        multiplier: '1.0',
        unitPriceMinor: 10000,
        lineTotalMinor: 20000,
    );

    expect($item->toArray())->toBe([
        'period_from' => 1,
        'period_to' => 2,
        'label' => 'Days 1-2',
        'multiplier' => '1.0',
        'unit_price_minor' => 10000,
        'line_total_minor' => 20000,
    ]);
});
