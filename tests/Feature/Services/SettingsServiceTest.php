<?php

use App\Models\Setting;
use App\Services\SettingsService;

beforeEach(function () {
    $this->service = app(SettingsService::class);
});

it('returns default when setting does not exist', function () {
    expect($this->service->get('company.name', 'default'))->toBe('default');
});

it('returns null when setting does not exist and no default given', function () {
    expect($this->service->get('company.name'))->toBeNull();
});

it('can set and get a string setting', function () {
    $this->service->set('company.name', 'Signals Rental');

    expect($this->service->get('company.name'))->toBe('Signals Rental');
    expect(Setting::query()->forKey('company', 'name')->first()->value)->toBe('Signals Rental');
});

it('can set and get a boolean setting', function () {
    $this->service->set('modules.opportunities', true, 'boolean');

    expect($this->service->get('modules.opportunities'))->toBeTrue();

    $this->service->set('modules.crew', false, 'boolean');

    expect($this->service->get('modules.crew'))->toBeFalse();
});

it('can set and get an integer setting', function () {
    $this->service->set('company.fiscal_year_start', 4, 'integer');

    expect($this->service->get('company.fiscal_year_start'))->toBe(4);
});

it('can set and get a json setting', function () {
    $data = ['primary' => '#000', 'accent' => '#333'];
    $this->service->set('branding.colours', $data, 'json');

    expect($this->service->get('branding.colours'))->toBe($data);
});

it('can set and get an encrypted setting', function () {
    $this->service->set('email.smtp_password', 'secret123', 'encrypted');

    expect($this->service->get('email.smtp_password'))->toBe('secret123');
});

it('can set and get null values', function () {
    $this->service->set('company.logo', null);

    expect($this->service->get('company.logo'))->toBeNull();
});

it('overwrites existing settings', function () {
    $this->service->set('company.name', 'First');
    $this->service->set('company.name', 'Second');

    expect($this->service->get('company.name'))->toBe('Second');
    expect(Setting::query()->where('group', 'company')->where('key', 'name')->count())->toBe(1);
});

it('can set many settings at once', function () {
    $this->service->setMany([
        'company.name' => ['value' => 'Signals Rental', 'type' => 'string'],
        'company.country' => ['value' => 'GB', 'type' => 'string'],
        'modules.opportunities' => ['value' => true, 'type' => 'boolean'],
    ]);

    expect($this->service->get('company.name'))->toBe('Signals Rental');
    expect($this->service->get('company.country'))->toBe('GB');
    expect($this->service->get('modules.opportunities'))->toBeTrue();
});

it('setMany treats simple values as strings', function () {
    $this->service->setMany([
        'company.name' => 'Signals Rental',
    ]);

    expect($this->service->get('company.name'))->toBe('Signals Rental');
});

it('checks if a setting exists with has()', function () {
    expect($this->service->has('company.name'))->toBeFalse();

    $this->service->set('company.name', 'Test');

    expect($this->service->has('company.name'))->toBeTrue();
});

it('returns all settings as a nested array', function () {
    $this->service->setMany([
        'company.name' => 'Test Co',
        'company.country' => 'US',
        'modules.crm' => ['value' => true, 'type' => 'boolean'],
    ]);

    $all = $this->service->all();

    expect($all)->toHaveKey('company')
        ->and($all['company'])->toHaveKey('name', 'Test Co')
        ->and($all['company'])->toHaveKey('country', 'US')
        ->and($all)->toHaveKey('modules')
        ->and($all['modules'])->toHaveKey('crm', true);
});

it('returns all settings for a group when key has no dot', function () {
    $this->service->setMany([
        'modules.crm' => ['value' => true, 'type' => 'boolean'],
        'modules.opportunities' => ['value' => false, 'type' => 'boolean'],
    ]);

    $modules = $this->service->get('modules');

    expect($modules)->toBe(['crm' => true, 'opportunities' => false]);
});

it('checks module enabled status', function () {
    $this->service->set('modules.crm', true, 'boolean');
    $this->service->set('modules.crew', false, 'boolean');

    expect($this->service->moduleEnabled('crm'))->toBeTrue();
    expect($this->service->moduleEnabled('crew'))->toBeFalse();
    expect($this->service->moduleEnabled('nonexistent'))->toBeFalse();
});

it('flushes in-memory cache on set so next get reloads from database', function () {
    $this->service->set('test.key', 'first');
    expect($this->service->get('test.key'))->toBe('first');

    // Directly update the database, bypassing the service
    Setting::query()->where('group', 'test')->where('key', 'key')->update(['value' => 'second']);

    // Flush forces reload from database on next access
    $this->service->flush();
    expect($this->service->get('test.key'))->toBe('second');
});

it('uses the settings() helper to get values', function () {
    $this->service->set('company.name', 'Helper Test');

    expect(settings('company.name'))->toBe('Helper Test');
});

it('returns the service when settings() is called without arguments', function () {
    expect(settings())->toBeInstanceOf(SettingsService::class);
});

it('loads settings from the database via factory', function () {
    Setting::factory()->create(['group' => 'test', 'key' => 'factored', 'value' => 'works']);

    $service = new SettingsService;
    $service->load();

    expect($service->get('test.factored'))->toBe('works');
});

it('handles boolean factory state', function () {
    $setting = Setting::factory()->boolean()->create(['group' => 'modules', 'key' => 'crm']);

    expect($setting->value)->toBe('true');
    expect($setting->type)->toBe('boolean');
});

it('handles integer factory state', function () {
    $setting = Setting::factory()->integer(42)->create(['group' => 'test', 'key' => 'count']);

    expect($setting->value)->toBe('42');
    expect($setting->type)->toBe('integer');
});

it('handles json factory state', function () {
    $setting = Setting::factory()->json(['a' => 1])->create(['group' => 'test', 'key' => 'data']);

    expect($setting->value)->toBe('{"a":1}');
    expect($setting->type)->toBe('json');
});

it('scopes settings by group', function () {
    Setting::factory()->create(['group' => 'company', 'key' => 'name']);
    Setting::factory()->create(['group' => 'modules', 'key' => 'crm']);

    expect(Setting::query()->forGroup('company')->count())->toBe(1);
    expect(Setting::query()->forGroup('modules')->count())->toBe(1);
});

it('scopes settings by key', function () {
    Setting::factory()->create(['group' => 'company', 'key' => 'name']);
    Setting::factory()->create(['group' => 'company', 'key' => 'country']);

    expect(Setting::query()->forKey('company', 'name')->count())->toBe(1);
});
