<?php

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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

it('does not show a New Stock Level button', function () {
    Volt::test('stock-levels.index')
        ->assertDontSee('New Stock Level');
});

it('renders the product image in the product column when the product has one', function () {
    Storage::fake('public');

    $product = Product::factory()->create([
        'name' => 'Imaged Product',
        'icon_thumb_url' => 'icons/product-thumb.jpg',
    ]);
    StockLevel::factory()->create(['product_id' => $product->id]);

    Volt::test('stock-levels.index')
        ->assertSee('icons/product-thumb.jpg', false)
        ->assertSee('<img', false);
});

it('renders item name and codes as links, codes monospaced, and store as a badge', function () {
    $product = Product::factory()->create(['name' => 'LED Panel']);
    $store = Store::factory()->create(['name' => 'Main Warehouse']);
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'item_name' => 'LED Panel #001',
        'asset_number' => 'AST-12345',
        'serial_number' => 'SN-99887',
    ]);

    Volt::test('stock-levels.index')
        // the item-name column now shows the related product's name
        ->assertSee('LED Panel')
        ->assertSee('AST-12345')
        ->assertSee('SN-99887')
        // item name + asset/serial numbers all link to the stock-level entry
        ->assertSee(route('stock-levels.show', $stockLevel), false)
        // asset/serial numbers use the monospace (SKU-style) treatment
        ->assertSee('var(--font-mono)', false)
        // store renders as a badge
        ->assertSee('s-badge', false)
        ->assertSee('Main Warehouse');
});

it('shows the related product name in the item-name column when item_name is null', function () {
    $product = Product::factory()->create(['name' => 'Fresnel 2K']);
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'item_name' => null,
    ]);

    Volt::test('stock-levels.index')
        ->assertSee('Fresnel 2K')
        ->assertSee(route('stock-levels.show', $stockLevel), false);
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
