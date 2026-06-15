<?php

use App\Livewire\Components\DataTable;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

/**
 * Build a known mix of availability states.
 *
 * @return array{available: StockLevel, allocated: StockLevel, quarantined: StockLevel}
 */
function makeStockMix(): array
{
    // Distinct products so the item-name column (which renders the product name)
    // produces a unique, assertable label per availability state.
    return [
        'available' => StockLevel::factory()->for(Product::factory()->create(['name' => 'AVAILABLE-ITEM']))->create([
            'quantity_held' => 10,
            'quantity_allocated' => 1,
            'quantity_unavailable' => 0,
        ]),
        'allocated' => StockLevel::factory()->for(Product::factory()->create(['name' => 'ALLOCATED-ITEM']))->create([
            'quantity_held' => 5,
            'quantity_allocated' => 5,
            'quantity_unavailable' => 0,
        ]),
        'quarantined' => StockLevel::factory()->for(Product::factory()->create(['name' => 'QUARANTINED-ITEM']))->create([
            'quantity_held' => 4,
            'quantity_allocated' => 0,
            'quantity_unavailable' => 4,
        ]),
    ];
}

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

it('maps each status filter to a real model scope (not an empty scope)', function () {
    $scopesFor = fn (string $status) => Volt::test('stock-levels.index')
        ->set('statusFilter', $status)
        ->viewData('scopes');

    expect($scopesFor('available'))->toBe(['available' => true])
        ->and($scopesFor('allocated'))->toBe(['allocated' => true])
        ->and($scopesFor('quarantined'))->toBe(['quarantined' => true])
        ->and($scopesFor(''))->toBe([]);
});

it('available chip shows only available stock, not allocated or quarantined', function () {
    makeStockMix();

    Livewire::test(DataTable::class, [
        'columns' => [['key' => 'item_name', 'label' => 'Item', 'view' => 'livewire.stock-levels.partials.column-item-name']],
        'model' => StockLevel::class,
        'with' => ['product', 'store'],
        'scopes' => ['available' => true],
    ])
        ->assertSee('AVAILABLE-ITEM')
        ->assertDontSee('ALLOCATED-ITEM')
        ->assertDontSee('QUARANTINED-ITEM');
});

it('allocated chip shows only fully-allocated stock, not all records', function () {
    makeStockMix();

    Livewire::test(DataTable::class, [
        'columns' => [['key' => 'item_name', 'label' => 'Item', 'view' => 'livewire.stock-levels.partials.column-item-name']],
        'model' => StockLevel::class,
        'with' => ['product', 'store'],
        'scopes' => ['allocated' => true],
    ])
        ->assertSee('ALLOCATED-ITEM')
        ->assertDontSee('AVAILABLE-ITEM')
        ->assertDontSee('QUARANTINED-ITEM');
});

it('quarantined chip shows only quarantined stock, not all records', function () {
    makeStockMix();

    Livewire::test(DataTable::class, [
        'columns' => [['key' => 'item_name', 'label' => 'Item', 'view' => 'livewire.stock-levels.partials.column-item-name']],
        'model' => StockLevel::class,
        'with' => ['product', 'store'],
        'scopes' => ['quarantined' => true],
    ])
        ->assertSee('QUARANTINED-ITEM')
        ->assertDontSee('AVAILABLE-ITEM')
        ->assertDontSee('ALLOCATED-ITEM');
});

it('row actions expose Edit and Delete links', function () {
    $product = Product::factory()->serialised()->create();
    $stockLevel = StockLevel::factory()->for($product)->create(['item_name' => 'Editable Item']);

    Volt::test('stock-levels.index')
        ->assertSee(route('stock-levels.edit', $stockLevel), false)
        ->assertSeeHtml('delete-stock-level-'.$stockLevel->id)
        ->assertSeeHtml('deleteStockLevel('.$stockLevel->id.')');
});

it('deletes a single stock level via the row-action method', function () {
    // Serialised so the "bulk products keep at least one stock level" guard does not block deletion.
    $stockLevel = StockLevel::factory()->for(Product::factory()->serialised())->create();

    Volt::test('stock-levels.index')
        ->call('deleteStockLevel', $stockLevel->id);

    expect(StockLevel::find($stockLevel->id))->toBeNull();
});

it('renders the bulk delete action in the bulk bar', function () {
    StockLevel::factory()->for(Product::factory()->serialised())->create();

    Livewire::test(DataTable::class, [
        'columns' => [
            ['key' => 'checkbox', 'type' => 'checkbox'],
            ['key' => 'item_name', 'label' => 'Item', 'view' => 'livewire.stock-levels.partials.column-item-name'],
        ],
        'model' => StockLevel::class,
        'with' => ['product', 'store'],
        'bulkActionsView' => 'livewire.stock-levels.partials.bulk-actions',
    ])
        ->set('selected', [StockLevel::query()->value('id')])
        ->assertSee('Delete Selected');
});

it('bulk deletes the selected stock levels', function () {
    $a = StockLevel::factory()->for(Product::factory()->serialised())->create();
    $b = StockLevel::factory()->for(Product::factory()->serialised())->create();

    Volt::test('stock-levels.index')
        ->call('deleteSelected', [$a->id, $b->id]);

    expect(StockLevel::find($a->id))->toBeNull()
        ->and(StockLevel::find($b->id))->toBeNull();
});

it('bulk delete is authorised through the DeleteStockLevel gate', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    // A user without stock.adjust cannot bulk delete; the action's
    // Gate::authorize('stock.adjust') surfaces as a forbidden response.
    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    $stockLevel = StockLevel::factory()->for(Product::factory()->serialised())->create();

    Volt::test('stock-levels.index')
        ->call('deleteSelected', [$stockLevel->id])
        ->assertForbidden();

    expect(StockLevel::find($stockLevel->id))->not->toBeNull();
});
