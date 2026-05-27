<?php

use App\Models\RateDefinition;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('renders the create page with the preset grid and From Scratch option', function () {
    $this->get(route('admin.settings.rate-definitions.create'))
        ->assertOk()
        ->assertSee('Daily Rate')
        ->assertSee('From Scratch');
});

it('prefills the form when a preset is chosen', function () {
    Volt::test('admin.settings.rate-definition-form')
        ->call('choosePreset', 'daily-multiplier-factor')
        ->assertSet('presetChosen', true)
        ->assertSet('name', 'Daily Multiplier and Factor')
        ->assertSet('calculationStrategy', 'period')
        ->assertSet('basePeriod', 'daily')
        ->assertSet('enabledModifiers', ['multiplier', 'factor']);
});

it('starts blank when building from scratch', function () {
    Volt::test('admin.settings.rate-definition-form')
        ->call('fromScratch')
        ->assertSet('presetChosen', true)
        ->assertSet('calculationStrategy', 'period');
});

it('creates a rate definition from a preset', function () {
    Volt::test('admin.settings.rate-definition-form')
        ->call('choosePreset', 'fixed-rate')
        ->set('name', 'My Flat Rate')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.settings.rate-definitions'));

    $this->assertDatabaseHas('rate_definitions', [
        'name' => 'My Flat Rate',
        'calculation_strategy' => 'fixed',
        'is_preset' => false,
    ]);
});

it('creates a rate definition from scratch', function () {
    Volt::test('admin.settings.rate-definition-form')
        ->call('fromScratch')
        ->set('name', 'Scratch Daily')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.settings.rate-definitions'));

    $this->assertDatabaseHas('rate_definitions', ['name' => 'Scratch Daily', 'is_preset' => false]);
});

it('validates that a name is required', function () {
    Volt::test('admin.settings.rate-definition-form')
        ->call('fromScratch')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

it('populates the form when editing an existing definition', function () {
    $definition = RateDefinition::factory()->create([
        'name' => 'Existing Rate',
        'enabled_modifiers' => ['multiplier'],
    ]);

    Volt::test('admin.settings.rate-definition-form', ['rateDefinition' => $definition])
        ->assertSet('presetChosen', true)
        ->assertSet('name', 'Existing Rate')
        ->assertSet('calculationStrategy', 'period')
        ->assertSet('enabledModifiers', ['multiplier']);
});

it('updates an existing definition', function () {
    $definition = RateDefinition::factory()->create(['name' => 'Old Name']);

    Volt::test('admin.settings.rate-definition-form', ['rateDefinition' => $definition])
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.settings.rate-definitions'));

    expect($definition->fresh()->name)->toBe('New Name');
});

it('enables and disables a modifier via the checkbox binding', function () {
    $component = Volt::test('admin.settings.rate-definition-form')
        ->call('choosePreset', 'daily-rate')
        ->set('enabledModifiers', ['multiplier'])
        ->assertSet('enabledModifiers', ['multiplier']);

    // Enabling a modifier seeds its config defaults.
    expect($component->get('modifierConfigs'))->toHaveKey('multiplier');

    // Disabling it strips its config back out.
    $component->set('enabledModifiers', [])
        ->assertSet('enabledModifiers', []);

    expect($component->get('modifierConfigs'))->not->toHaveKey('multiplier');
});

it('adds and removes repeater rows', function () {
    $component = Volt::test('admin.settings.rate-definition-form')
        ->call('choosePreset', 'daily-multiplier-factor')
        ->call('addRow', 'modifierConfigs.multiplier.tiers');

    expect($component->get('modifierConfigs')['multiplier']['tiers'])->toHaveCount(1);

    $component->call('removeRow', 'modifierConfigs.multiplier.tiers', 0);

    expect($component->get('modifierConfigs')['multiplier']['tiers'])->toHaveCount(0);
});

it('forbids non-admin users from the create page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.settings.rate-definitions.create'))
        ->assertForbidden();
});
