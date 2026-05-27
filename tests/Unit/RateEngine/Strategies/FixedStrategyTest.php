<?php

use App\Contracts\CalculationStrategy;
use App\Enums\RateTransactionType;
use App\Services\RateEngine\Strategies\FixedStrategy;
use App\ValueObjects\CalculationContext;
use Illuminate\Support\Carbon;

function fixedContext(
    int $unitPriceMinor = 7500,
    string $start = '2026-01-05 00:00:00',
    string $end = '2026-01-12 00:00:00',
    int $quantity = 1,
): CalculationContext {
    return new CalculationContext(
        unitPriceMinor: $unitPriceMinor,
        currency: 'GBP',
        start: Carbon::parse($start),
        end: Carbon::parse($end),
        quantity: $quantity,
        basePeriod: null,
        strategyConfig: [],
        transactionType: RateTransactionType::Sale,
    );
}

it('implements the calculation strategy contract', function () {
    expect(new FixedStrategy)->toBeInstanceOf(CalculationStrategy::class);
});

it('declares its identifier, label, support flags and allowed base periods', function () {
    $strategy = new FixedStrategy;

    expect($strategy->identifier())->toBe('fixed')
        ->and($strategy->label())->toBe('Fixed')
        ->and($strategy->supportsMultiplier())->toBeFalse()
        ->and($strategy->supportsFactor())->toBeTrue()
        ->and($strategy->allowedBasePeriods())->toBe([]);
});

it('charges a single flat unit regardless of duration', function () {
    $breakdown = (new FixedStrategy)->calculate(fixedContext());

    expect($breakdown->units)->toBe(1)
        ->and($breakdown->unitLabel)->toBe('fixed')
        ->and($breakdown->unitPriceMinor)->toBe(7500)
        ->and($breakdown->currency)->toBe('GBP')
        ->and($breakdown->perUnitSubtotalMinor)->toBe(7500)
        ->and($breakdown->quantity)->toBe(1)
        ->and($breakdown->appliedModifiers)->toBe([]);
});

it('is independent of the rental window length', function () {
    $oneDay = (new FixedStrategy)->calculate(fixedContext(
        start: '2026-01-05 00:00:00',
        end: '2026-01-06 00:00:00',
    ));

    $thirtyDays = (new FixedStrategy)->calculate(fixedContext(
        start: '2026-01-05 00:00:00',
        end: '2026-02-04 00:00:00',
    ));

    expect($oneDay->perUnitSubtotalMinor)->toBe(7500)
        ->and($thirtyDays->perUnitSubtotalMinor)->toBe(7500)
        ->and($oneDay->units)->toBe(1)
        ->and($thirtyDays->units)->toBe(1);
});

it('produces a single line item for the flat charge', function () {
    $breakdown = (new FixedStrategy)->calculate(fixedContext());

    expect($breakdown->lineItems)->toHaveCount(1);

    $line = $breakdown->lineItems[0];

    expect($line->periodFrom)->toBe(1)
        ->and($line->periodTo)->toBe(1)
        ->and($line->multiplier)->toBe('1.0')
        ->and($line->unitPriceMinor)->toBe(7500)
        ->and($line->lineTotalMinor)->toBe(7500);
});

it('carries quantity through to the breakdown total', function () {
    $breakdown = (new FixedStrategy)->calculate(fixedContext(quantity: 4));

    expect($breakdown->quantity)->toBe(4)
        ->and($breakdown->perUnitSubtotalMinor)->toBe(7500)
        ->and($breakdown->totalMinor())->toBe(30000);
});
