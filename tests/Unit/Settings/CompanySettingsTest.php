<?php

use App\Settings\CompanySettings;

it('returns company as the group', function () {
    $settings = new CompanySettings;

    expect($settings->group())->toBe('company');
});

it('provides default values for all settings', function () {
    $settings = new CompanySettings;
    $defaults = $settings->defaults();

    expect($defaults)
        ->toHaveKeys(['name', 'timezone', 'date_format', 'date_format_php', 'date_format_js', 'time_format', 'time_format_php', 'number_format', 'fiscal_year_start'])
        ->and($defaults['timezone'])->toBe('UTC')
        ->and($defaults['fiscal_year_start'])->toBe(1);
});

it('provides validation rules for all settings', function () {
    $settings = new CompanySettings;
    $rules = $settings->rules();

    expect($rules)
        ->toHaveKeys(['name', 'timezone', 'date_format', 'fiscal_year_start'])
        ->and($rules['name'])->toContain('required')
        ->and($rules['timezone'])->toContain('timezone:all');
});

it('defines types for non-string settings', function () {
    $settings = new CompanySettings;
    $types = $settings->types();

    expect($types)->toHaveKey('fiscal_year_start', 'integer');
});
