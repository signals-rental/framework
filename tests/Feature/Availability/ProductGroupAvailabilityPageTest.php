<?php

use App\Models\AvailabilityDailySummary;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Product-Group Availability grid (C2)
|--------------------------------------------------------------------------
|
| The availability page's default "By Group" view renders a group's products
| (rows) x dates (columns), each cell showing `available (held)` colour-coded by
| state, with product-type / shortages-only / warnings-only / include-quotes
| filters, a selectable days-period (default 30) and paginated rows. Gated on
| availability.view; reads the daily-summary read model via
| AvailabilityService::getCalendar and derives held from productTotalStock.
|
| WRITTEN under the Phase-3 cadence and executed for this workstream.
|
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->store = Store::factory()->create(['timezone' => 'UTC', 'is_default' => true]);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['stock.access', 'availability.view']);

    $this->group = ProductGroup::factory()->create(['name' => 'Speakers']);

    Carbon::setTestNow(Carbon::parse('2026-06-20 09:00:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Helper: a product in the group with a bulk stock level and a single-day
 * daily summary so the grid has a cell to render.
 */
function seedGroupProduct(ProductGroup $group, Store $store, string $name, int $stock, int $available, bool $shortage = false): Product
{
    $product = Product::factory()->create([
        'name' => $name,
        'product_group_id' => $group->id,
    ]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => $stock,
    ]);

    AvailabilityDailySummary::factory()
        ->day(Carbon::parse('2026-06-20', 'UTC'), $available, $available)
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'has_shortage' => $shortage,
        ]);

    return $product;
}

it('defaults to the by-group view with a 30-day period', function () {
    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->assertSet('viewMode', 'group')
        ->assertSet('daysPeriod', 30)
        ->assertSet('storeId', $this->store->id);
});

it('forbids the page for a user without availability.view', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('availability.index')->assertForbidden();
});

it('renders a group product row with available and held figures', function () {
    seedGroupProduct($this->group, $this->store, 'JBL EON615', stock: 10, available: 4);

    $this->actingAs($this->user);

    // available=4, held = total stock (10) - available (4) = 6, rendered "4 (6)".
    Volt::test('availability.index')
        ->set('groupId', $this->group->id)
        ->assertOk()
        ->assertSee('JBL EON615')
        ->assertSee('gcell-', false)
        ->assertSeeHtml('(6)');
});

it('shows nothing until a group is selected', function () {
    seedGroupProduct($this->group, $this->store, 'JBL EON615', stock: 10, available: 4);

    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->assertOk()
        ->assertSee('Select a product group')
        ->assertDontSee('JBL EON615');
});

it('filters rows to shortages only', function () {
    $shorted = seedGroupProduct($this->group, $this->store, 'Shorted Speaker', stock: 2, available: -1, shortage: true);
    $fine = seedGroupProduct($this->group, $this->store, 'Fine Speaker', stock: 10, available: 8);

    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->set('groupId', $this->group->id)
        ->set('shortagesOnly', true)
        ->assertSee('grow-'.$shorted->id, false)
        ->assertDontSee('grow-'.$fine->id, false);
});

it('filters rows to warnings only', function () {
    // available=1 (<= threshold 2, > 0) is a warning; available=9 is plain available.
    $warning = seedGroupProduct($this->group, $this->store, 'Low Speaker', stock: 10, available: 1);
    $plenty = seedGroupProduct($this->group, $this->store, 'Plenty Speaker', stock: 10, available: 9);

    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->set('groupId', $this->group->id)
        ->set('warningsOnly', true)
        ->assertSee('grow-'.$warning->id, false)
        ->assertDontSee('grow-'.$plenty->id, false);
});

it('filters products by product type', function () {
    $rental = Product::factory()->rental()->create(['name' => 'Rental Item', 'product_group_id' => $this->group->id]);
    $sale = Product::factory()->sale()->create(['name' => 'Sale Item', 'product_group_id' => $this->group->id]);

    foreach ([$rental, $sale] as $p) {
        StockLevel::factory()->bulk()->create(['product_id' => $p->id, 'store_id' => $this->store->id, 'quantity_held' => 5]);
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-06-20', 'UTC'), 3, 3)
            ->create(['product_id' => $p->id, 'store_id' => $this->store->id]);
    }

    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->set('groupId', $this->group->id)
        ->set('productType', 'sale')
        ->assertSee('grow-'.$sale->id, false)
        ->assertDontSee('grow-'.$rental->id, false);
});

it('honours the days-period in the rendered column count', function () {
    seedGroupProduct($this->group, $this->store, 'Period Speaker', stock: 5, available: 3);

    $this->actingAs($this->user);

    // A 7-day period from 2026-06-20 includes 2026-06-26 but not 2026-06-27.
    Volt::test('availability.index')
        ->set('groupId', $this->group->id)
        ->set('daysPeriod', 7)
        ->assertSee('ghead-2026-06-26', false)
        ->assertDontSee('ghead-2026-06-27', false);
});

it('colours a fully-booked cell as booked (teal) not shortage', function () {
    // available exactly 0, no shortage -> booked (cyan badge), held = full stock.
    seedGroupProduct($this->group, $this->store, 'Booked Speaker', stock: 5, available: 0, shortage: false);

    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->set('groupId', $this->group->id)
        ->assertSee('s-badge-cyan', false)
        ->assertDontSee('Select a product group');
});
