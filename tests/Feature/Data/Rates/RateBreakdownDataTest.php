<?php

use App\Data\Rates\CalculateRateData;
use App\Data\Rates\RateBreakdownData;
use App\Enums\BasePeriod;
use App\Enums\RateTransactionType;
use App\Services\RateEngine\RateCalculator;
use App\ValueObjects\CalculationContext;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('builds a calculate DTO from valid input', function () {
    $data = CalculateRateData::from([
        'quantity' => 8,
        'start' => '2026-01-05T00:00:00Z',
        'end' => '2026-01-10T00:00:00Z',
        'transaction_type' => 'rental',
    ]);

    expect($data->quantity)->toBe(8)
        ->and($data->transaction_type)->toBe(RateTransactionType::Rental)
        ->and($data->store_id)->toBeNull();
});

it('requires a positive quantity and a valid date range', function (array $payload) {
    CalculateRateData::validate($payload);
})->throws(ValidationException::class)->with([
    'zero quantity' => [['quantity' => 0, 'start' => '2026-01-05', 'end' => '2026-01-10']],
    'end before start' => [['quantity' => 1, 'start' => '2026-01-10', 'end' => '2026-01-05']],
    'missing dates' => [['quantity' => 1]],
]);

it('serialises a rate breakdown with money as decimal strings (spec example 1)', function () {
    $context = new CalculationContext(
        unitPriceMinor: 10000,
        currency: 'GBP',
        start: Carbon::parse('2026-01-05 00:00:00'),
        end: Carbon::parse('2026-01-10 00:00:00'),
        quantity: 8,
        basePeriod: BasePeriod::Daily,
        strategyConfig: [],
        transactionType: RateTransactionType::Rental,
    );

    $breakdown = app(RateCalculator::class)->calculate(
        context: $context,
        strategy: 'period',
        enabledModifiers: ['multiplier', 'factor'],
        modifierConfigs: [
            'multiplier' => ['tiers' => [
                ['multiplier' => '1.0'], ['multiplier' => '1.0'], ['multiplier' => '0.7'], ['multiplier' => '0.5'],
            ]],
            'factor' => ['ranges' => [
                ['from' => 1, 'to' => 5, 'factor' => '1.0'],
                ['from' => 6, 'to' => 20, 'factor' => '0.9'],
                ['from' => 21, 'to' => null, 'factor' => '0.8'],
            ]],
        ],
    );

    $dto = RateBreakdownData::fromBreakdown($breakdown);

    expect($dto->currency)->toBe('GBP')
        ->and($dto->unit_price)->toBe('100.00')
        ->and($dto->units)->toBe(5)
        ->and($dto->unit_label)->toBe('days')
        ->and($dto->per_unit_subtotal)->toBe('333.00')
        ->and($dto->quantity)->toBe(8)
        ->and($dto->total)->toBe('2664.00');
});

it('exposes line items with formatted money and no raw minor units', function () {
    $context = new CalculationContext(
        unitPriceMinor: 10000,
        currency: 'GBP',
        start: Carbon::parse('2026-01-05 00:00:00'),
        end: Carbon::parse('2026-01-10 00:00:00'),
        quantity: 1,
        basePeriod: BasePeriod::Daily,
        strategyConfig: [],
        transactionType: RateTransactionType::Rental,
    );

    $breakdown = app(RateCalculator::class)->calculate(
        context: $context,
        strategy: 'period',
        enabledModifiers: ['multiplier'],
        modifierConfigs: ['multiplier' => ['tiers' => [
            ['multiplier' => '1.0'], ['multiplier' => '1.0'], ['multiplier' => '0.5'],
        ]]],
    );

    $dto = RateBreakdownData::fromBreakdown($breakdown);

    // Days 1-2 @ 1.0 (£100), Day 3-5 @ 0.5 (£50).
    expect($dto->line_items[0])->toMatchArray([
        'period_from' => 1,
        'period_to' => 2,
        'multiplier' => '1.0',
        'unit_price' => '100.00',
        'line_total' => '200.00',
    ])
        ->and($dto->line_items[1])->toMatchArray([
            'period_from' => 3,
            'period_to' => 5,
            'unit_price' => '50.00',
            'line_total' => '150.00',
        ]);
});

it('exposes applied modifiers with before/after as decimal strings', function () {
    $context = new CalculationContext(
        unitPriceMinor: 10000,
        currency: 'GBP',
        start: Carbon::parse('2026-01-05 00:00:00'),
        end: Carbon::parse('2026-01-10 00:00:00'),
        quantity: 8,
        basePeriod: BasePeriod::Daily,
        strategyConfig: [],
        transactionType: RateTransactionType::Rental,
    );

    $breakdown = app(RateCalculator::class)->calculate(
        context: $context,
        strategy: 'period',
        enabledModifiers: ['factor'],
        modifierConfigs: ['factor' => ['ranges' => [['from' => 1, 'to' => null, 'factor' => '0.9']]]],
    );

    $dto = RateBreakdownData::fromBreakdown($breakdown);

    expect($dto->applied_modifiers[0]['key'])->toBe('factor')
        ->and($dto->applied_modifiers[0]['before'])->toBe('500.00')
        ->and($dto->applied_modifiers[0]['after'])->toBe('450.00');
});
