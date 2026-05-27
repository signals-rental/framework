<?php

use App\Support\ConfigSchema\Field;
use App\Support\ConfigSchema\Fields\DecimalField;
use App\Support\ConfigSchema\Fields\NumberField;
use App\Support\ConfigSchema\Fields\SelectField;
use App\Support\ConfigSchema\Fields\TextField;
use App\Support\ConfigSchema\Fields\TimeField;
use App\Support\ConfigSchema\Fields\ToggleField;

it('builds a field via the static factory carrying its key', function () {
    expect(TextField::make('label')->key)->toBe('label')
        ->and(TextField::make('label'))->toBeInstanceOf(Field::class);
});

it('derives a humanised label from the key by default', function () {
    expect(TextField::make('business_hours_start')->getLabel())->toBe('Business Hours Start');
});

it('accepts fluent metadata overrides', function () {
    $field = TextField::make('name')
        ->label('Custom')
        ->help('Some help')
        ->placeholder('Type here')
        ->default('x');

    expect($field->getLabel())->toBe('Custom')
        ->and($field->getDefault())->toBe('x');
});

it('reports its type per concrete field', function () {
    expect(TextField::make('a')->type())->toBe('text')
        ->and(NumberField::make('a')->type())->toBe('number')
        ->and(DecimalField::make('a')->type())->toBe('decimal')
        ->and(ToggleField::make('a')->type())->toBe('toggle')
        ->and(SelectField::make('a')->type())->toBe('select')
        ->and(TimeField::make('a')->type())->toBe('time');
});

it('marks a field nullable by default and required when set', function () {
    expect(TextField::make('a')->validationRules('', []))->toBe(['a' => ['nullable', 'string']])
        ->and(TextField::make('a')->required()->validationRules('', []))->toBe(['a' => ['required', 'string']]);
});

it('prefixes the rule path for nested fields', function () {
    expect(NumberField::make('from')->validationRules('ranges.*', []))
        ->toBe(['ranges.*.from' => ['nullable', 'integer']]);
});

it('appends min/max integer rules for number fields', function () {
    expect(NumberField::make('qty')->min(1)->max(10)->validationRules('', []))
        ->toBe(['qty' => ['nullable', 'integer', 'min:1', 'max:10']]);
});

it('appends length rules for text fields', function () {
    expect(TextField::make('code')->minLength(2)->maxLength(8)->validationRules('', []))
        ->toBe(['code' => ['nullable', 'string', 'min:2', 'max:8']]);
});

it('validates decimals as numeric', function () {
    expect(DecimalField::make('multiplier')->validationRules('', []))
        ->toBe(['multiplier' => ['nullable', 'numeric']]);
});

it('validates toggles as boolean', function () {
    expect(ToggleField::make('active')->validationRules('', []))
        ->toBe(['active' => ['nullable', 'boolean']]);
});

it('constrains select fields to their option keys', function () {
    $field = SelectField::make('day_type')->options(['clock' => 'Clock', 'business' => 'Business']);

    $rules = $field->validationRules('', []);

    // The rule is an Illuminate "in" rule object (Rule::in) rather than a raw
    // string, so option keys containing commas or whitespace can't corrupt it.
    expect($rules['day_type'][0])->toBe('nullable')
        ->and((string) $rules['day_type'][1])->toBe('in:"clock","business"');
});

it('validates time fields against the HH:MM format', function () {
    expect(TimeField::make('cutoff')->validationRules('', []))
        ->toBe(['cutoff' => ['nullable', 'date_format:H:i']]);
});

it('appends extra custom rules last', function () {
    expect(NumberField::make('qty')->required()->rules(['gt:0'])->validationRules('', []))
        ->toBe(['qty' => ['required', 'integer', 'gt:0']]);
});

it('hides a field whose single visibility condition is unmet, excluding it from validation', function () {
    $field = TimeField::make('business_hours_start')->visibleWhen('day_type', 'business');

    expect($field->isVisible(['day_type' => 'clock']))->toBeFalse()
        ->and($field->validationRules('', ['day_type' => 'clock']))->toBe([])
        ->and($field->isVisible(['day_type' => 'business']))->toBeTrue()
        ->and($field->validationRules('', ['day_type' => 'business']))->toBe(['business_hours_start' => ['nullable', 'date_format:H:i']]);
});

it('requires every condition to match when multiple are declared (AND)', function () {
    $field = TextField::make('x')
        ->visibleWhen('a', '1')
        ->visibleWhen('b', '2');

    expect($field->isVisible(['a' => '1', 'b' => '2']))->toBeTrue()
        ->and($field->isVisible(['a' => '1', 'b' => '9']))->toBeFalse()
        ->and($field->isVisible(['a' => '1']))->toBeFalse();
});

it('casts values to their typed form', function () {
    expect(NumberField::make('a')->cast('5'))->toBe(5)
        ->and(DecimalField::make('a')->cast(0.5))->toBe('0.5')
        ->and(ToggleField::make('a')->cast('1'))->toBeTrue()
        ->and(ToggleField::make('a')->cast('false'))->toBeFalse()
        ->and(TextField::make('a')->cast(42))->toBe('42')
        ->and(TimeField::make('a')->cast('09:00'))->toBe('09:00');
});

it('passes null through casting untouched', function () {
    expect(NumberField::make('a')->cast(null))->toBeNull()
        ->and(TextField::make('a')->cast(null))->toBeNull();
});

it('reports whether it is required', function () {
    expect(NumberField::make('a')->isRequired())->toBeFalse()
        ->and(NumberField::make('a')->required()->isRequired())->toBeTrue();
});

it('sanitises a visible present value and drops hidden or absent ones', function () {
    // Visible and present: cast and keep.
    expect(NumberField::make('qty')->sanitise(['qty' => '5']))->toBe(['qty' => 5]);

    // Visible but absent from input: contribute nothing.
    expect(NumberField::make('qty')->sanitise([]))->toBe([]);

    // Hidden by an unmet condition: dropped even when present.
    expect(NumberField::make('qty')->visibleWhen('on', true)->sanitise(['on' => false, 'qty' => '5']))->toBe([]);
});

it('exposes field metadata as an array', function () {
    $meta = SelectField::make('day_type')
        ->label('Day Type')
        ->default('clock')
        ->options(['clock' => 'Clock', 'business' => 'Business'])
        ->toArray();

    expect($meta['key'])->toBe('day_type')
        ->and($meta['type'])->toBe('select')
        ->and($meta['label'])->toBe('Day Type')
        ->and($meta['default'])->toBe('clock')
        ->and($meta['required'])->toBeFalse()
        ->and($meta['options'])->toBe(['clock' => 'Clock', 'business' => 'Business']);
});

it('includes visibility conditions in metadata', function () {
    $meta = TimeField::make('business_hours_start')->visibleWhen('day_type', 'business')->toArray();

    expect($meta['visible_when'])->toBe([['field' => 'day_type', 'value' => 'business']]);
});

it('exposes type-specific metadata for text, number and decimal fields', function () {
    expect(TextField::make('a')->minLength(2)->maxLength(8)->toArray())
        ->toMatchArray(['type' => 'text', 'min_length' => 2, 'max_length' => 8]);

    expect(NumberField::make('a')->min(1)->max(9)->step(2)->toArray())
        ->toMatchArray(['type' => 'number', 'min' => 1, 'max' => 9, 'step' => 2]);

    expect(DecimalField::make('a')->decimals(4)->toArray())
        ->toMatchArray(['type' => 'decimal', 'decimals' => 4]);
});
