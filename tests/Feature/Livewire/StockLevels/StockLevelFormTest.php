<?php

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    Store::factory()->create();
});

it('renders the violet serialised badge when a serialised product is selected', function () {
    $product = Product::factory()->serialised()->create(['name' => 'Serialised Camera']);

    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->assertSee('Serialised Camera')
        ->assertSee('s-badge-violet', false)
        ->assertSee('Serialised Stock');
});

it('renders the cyan bulk badge when a bulk product is selected', function () {
    $product = Product::factory()->bulk()->create(['name' => 'Bulk Cable']);

    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->assertSee('Bulk Cable')
        ->assertSee('s-badge-cyan', false)
        ->assertSee('Bulk Stock');
});

it('renders the consistent badge in the product search results', function () {
    // Set results directly to avoid the pg-only ilike search query on the SQLite test DB.
    Volt::test('stock-levels.form')
        ->set('productResults', [
            ['id' => 1, 'name' => 'Result Serialised', 'stock_method' => 2],
            ['id' => 2, 'name' => 'Result Bulk', 'stock_method' => 1],
        ])
        ->assertSee('Result Serialised')
        ->assertSee('Result Bulk')
        ->assertSee('s-badge-violet', false)
        ->assertSee('s-badge-cyan', false);
});
