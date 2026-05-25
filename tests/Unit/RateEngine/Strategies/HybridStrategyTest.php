<?php

use App\Contracts\CalculationStrategy;
use App\Enums\BasePeriod;
use App\Enums\RateTransactionType;
use App\Services\RateEngine\Strategies\HybridStrategy;
use App\ValueObjects\CalculationContext;
use Illuminate\Support\Carbon;

/**
 * @param  array<string, mixed>  $strategyConfig
 */
function hybridContext(
    int $unitPriceMinor = 4000,
    string $start = '2026-01-05 00:00:00',
    string $end = '2026-01-12 00:00:00',
    BasePeriod $basePeriod = BasePeriod::Daily,
    int $quantity = 1,
    array $strategyConfig = ['fixed_charge' => 20000, 'fixed_period_units' => 3],
): CalculationContext {
    return new CalculationContext(
        unitPriceMinor: $unitPriceMinor,
        currency: 'GBP',
        start: Carbon::parse($start),
        end: Carbon::parse($end),
        quantity: $quantity,
        basePeriod: $basePeriod,
        strategyConfig: $strategyConfig,
        transactionType: RateTransactionType::Rental,
    );
}

it('implements the calculation strategy contract', function () {
    expect(new HybridStrategy)->toBeInstanceOf(CalculationStrategy::class);
});

it('declares its identifier, label, support flags and allowed base periods', function () {
    $strategy = new HybridStrategy;

    expect($strategy->identifier())->toBe('hybrid')
        ->and($strategy->label())->toBe('Hybrid')
        ->and($strategy->supportsMultiplier())->toBeFalse()
        ->and($strategy->supportsFactor())->toBeFalse()
        ->and($strategy->allowedBasePeriods())->toBe([
            BasePeriod::Daily,
            BasePeriod::Weekly,
            BasePeriod::Monthly,
        ]);
});

it('charges a fixed block then per-unit subsequent days (spec example 2)', function () {
    // Fixed GBP 200 for first 3 days, then GBP 40/day; 7-day rental.
    $breakdown = (new HybridStrategy)->calculate(hybridContext());

    expect($breakdown->units)->toBe(7)
        ->and($breakdown->unitLabel)->toBe('days')
        ->and($breakdown->unitPriceMinor)->toBe(4000)
        ->and($breakdown->currency)->toBe('GBP')
        // 20000 fixed + (7 - 3) * 4000 = 36000.
        ->and($breakdown->perUnitSubtotalMinor)->toBe(36000)
        ->and($breakdown->quantity)->toBe(1)
        ->and($breakdown->appliedModifiers)->toBe([])
        ->and($breakdown->totalMinor())->toBe(36000);
});

it('produces a fixed-block line item and a subsequent-units line item', function () {
    $breakdown = (new HybridStrategy)->calculate(hybridContext());

    expect($breakdown->lineItems)->toHaveCount(2);

    [$fixed, $subsequent] = $breakdown->lineItems;

    expect($fixed->periodFrom)->toBe(1)
        ->and($fixed->periodTo)->toBe(3)
        ->and($fixed->lineTotalMinor)->toBe(20000);

    expect($subsequent->periodFrom)->toBe(4)
        ->and($subsequent->periodTo)->toBe(7)
        ->and($subsequent->unitPriceMinor)->toBe(4000)
        ->and($subsequent->lineTotalMinor)->toBe(16000);
});

it('charges only the fixed block when the rental fits within the fixed period', function () {
    // 2-day rental, fixed covers 3 days: no subsequent units.
    $breakdown = (new HybridStrategy)->calculate(hybridContext(
        start: '2026-01-05 00:00:00',
        end: '2026-01-07 00:00:00',
    ));

    expect($breakdown->units)->toBe(2)
        ->and($breakdown->perUnitSubtotalMinor)->toBe(20000)
        ->and($breakdown->lineItems)->toHaveCount(1)
        ->and($breakdown->lineItems[0]->periodFrom)->toBe(1)
        ->and($breakdown->lineItems[0]->periodTo)->toBe(2)
        ->and($breakdown->lineItems[0]->lineTotalMinor)->toBe(20000);
});

it('does not produce negative subsequent units when exactly at the fixed period', function () {
    // 3-day rental, fixed covers 3 days: subsequentUnits clamps to 0.
    $breakdown = (new HybridStrategy)->calculate(hybridContext(
        start: '2026-01-05 00:00:00',
        end: '2026-01-08 00:00:00',
    ));

    expect($breakdown->units)->toBe(3)
        ->and($breakdown->perUnitSubtotalMinor)->toBe(20000)
        ->and($breakdown->lineItems)->toHaveCount(1);
});

it('honours the configured base period for subsequent units', function () {
    // Weekly base period over a 21-day rental: 3 weekly units, fixed covers 1.
    $breakdown = (new HybridStrategy)->calculate(hybridContext(
        unitPriceMinor: 10000,
        start: '2026-01-05 00:00:00',
        end: '2026-01-26 00:00:00',
        basePeriod: BasePeriod::Weekly,
        strategyConfig: ['fixed_charge' => 30000, 'fixed_period_units' => 1],
    ));

    expect($breakdown->units)->toBe(3)
        ->and($breakdown->unitLabel)->toBe('weeks')
        // 30000 fixed + (3 - 1) * 10000 = 50000.
        ->and($breakdown->perUnitSubtotalMinor)->toBe(50000);
});

it('carries quantity through to the breakdown total', function () {
    $breakdown = (new HybridStrategy)->calculate(hybridContext(quantity: 5));

    expect($breakdown->quantity)->toBe(5)
        ->and($breakdown->perUnitSubtotalMinor)->toBe(36000)
        ->and($breakdown->totalMinor())->toBe(180000);
});
