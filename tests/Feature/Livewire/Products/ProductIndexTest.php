<?php

use App\Enums\ProductType;
use App\Livewire\Components\DataTable;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Livewire\Livewire;
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

it('renders the group column with the product group name as a filter link', function () {
    $group = ProductGroup::factory()->create(['name' => 'Lighting Group']);
    Product::factory()->create(['name' => 'Grouped Product', 'product_group_id' => $group->id]);

    Volt::test('products.index')
        ->assertSee('Group')
        ->assertSee('Lighting Group')
        ->assertSeeHtml("applyFilter('product_group_id', '{$group->id}')");
});

it('filters the products data table by product group', function () {
    $lighting = ProductGroup::factory()->create(['name' => 'Lighting']);
    $sound = ProductGroup::factory()->create(['name' => 'Sound']);

    Product::factory()->create(['name' => 'Moving Head', 'product_group_id' => $lighting->id]);
    Product::factory()->create(['name' => 'PA Speaker', 'product_group_id' => $sound->id]);

    $columns = [
        ['key' => 'name', 'label' => 'Name', 'sortable' => true],
        ['key' => 'product_group_id', 'label' => 'Group', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => [$lighting->id => 'Lighting', $sound->id => 'Sound'], 'view' => 'livewire.products.partials.column-group'],
    ];

    Livewire::test(DataTable::class, [
        'columns' => $columns,
        'model' => Product::class,
        'with' => ['productGroup'],
        'defaultSort' => 'product_group_id',
    ])
        ->call('applyFilter', 'product_group_id', (string) $lighting->id)
        ->assertSee('Moving Head')
        ->assertDontSee('PA Speaker');
});

it('renders the group and updated columns by default', function () {
    Product::factory()->create(['name' => 'Defaults Product']);

    Volt::test('products.index')
        ->assertSee('Group')
        ->assertSee('Updated');
});

it('requires authentication', function () {
    auth()->logout();
    $this->get(route('products.index'))
        ->assertRedirect();
});
