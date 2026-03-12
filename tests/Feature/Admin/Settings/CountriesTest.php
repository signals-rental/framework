<?php

use App\Models\Country;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the countries page', function () {
    $this->get(route('admin.settings.countries'))
        ->assertOk()
        ->assertSee('Countries');
});

it('lists countries', function () {
    Country::factory()->create(['name' => 'United Kingdom']);
    Country::factory()->create(['name' => 'France']);

    Volt::test('admin.settings.countries')
        ->assertSee('United Kingdom')
        ->assertSee('France');
});

it('can toggle active status', function () {
    $country = Country::factory()->create(['is_active' => true]);

    Volt::test('admin.settings.countries')
        ->call('toggleActive', $country->id);

    expect($country->fresh()->is_active)->toBeFalse();
});

it('can search countries', function () {
    Country::factory()->create(['name' => 'United Kingdom']);
    Country::factory()->create(['name' => 'France']);

    Volt::test('admin.settings.countries')
        ->set('search', 'United')
        ->assertSee('United Kingdom')
        ->assertDontSee('France');
})->skip(fn () => config('database.default') === 'sqlite', 'ilike requires PostgreSQL');

it('can toggle country back to active', function () {
    $country = Country::factory()->create(['is_active' => false]);

    Volt::test('admin.settings.countries')
        ->call('toggleActive', $country->id);

    expect($country->fresh()->is_active)->toBeTrue();
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.countries'))
        ->assertForbidden();
});
