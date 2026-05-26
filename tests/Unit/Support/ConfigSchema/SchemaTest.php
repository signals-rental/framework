<?php

use App\Support\ConfigSchema\Fields\GroupField;
use App\Support\ConfigSchema\Fields\NumberField;
use App\Support\ConfigSchema\Fields\SelectField;
use App\Support\ConfigSchema\Fields\TimeField;
use App\Support\ConfigSchema\Schema;
use App\Support\ConfigSchema\Section;

function optionsSchema(): Schema
{
    return Schema::make(
        SelectField::make('day_type')->options(['clock' => 'Clock', 'business' => 'Business'])->default('clock'),
        GroupField::make('business_hours')
            ->fields(
                TimeField::make('business_hours_start')->required()->default('09:00'),
                TimeField::make('business_hours_end')->required()->default('17:00'),
            )
            ->visibleWhen('day_type', 'business'),
        NumberField::make('leeway_minutes')->default(0)->min(0),
    );
}

it('exposes its ordered fields', function () {
    expect(optionsSchema()->fields())->toHaveCount(3);
});

it('aggregates validation rules across fields, excluding a hidden group', function () {
    expect(optionsSchema()->validationRules(['day_type' => 'clock']))->toBe([
        'day_type' => ['nullable', 'in:clock,business'],
        'leeway_minutes' => ['nullable', 'integer', 'min:0'],
    ]);
});

it('includes a visible group\'s children in the aggregated rules', function () {
    expect(optionsSchema()->validationRules(['day_type' => 'business']))->toBe([
        'day_type' => ['nullable', 'in:clock,business'],
        'business_hours_start' => ['required', 'date_format:H:i'],
        'business_hours_end' => ['required', 'date_format:H:i'],
        'leeway_minutes' => ['nullable', 'integer', 'min:0'],
    ]);
});

it('aggregates defaults across fields and groups', function () {
    expect(optionsSchema()->defaults())->toBe([
        'day_type' => 'clock',
        'business_hours_start' => '09:00',
        'business_hours_end' => '17:00',
        'leeway_minutes' => 0,
    ]);
});

it('sanitises visible values and strips config for hidden fields', function () {
    $sanitised = optionsSchema()->sanitise([
        'day_type' => 'clock',
        'business_hours_start' => '09:00', // hidden because day_type is clock -> dropped
        'leeway_minutes' => '30',
    ]);

    expect($sanitised)->toBe([
        'day_type' => 'clock',
        'leeway_minutes' => 30,
    ]);
});

it('keeps a visible group\'s values on sanitisation, cast to type', function () {
    $sanitised = optionsSchema()->sanitise([
        'day_type' => 'business',
        'business_hours_start' => '09:00',
        'business_hours_end' => '17:00',
        'leeway_minutes' => '15',
    ]);

    expect($sanitised)->toBe([
        'day_type' => 'business',
        'business_hours_start' => '09:00',
        'business_hours_end' => '17:00',
        'leeway_minutes' => 15,
    ]);
});

it('produces ordered field metadata', function () {
    $meta = optionsSchema()->toArray();

    expect($meta)->toHaveCount(3)
        ->and(array_column($meta, 'key'))->toBe(['day_type', 'business_hours', 'leeway_minutes']);
});

it('treats an empty schema as no rules and no defaults', function () {
    $schema = Schema::make();

    expect($schema->validationRules([]))->toBe([])
        ->and($schema->defaults())->toBe([])
        ->and($schema->isEmpty())->toBeTrue();
});

it('wraps a schema in a labelled section', function () {
    $section = new Section('options', 'Options', optionsSchema());

    expect($section->key)->toBe('options')
        ->and($section->label)->toBe('Options')
        ->and($section->schema)->toBeInstanceOf(Schema::class);

    $array = $section->toArray();

    expect($array['key'])->toBe('options')
        ->and($array['label'])->toBe('Options')
        ->and($array['fields'])->toHaveCount(3);
});
