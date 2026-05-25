<?php

use App\Enums\BasePeriod;
use App\Enums\RateTransactionType;
use App\ValueObjects\CalculationContext;
use Illuminate\Support\Carbon;

it('exposes the supplied values as readonly properties', function () {
    $start = Carbon::parse('2026-01-15 14:00:00');
    $end = Carbon::parse('2026-01-20 14:00:00');

    $context = new CalculationContext(
        unitPriceMinor: 10000,
        currency: 'GBP',
        start: $start,
        end: $end,
        quantity: 8,
        basePeriod: BasePeriod::Daily,
        strategyConfig: ['leeway_minutes' => 30],
        transactionType: RateTransactionType::Rental,
        storeId: 3,
        usageUnits: 4,
        extra: ['note' => 'seasonal'],
    );

    expect($context->unitPriceMinor)->toBe(10000)
        ->and($context->currency)->toBe('GBP')
        ->and($context->start)->toBe($start)
        ->and($context->end)->toBe($end)
        ->and($context->quantity)->toBe(8)
        ->and($context->basePeriod)->toBe(BasePeriod::Daily)
        ->and($context->strategyConfig)->toBe(['leeway_minutes' => 30])
        ->and($context->transactionType)->toBe(RateTransactionType::Rental)
        ->and($context->storeId)->toBe(3)
        ->and($context->usageUnits)->toBe(4)
        ->and($context->extra)->toBe(['note' => 'seasonal']);
});

it('applies sensible defaults for optional values', function () {
    $context = new CalculationContext(
        unitPriceMinor: 5000,
        currency: 'USD',
        start: Carbon::parse('2026-01-01 00:00:00'),
        end: Carbon::parse('2026-01-02 00:00:00'),
        quantity: 1,
        basePeriod: null,
        strategyConfig: [],
        transactionType: RateTransactionType::Sale,
    );

    expect($context->basePeriod)->toBeNull()
        ->and($context->storeId)->toBeNull()
        ->and($context->usageUnits)->toBeNull()
        ->and($context->extra)->toBe([]);
});
