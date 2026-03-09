<?php

use App\Models\Store;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
});

it('renders the stores page', function () {
    $this->get(route('admin.settings.stores'))
        ->assertOk()
        ->assertSee('Stores');
});

it('lists existing stores', function () {
    Store::factory()->create(['name' => 'London Warehouse']);
    Store::factory()->create(['name' => 'Manchester Depot']);

    Volt::test('admin.settings.stores')
        ->assertSee('London Warehouse')
        ->assertSee('Manchester Depot');
});

it('can create a store', function () {
    settings()->set('company.country_code', 'GB');

    Volt::test('admin.settings.stores')
        ->call('openCreateModal')
        ->assertDispatched('open-store-modal')
        ->set('storeName', 'New Store')
        ->set('storeCity', 'London')
        ->set('storeCountryCode', 'GB')
        ->call('saveStore')
        ->assertDispatched('close-store-modal');

    expect(Store::where('name', 'New Store')->exists())->toBeTrue();
});

it('sets first store as default automatically', function () {
    settings()->set('company.country_code', 'GB');

    Volt::test('admin.settings.stores')
        ->call('openCreateModal')
        ->set('storeName', 'First Store')
        ->set('storeCountryCode', 'GB')
        ->call('saveStore');

    expect(Store::where('name', 'First Store')->first()->is_default)->toBeTrue();
});

it('can set a store as default', function () {
    $store1 = Store::factory()->default()->create(['name' => 'Store A']);
    $store2 = Store::factory()->create(['name' => 'Store B']);

    Volt::test('admin.settings.stores')
        ->call('setDefault', $store2->id);

    expect($store1->fresh()->is_default)->toBeFalse();
    expect($store2->fresh()->is_default)->toBeTrue();
});

it('validates required fields when creating a store', function () {
    Volt::test('admin.settings.stores')
        ->call('openCreateModal')
        ->set('storeName', '')
        ->set('storeCountryCode', '')
        ->call('saveStore')
        ->assertHasErrors(['storeName', 'storeCountryCode']);
});

it('can edit a store', function () {
    $store = Store::factory()->create(['name' => 'Old Name']);

    Volt::test('admin.settings.stores')
        ->call('openEditModal', $store->id)
        ->assertSet('storeName', 'Old Name')
        ->set('storeName', 'New Name')
        ->call('saveStore');

    expect($store->fresh()->name)->toBe('New Name');
});

it('can delete a non-default store', function () {
    Store::factory()->default()->create();
    $store = Store::factory()->create(['name' => 'To Delete']);

    Volt::test('admin.settings.stores')
        ->call('deleteStore', $store->id);

    expect(Store::where('name', 'To Delete')->exists())->toBeFalse();
});

it('cannot delete the default store and shows error', function () {
    $store = Store::factory()->default()->create(['name' => 'Default Store']);

    Volt::test('admin.settings.stores')
        ->call('deleteStore', $store->id)
        ->assertHasErrors('deleteStore');

    expect(Store::where('name', 'Default Store')->exists())->toBeTrue();
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.stores'))
        ->assertForbidden();
});
