<?php

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

it('renders the product groups index page', function () {
    ProductGroup::factory()->count(2)->create();

    $this->get(route('product-groups.index'))
        ->assertOk()
        ->assertSee('Product Groups');
});

it('lists product groups', function () {
    ProductGroup::factory()->create(['name' => 'Lighting']);
    ProductGroup::factory()->create(['name' => 'Sound']);

    Volt::test('product-groups.index')
        ->assertSee('Lighting')
        ->assertSee('Sound');
});

it('deletes a product group', function () {
    $group = ProductGroup::factory()->create(['name' => 'To Delete']);

    Volt::test('product-groups.index')
        ->call('deleteGroup', $group->id);

    expect(ProductGroup::find($group->id))->toBeNull();
});

it('renders view products, edit and delete row actions', function () {
    $group = ProductGroup::factory()->create(['name' => 'Group With Actions']);

    Volt::test('product-groups.index')
        ->assertSee('View products')
        ->assertSee('Edit')
        ->assertSee('Delete')
        ->assertSeeHtml(route('products.index', ['filters' => ['product_group_id' => $group->id]]))
        ->assertSeeHtml(route('product-groups.edit', $group->id))
        ->assertSeeHtml("open-modal', 'delete-group-{$group->id}'");
});

it('renders the products count as a filtered link when the group has products', function () {
    $group = ProductGroup::factory()->create(['name' => 'Has Products']);
    Product::factory()->create(['product_group_id' => $group->id]);

    Volt::test('product-groups.index')
        ->assertSeeHtml('filters%5Bproduct_group_id%5D='.$group->id);
});

it('renders the group icon inline in the name column with a fallback glyph when no icon is set', function () {
    ProductGroup::factory()->create(['name' => 'Audio Visual']);

    Volt::test('product-groups.index')
        // Fallback glyph container + the name link rendered by the column-name partial
        ->assertSeeHtml('size-8 rounded')
        ->assertSee('Audio Visual')
        ->assertSeeHtml(route('products.index', ['filters' => ['product_group_id' => ProductGroup::first()->id]]));
});

it('renders the group icon thumbnail inline in the name column when an icon is set', function () {
    $group = ProductGroup::factory()->create([
        'name' => 'Has Icon',
        'icon_url' => 'icons/productgroups/1/icon.jpg',
        'icon_thumb_url' => 'icons/productgroups/1/thumbs/icon.jpg',
    ]);

    Volt::test('product-groups.index')
        ->assertSeeHtml('object-cover');
});

it('renders the parent group name in the parent column for a child group', function () {
    $parent = ProductGroup::factory()->create(['name' => 'Lighting']);
    ProductGroup::factory()->create(['name' => 'Moving Heads', 'parent_id' => $parent->id]);

    Volt::test('product-groups.index')
        ->assertSee('Lighting')
        ->assertSee('Moving Heads');
});

it('renders an em-dash in the parent column for a top-level group', function () {
    ProductGroup::factory()->create(['name' => 'Top Level']);

    Volt::test('product-groups.index')
        ->assertSeeHtml('text-[var(--text-muted)]')
        ->assertSee('—');
});

it('shows empty state when no product groups exist', function () {
    Volt::test('product-groups.index')
        ->assertSee('No product groups found.');
});

it('renders the custom-view selector for the product_groups entity type', function () {
    ProductGroup::factory()->create(['name' => 'Lighting']);

    Volt::test('product-groups.index')
        // view-selector split button + the "new custom view" entry
        ->assertSeeHtml('s-btn-split')
        ->assertSee('New custom view');
});

it('renders the bulk-actions bar with a delete action when rows are selected', function () {
    $group = ProductGroup::factory()->create(['name' => 'Lighting']);

    // Drive the shared data-table directly with the product-groups bulk config,
    // since the bulk bar only renders once a row is selected.
    Livewire::test(DataTable::class, [
        'columns' => [
            ['key' => 'checkbox', 'type' => 'checkbox'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'actions', 'type' => 'actions'],
        ],
        'model' => ProductGroup::class,
        'bulkActionsView' => 'livewire.product-groups.partials.bulk-actions',
        'entityType' => 'product_groups',
    ])
        ->call('toggleSelected', $group->id)
        ->assertSee('Delete Selected')
        ->assertSeeHtml('$parent.deleteSelected');
});

it('bulk-deletes the selected product groups', function () {
    $groupA = ProductGroup::factory()->create(['name' => 'Alpha']);
    $groupB = ProductGroup::factory()->create(['name' => 'Bravo']);
    $keep = ProductGroup::factory()->create(['name' => 'Charlie']);

    Volt::test('product-groups.index')
        ->call('deleteSelected', [$groupA->id, $groupB->id]);

    expect(ProductGroup::find($groupA->id))->toBeNull()
        ->and(ProductGroup::find($groupB->id))->toBeNull()
        ->and(ProductGroup::find($keep->id))->not->toBeNull();
});

it('renders the bulk checkbox column header', function () {
    ProductGroup::factory()->create(['name' => 'Lighting']);

    Volt::test('product-groups.index')
        ->assertSeeHtml('s-col-check');
});
