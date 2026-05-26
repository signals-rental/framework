<?php

use App\Support\ConfigSchema\Fields\DecimalField;
use App\Support\ConfigSchema\Fields\NumberField;
use App\Support\ConfigSchema\Fields\RepeaterField;

function tiersRepeater(): RepeaterField
{
    return RepeaterField::make('tiers')
        ->minItems(1)
        ->fields(DecimalField::make('multiplier')->required());
}

function rangesRepeater(): RepeaterField
{
    return RepeaterField::make('ranges')->fields(
        NumberField::make('from')->required(),
        NumberField::make('to'),
        DecimalField::make('factor')->required(),
    );
}

it('reports the repeater type and exposes its row fields', function () {
    expect(tiersRepeater()->type())->toBe('repeater')
        ->and(tiersRepeater()->getFields())->toHaveCount(1);
});

it('validates the array and each row field at wildcard paths', function () {
    expect(tiersRepeater()->validationRules('', []))->toBe([
        'tiers' => ['nullable', 'array', 'min:1'],
        'tiers.*.multiplier' => ['required', 'numeric'],
    ]);
});

it('validates every row field of a multi-field repeater', function () {
    expect(rangesRepeater()->validationRules('', []))->toBe([
        'ranges' => ['nullable', 'array'],
        'ranges.*.from' => ['required', 'integer'],
        'ranges.*.to' => ['nullable', 'integer'],
        'ranges.*.factor' => ['required', 'numeric'],
    ]);
});

it('marks the array required when set', function () {
    $repeater = RepeaterField::make('tiers')->required()->minItems(1)
        ->fields(DecimalField::make('multiplier'));

    expect($repeater->validationRules('', [])['tiers'])->toBe(['required', 'array', 'min:1']);
});

it('defaults to an empty list of rows', function () {
    expect(tiersRepeater()->defaults())->toBe(['tiers' => []]);
});

it('sanitises and casts each row, reindexing and keeping only known row fields', function () {
    $sanitised = rangesRepeater()->sanitise(['ranges' => [
        5 => ['from' => '1', 'to' => '10', 'factor' => '1.0', 'unknown' => 'x'],
        9 => ['from' => '11', 'to' => null, 'factor' => '0.9'],
    ]]);

    expect($sanitised)->toBe(['ranges' => [
        ['from' => 1, 'to' => 10, 'factor' => '1.0'],
        ['from' => 11, 'to' => null, 'factor' => '0.9'],
    ]]);
});

it('sanitises a missing or non-array repeater value to an empty list', function () {
    expect(tiersRepeater()->sanitise([]))->toBe([])
        ->and(tiersRepeater()->sanitise(['tiers' => 'nonsense']))->toBe(['tiers' => []]);
});

it('excludes a hidden repeater from validation entirely', function () {
    $repeater = RepeaterField::make('tiers')->visibleWhen('on', true)
        ->fields(DecimalField::make('multiplier'));

    expect($repeater->validationRules('', ['on' => false]))->toBe([]);
});

it('skips non-array rows when sanitising', function () {
    $sanitised = tiersRepeater()->sanitise(['tiers' => [
        ['multiplier' => '1.0'],
        'not-a-row',
        ['multiplier' => '0.5'],
    ]]);

    expect($sanitised)->toBe(['tiers' => [
        ['multiplier' => '1.0'],
        ['multiplier' => '0.5'],
    ]]);
});

it('passes a cast value through unchanged (the repeater holds the row array as-is)', function () {
    expect(tiersRepeater()->cast([['multiplier' => '0.5']]))->toBe([['multiplier' => '0.5']]);
});

it('exposes row field metadata and the minimum item count', function () {
    $meta = tiersRepeater()->toArray();

    expect($meta['type'])->toBe('repeater')
        ->and($meta['min_items'])->toBe(1)
        ->and($meta['fields'])->toHaveCount(1)
        ->and($meta['fields'][0]['key'])->toBe('multiplier');
});
