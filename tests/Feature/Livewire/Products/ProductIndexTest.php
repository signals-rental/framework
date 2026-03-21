<?php

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the products index page', function () {
    Product::factory()->count(3)->create();

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('Products');
});

it('lists products', function () {
    Product::factory()->create(['name' => 'LED Panel']);
    Product::factory()->create(['name' => 'Speaker System']);

    Volt::test('products.index')
        ->assertSee('LED Panel')
        ->assertSee('Speaker System');
});

it('filters by product type', function () {
    Product::factory()->rental()->create(['name' => 'Rental Product']);
    Product::factory()->sale()->create(['name' => 'Sale Product']);

    Volt::test('products.index')
        ->set('typeFilter', ProductType::Rental->value)
        ->assertSee('Rental Product')
        ->assertDontSee('Sale Product');
});

it('can archive a product', function () {
    $product = Product::factory()->create(['name' => 'To Archive']);

    Volt::test('products.index')
        ->call('archiveProduct', $product->id);

    expect(Product::withTrashed()->find($product->id)->trashed())->toBeTrue();
});

it('can restore a product', function () {
    $product = Product::factory()->create(['name' => 'To Restore']);
    $product->delete();

    Volt::test('products.index')
        ->call('restoreProduct', $product->id);

    expect(Product::find($product->id))->not->toBeNull();
    expect(Product::find($product->id)->trashed())->toBeFalse();
});

it('shows empty state when no products exist', function () {
    Volt::test('products.index')
        ->assertSee('No products found.');
});

it('ignores invalid product type in setTypeFilter', function () {
    Volt::test('products.index')
        ->call('setTypeFilter', 'invalid_type')
        ->assertSet('typeFilter', '');
});

it('ignores invalid archive filter', function () {
    Volt::test('products.index')
        ->call('setArchiveFilter', 'invalid_filter')
        ->assertSet('archiveFilter', 'active');
});

it('requires authentication', function () {
    auth()->logout();
    $this->get(route('products.index'))
        ->assertRedirect();
});
