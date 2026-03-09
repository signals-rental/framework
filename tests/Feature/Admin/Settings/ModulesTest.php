<?php

use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
});

it('renders the modules page', function () {
    $this->get(route('admin.settings.modules'))
        ->assertOk()
        ->assertSee('Modules');
});

it('loads module states from settings', function () {
    settings()->set('modules.opportunities', true, 'boolean');
    settings()->set('modules.stock', false, 'boolean');

    Volt::test('admin.settings.modules')
        ->assertSet('modules.opportunities', true)
        ->assertSet('modules.stock', false)
        ->assertSet('modules.crm', true);
});

it('can toggle a module on', function () {
    Volt::test('admin.settings.modules')
        ->call('toggle', 'opportunities');

    expect(settings('modules.opportunities'))->toBeTrue();
});

it('can toggle a module off', function () {
    settings()->set('modules.opportunities', true, 'boolean');

    Volt::test('admin.settings.modules')
        ->call('toggle', 'opportunities');

    expect(settings('modules.opportunities'))->toBeFalse();
});

it('cannot toggle CRM module', function () {
    Volt::test('admin.settings.modules')
        ->call('toggle', 'crm')
        ->assertSet('modules.crm', true);
});

it('ignores toggle for invalid module key', function () {
    Volt::test('admin.settings.modules')
        ->call('toggle', 'nonexistent_module');

    expect(settings('modules.nonexistent_module'))->toBeNull();
});

it('displays all module cards', function () {
    Volt::test('admin.settings.modules')
        ->assertSee('CRM')
        ->assertSee('Opportunities')
        ->assertSee('Products')
        ->assertSee('Stock')
        ->assertSee('Invoicing')
        ->assertSee('Crew')
        ->assertSee('Services')
        ->assertSee('Projects')
        ->assertSee('Inspections');
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.modules'))
        ->assertForbidden();
});
