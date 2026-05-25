<?php

use App\Contracts\CalculationStrategy;
use App\Enums\BasePeriod;
use App\Enums\RateTransactionType;
use App\Services\RateEngine\Strategies\PeriodStrategy;
use App\ValueObjects\CalculationContext;
use Illuminate\Support\Carbon;

/**
 * @param  array<string, mixed>  $strategyConfig
 */
function periodContext(
    int $unitPriceMinor = 10000,
    string $start = '2026-01-05 00:00:00',
    string $end = '2026-01-10 00:00:00',
    BasePeriod $basePeriod = BasePeriod::Daily,
    int $quantity = 1,
    array $strategyConfig = [],
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
    expect(new PeriodStrategy)->toBeInstanceOf(CalculationStrategy::class);
});

it('declares its identifier, label, support flags and allowed base periods', function () {
    $strategy = new PeriodStrategy;

    expect($strategy->identifier())->toBe('period')
        ->and($strategy->label())->toBe('Period-based')
        ->and($strategy->supportsMultiplier())->toBeTrue()
        ->and($strategy->supportsFactor())->toBeTrue()
        ->and($strategy->allowedBasePeriods())->toBe([
            BasePeriod::HalfHourly,
            BasePeriod::Hourly,
            BasePeriod::Daily,
            BasePeriod::Weekly,
            BasePeriod::Monthly,
        ]);
});

it('charges per elapsed daily unit (spec example 1 step 1)', function () {
    // Unit price GBP 100.00/day, 5-day rental.
    $breakdown = (new PeriodStrategy)->calculate(periodContext());

    expect($breakdown->units)->toBe(5)
        ->and($breakdown->unitPriceMinor)->toBe(10000)
        ->and($breakdown->currency)->toBe('GBP')
        ->and($breakdown->unitLabel)->toBe('days')
        ->and($breakdown->perUnitSubtotalMinor)->toBe(50000)
        ->and($breakdown->quantity)->toBe(1)
        ->and($breakdown->appliedModifiers)->toBe([]);
});

it('produces a single base line item spanning all units', function () {
    $breakdown = (new PeriodStrategy)->calculate(periodContext());

    expect($breakdown->lineItems)->toHaveCount(1);

    $line = $breakdown->lineItems[0];

    expect($line->periodFrom)->toBe(1)
        ->and($line->periodTo)->toBe(5)
        ->and($line->multiplier)->toBe('1.0')
        ->and($line->unitPriceMinor)->toBe(10000)
        ->and($line->lineTotalMinor)->toBe(50000);
});

it('carries quantity through to the breakdown total', function () {
    $breakdown = (new PeriodStrategy)->calculate(periodContext(quantity: 8));

    expect($breakdown->quantity)->toBe(8)
        ->and($breakdown->perUnitSubtotalMinor)->toBe(50000)
        ->and($breakdown->totalMinor())->toBe(400000);
});

it('honours leeway and last-day cut-off time options (spec example 3)', function () {
    // Daily rate, leeway 30m, last-day cut-off 09:00, Mon 14:00 -> Thu 08:45.
    $breakdown = (new PeriodStrategy)->calculate(periodContext(
        unitPriceMinor: 5000,
        start: '2026-01-05 14:00:00', // Monday
        end: '2026-01-08 08:45:00',   // Thursday, before the 09:00 cut-off
        strategyConfig: [
            'day_type' => 'clock',
            'leeway_minutes' => 30,
            'last_day_cutoff' => '09:00',
        ],
    ));

    expect($breakdown->units)->toBe(3)
        ->and($breakdown->perUnitSubtotalMinor)->toBe(15000);
});

it('coerces the day_type string into the DayType enum for business hours', function () {
    // Two business days of 09:00-17:00 (8h each) at hourly granularity = 16 hours.
    $breakdown = (new PeriodStrategy)->calculate(periodContext(
        unitPriceMinor: 100,
        start: '2026-01-05 09:00:00',
        end: '2026-01-06 17:00:00',
        basePeriod: BasePeriod::Hourly,
        strategyConfig: [
            'day_type' => 'business',
            'business_hours_start' => '09:00',
            'business_hours_end' => '17:00',
        ],
    ));

    expect($breakdown->units)->toBe(16)
        ->and($breakdown->unitLabel)->toBe('hours')
        ->and($breakdown->perUnitSubtotalMinor)->toBe(1600);
});

it('labels each base period sensibly', function (BasePeriod $period, string $label) {
    $breakdown = (new PeriodStrategy)->calculate(periodContext(
        start: '2026-01-05 00:00:00',
        end: '2026-01-05 00:30:00',
        basePeriod: $period,
    ));

    expect($breakdown->unitLabel)->toBe($label);
})->with([
    'half-hourly' => [BasePeriod::HalfHourly, 'half-hours'],
    'hourly' => [BasePeriod::Hourly, 'hours'],
    'daily' => [BasePeriod::Daily, 'days'],
    'weekly' => [BasePeriod::Weekly, 'weeks'],
    'monthly' => [BasePeriod::Monthly, 'months'],
]);
