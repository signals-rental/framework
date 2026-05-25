<?php

use App\ValueObjects\RateBreakdown;
use App\ValueObjects\RateLineItem;

function breakdown(int $perUnitSubtotalMinor = 0, int $quantity = 1, array $lineItems = []): RateBreakdown
{
    return new RateBreakdown(
        unitPriceMinor: 10000,
        currency: 'GBP',
        units: 5,
        unitLabel: 'days',
        perUnitSubtotalMinor: $perUnitSubtotalMinor,
        quantity: $quantity,
        lineItems: $lineItems,
        appliedModifiers: [],
    );
}

it('exposes the supplied values as readonly properties', function () {
    $lineItem = new RateLineItem(1, 5, 'Days 1-5', '1.0', 10000, 50000);
    $modifier = [
        'key' => 'factor',
        'label' => 'Factor',
        'description' => 'Qty 6-20',
        'beforeMinor' => 37000,
        'afterMinor' => 33300,
    ];

    $rateBreakdown = new RateBreakdown(
        unitPriceMinor: 10000,
        currency: 'GBP',
        units: 5,
        unitLabel: 'days',
        perUnitSubtotalMinor: 33300,
        quantity: 8,
        lineItems: [$lineItem],
        appliedModifiers: [$modifier],
    );

    expect($rateBreakdown->unitPriceMinor)->toBe(10000)
        ->and($rateBreakdown->currency)->toBe('GBP')
        ->and($rateBreakdown->units)->toBe(5)
        ->and($rateBreakdown->unitLabel)->toBe('days')
        ->and($rateBreakdown->perUnitSubtotalMinor)->toBe(33300)
        ->and($rateBreakdown->quantity)->toBe(8)
        ->and($rateBreakdown->lineItems)->toBe([$lineItem])
        ->and($rateBreakdown->appliedModifiers)->toBe([$modifier]);
});

it('multiplies the per-unit subtotal by the quantity for the total', function () {
    // Spec example 1: per-unit subtotal 33300 x quantity 8 = 266400 (GBP 2,664.00).
    expect(breakdown(33300, 8)->totalMinor())->toBe(266400);
});

it('returns the per-unit subtotal when quantity is one', function () {
    // Spec example 2: per-unit subtotal 36000 x quantity 1 = 36000 (GBP 360.00).
    expect(breakdown(36000, 1)->totalMinor())->toBe(36000);
});

it('returns a new instance when applying a per-unit subtotal without mutating the original', function () {
    $original = breakdown(0, 8, []);
    $newLineItem = new RateLineItem(1, 5, 'Days 1-5', '1.0', 10000, 37000);

    $updated = $original->withPerUnitSubtotal(37000, [$newLineItem]);

    expect($updated)->not->toBe($original)
        ->and($updated->perUnitSubtotalMinor)->toBe(37000)
        ->and($updated->lineItems)->toBe([$newLineItem])
        ->and($updated->totalMinor())->toBe(296000)
        // Original is untouched.
        ->and($original->perUnitSubtotalMinor)->toBe(0)
        ->and($original->lineItems)->toBe([]);
});

it('preserves the other properties when applying a per-unit subtotal', function () {
    $updated = breakdown(0, 8, [])->withPerUnitSubtotal(37000, []);

    expect($updated->unitPriceMinor)->toBe(10000)
        ->and($updated->currency)->toBe('GBP')
        ->and($updated->units)->toBe(5)
        ->and($updated->unitLabel)->toBe('days')
        ->and($updated->quantity)->toBe(8);
});

it('returns a new instance when applying a modifier without mutating the original', function () {
    $original = breakdown(37000, 8, []);
    $modifier = [
        'key' => 'factor',
        'label' => 'Factor',
        'description' => 'Qty 6-20 at 0.9x',
        'beforeMinor' => 37000,
        'afterMinor' => 33300,
    ];

    $updated = $original->withModifierApplied($modifier);

    expect($updated)->not->toBe($original)
        ->and($updated->appliedModifiers)->toBe([$modifier])
        ->and($original->appliedModifiers)->toBe([]);
});

it('appends successive modifiers in application order', function () {
    $first = ['key' => 'multiplier', 'label' => 'Multiplier', 'description' => '', 'beforeMinor' => 50000, 'afterMinor' => 37000];
    $second = ['key' => 'factor', 'label' => 'Factor', 'description' => '', 'beforeMinor' => 37000, 'afterMinor' => 33300];

    $updated = breakdown(33300, 8, [])
        ->withModifierApplied($first)
        ->withModifierApplied($second);

    expect($updated->appliedModifiers)->toBe([$first, $second]);
});

it('serialises to a snake-cased array including the computed total', function () {
    $lineItem = new RateLineItem(1, 5, 'Days 1-5', '1.0', 10000, 50000);
    $modifier = [
        'key' => 'factor',
        'label' => 'Factor',
        'description' => 'Qty 6-20',
        'beforeMinor' => 37000,
        'afterMinor' => 33300,
    ];

    $rateBreakdown = new RateBreakdown(
        unitPriceMinor: 10000,
        currency: 'GBP',
        units: 5,
        unitLabel: 'days',
        perUnitSubtotalMinor: 33300,
        quantity: 8,
        lineItems: [$lineItem],
        appliedModifiers: [$modifier],
    );

    expect($rateBreakdown->toArray())->toBe([
        'unit_price_minor' => 10000,
        'currency' => 'GBP',
        'units' => 5,
        'unit_label' => 'days',
        'per_unit_subtotal_minor' => 33300,
        'quantity' => 8,
        'total_minor' => 266400,
        'line_items' => [$lineItem->toArray()],
        'applied_modifiers' => [$modifier],
    ]);
});
