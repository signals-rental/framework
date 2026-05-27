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

it('renders the rate definitions admin page', function () {
    $this->get(route('admin.settings.rate-definitions'))
        ->assertOk()
        ->assertSee('Rate Definitions');
});

it('lists rate definitions with a preset/custom badge', function () {
    RateDefinition::factory()->preset()->create(['name' => 'Daily Rate Preset']);
    RateDefinition::factory()->create(['name' => 'My Custom Rate']);

    $this->get(route('admin.settings.rate-definitions'))
        ->assertSee('Daily Rate Preset')
        ->assertSee('My Custom Rate')
        ->assertSee('Preset')
        ->assertSee('Custom');
});

it('deletes a custom rate definition', function () {
    $definition = RateDefinition::factory()->create(['name' => 'Disposable']);

    Volt::test('admin.settings.rate-definitions')
        ->call('deleteDefinition', $definition->id);

    $this->assertDatabaseMissing('rate_definitions', ['id' => $definition->id]);
});

it('forbids non-admin users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.settings.rate-definitions'))
        ->assertForbidden();
});

it('requires authentication', function () {
    auth()->logout();

    $this->get(route('admin.settings.rate-definitions'))
        ->assertRedirect();
});
