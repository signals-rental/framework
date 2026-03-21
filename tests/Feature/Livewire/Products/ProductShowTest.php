<?php

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the show page with product details', function () {
    $product = Product::factory()->create(['name' => 'LED Panel 4x8']);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('LED Panel 4x8');
});

it('shows the product name and type', function () {
    $product = Product::factory()->rental()->create(['name' => 'Wireless Microphone']);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('Wireless Microphone')
        ->assertSee('Rental');
});

it('shows active badge for active products', function () {
    $product = Product::factory()->create(['name' => 'Active Product', 'is_active' => true]);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('Active');
});

it('shows inactive badge for inactive products', function () {
    $product = Product::factory()->inactive()->create(['name' => 'Inactive Product']);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('Inactive');
});

it('loads product group relationship', function () {
    $group = ProductGroup::factory()->create(['name' => 'Audio Equipment']);
    $product = Product::factory()->create([
        'name' => 'Grouped Product',
        'product_group_id' => $group->id,
    ]);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('Audio Equipment');
});

it('shows description when present', function () {
    $product = Product::factory()->create([
        'name' => 'Described Product',
        'description' => 'A detailed product description',
    ]);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('A detailed product description');
});

it('requires authentication', function () {
    $product = Product::factory()->create();
    auth()->logout();

    $this->get(route('products.show', $product))
        ->assertRedirect();
});
