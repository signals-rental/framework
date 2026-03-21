<?php

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the stock levels index page', function () {
    $this->get(route('stock-levels.index'))
        ->assertOk()
        ->assertSee('Stock Levels');
});

it('lists stock levels', function () {
    $product = Product::factory()->create(['name' => 'LED Panel']);
    $store = Store::factory()->create();
    StockLevel::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'item_name' => 'LED Panel #001',
    ]);

    Volt::test('stock-levels.index')
        ->assertSee('LED Panel');
});

it('shows empty state when no stock levels exist', function () {
    Volt::test('stock-levels.index')
        ->assertSee('No stock levels found.');
});

it('ignores invalid status filter', function () {
    Volt::test('stock-levels.index')
        ->call('setStatusFilter', 'invalid')
        ->assertSet('statusFilter', '');
});

it('requires authentication', function () {
    auth()->logout();
    $this->get(route('stock-levels.index'))
        ->assertRedirect();
});
