<?php

use App\Support\ConfigSchema\Fields\GroupField;
use App\Support\ConfigSchema\Fields\TimeField;
use App\Support\ConfigSchema\Fields\ToggleField;

function businessHoursGroup(): GroupField
{
    return GroupField::make('business_hours')
        ->fields(
            TimeField::make('business_hours_start')->required(),
            TimeField::make('business_hours_end')->required(),
        )
        ->visibleWhen('day_type', 'business');
}

it('reports the group type and exposes its children', function () {
    $group = businessHoursGroup();

    expect($group->type())->toBe('group')
        ->and($group->getFields())->toHaveCount(2);
});

it('validates its children with flat keys when the group is visible', function () {
    expect(businessHoursGroup()->validationRules('', ['day_type' => 'business']))->toBe([
        'business_hours_start' => ['required', 'date_format:H:i'],
        'business_hours_end' => ['required', 'date_format:H:i'],
    ]);
});

it('excludes all children from validation when the group is hidden', function () {
    expect(businessHoursGroup()->validationRules('', ['day_type' => 'clock']))->toBe([]);
});

it('still honours a child-level visibility condition within a visible group', function () {
    $group = GroupField::make('opts')->fields(
        ToggleField::make('flag'),
        TimeField::make('at')->visibleWhen('flag', true),
    );

    $rules = $group->validationRules('', ['flag' => false]);

    expect($rules)->toHaveKey('flag')
        ->and($rules)->not->toHaveKey('at');
});

it('merges its children defaults', function () {
    $group = GroupField::make('opts')->fields(
        TimeField::make('start')->default('09:00'),
        TimeField::make('end')->default('17:00'),
    );

    expect($group->defaults())->toBe(['start' => '09:00', 'end' => '17:00']);
});

it('sanitises its children flat when visible and drops everything when hidden', function () {
    $group = businessHoursGroup();

    expect($group->sanitise(['day_type' => 'business', 'business_hours_start' => '09:00', 'business_hours_end' => '17:00']))
        ->toBe(['business_hours_start' => '09:00', 'business_hours_end' => '17:00'])
        ->and($group->sanitise(['day_type' => 'clock', 'business_hours_start' => '09:00']))
        ->toBe([]);
});

it('passes a cast value through unchanged (groups hold no scalar value)', function () {
    expect(GroupField::make('g')->cast('x'))->toBe('x');
});

it('exposes nested field metadata and its own visibility', function () {
    $meta = businessHoursGroup()->toArray();

    expect($meta['type'])->toBe('group')
        ->and($meta['fields'])->toHaveCount(2)
        ->and($meta['fields'][0]['key'])->toBe('business_hours_start')
        ->and($meta['visible_when'])->toBe([['field' => 'day_type', 'value' => 'business']]);
});
