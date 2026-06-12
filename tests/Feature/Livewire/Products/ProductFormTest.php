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

it('renders the create form', function () {
    $this->get(route('products.create'))
        ->assertOk()
        ->assertSee('Create Product');
});

it('renders the edit form with pre-populated data', function () {
    $product = Product::factory()->create([
        'name' => 'LED Panel',
        'product_type' => ProductType::Rental,
        'description' => 'A bright LED panel',
        'sku' => 'LED-001',
        'barcode' => 'BC123',
    ]);

    $this->get(route('products.edit', $product))
        ->assertOk()
        ->assertSee('Edit');

    Volt::test('products.form', ['product' => $product])
        ->assertSet('name', 'LED Panel')
        ->assertSet('productType', 'rental')
        ->assertSet('description', 'A bright LED panel')
        ->assertSet('sku', 'LED-001')
        ->assertSet('barcode', 'BC123');
});

it('creates a product with valid data', function () {
    Volt::test('products.form')
        ->set('name', 'New Test Product')
        ->set('productType', 'rental')
        ->set('stockMethod', 1)
        ->call('save')
        ->assertRedirect();

    $this->assertDatabaseHas('products', [
        'name' => 'New Test Product',
        'product_type' => 'rental',
    ]);
});

it('validates required name field', function () {
    Volt::test('products.form')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

it('redirects to show page after creation', function () {
    $component = Volt::test('products.form')
        ->set('name', 'Redirect Test Product')
        ->call('save');

    $product = Product::where('name', 'Redirect Test Product')->first();
    $component->assertRedirect(route('products.show', $product));
});

it('updates an existing product', function () {
    $product = Product::factory()->create(['name' => 'Original Name']);

    Volt::test('products.form', ['product' => $product])
        ->set('name', 'Updated Name')
        ->call('save')
        ->assertRedirect();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Updated Name',
    ]);
});

it('pre-populates product type from query param', function () {
    // Default without query param
    Volt::test('products.form')
        ->assertSet('productType', 'rental');

    // With query param - test via HTTP request
    $this->get(route('products.create', ['type' => 'sale']))
        ->assertOk()
        ->assertSee('Sale');
});

it('validates unique product name', function () {
    Product::factory()->create(['name' => 'Duplicate Name']);

    Volt::test('products.form')
        ->set('name', 'Duplicate Name')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('allows same name when editing the same product', function () {
    $product = Product::factory()->create(['name' => 'Keep This Name']);

    Volt::test('products.form', ['product' => $product])
        ->set('name', 'Keep This Name')
        ->call('save')
        ->assertHasNoErrors(['name'])
        ->assertRedirect();
});

it('saves tags from the tag-input array on create', function () {
    Volt::test('products.form')
        ->set('name', 'Tagged Product')
        ->set('tags', ['Lighting', 'Sound'])
        ->call('save')
        ->assertRedirect();

    $product = Product::where('name', 'Tagged Product')->firstOrFail();

    expect($product->tag_list)->toBe(['Lighting', 'Sound']);
});

it('hydrates existing tags into the tags property on mount', function () {
    $product = Product::factory()->create([
        'name' => 'Existing Tags Product',
        'tag_list' => ['Rigging', 'Cables'],
    ]);

    Volt::test('products.form', ['product' => $product])
        ->assertSet('tags', ['Rigging', 'Cables']);
});

it('exposes distinct existing tags as suggestions', function () {
    Product::factory()->create(['tag_list' => ['Sound', 'Lighting']]);
    Product::factory()->create(['tag_list' => ['Lighting', 'Rigging']]);

    Volt::test('products.form')
        ->assertSet('tags', [])
        ->tap(function ($component) {
            $suggestions = $component->instance()->tagSuggestions();
            expect($suggestions)->toBe(['Lighting', 'Rigging', 'Sound']);
        });
});

it('requires authentication', function () {
    auth()->logout();
    $this->get(route('products.create'))
        ->assertRedirect();
});
