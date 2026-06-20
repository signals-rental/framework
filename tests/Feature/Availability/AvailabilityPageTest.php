<?php

use App\Models\AvailabilityDailySummary;
use App\Models\Demand;
use App\Models\Product;
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
| Equipment Availability page (M8-4b)
|--------------------------------------------------------------------------
|
| The standalone availability page renders a products x days calendar grid and a
| per-product Gantt timeline for a chosen store and date range, gated on
| availability.view. The Volt component calls AvailabilityService directly (the
| same service layer the API uses); the calendar reads the daily-summary read
| model and the Gantt reads the demands table.
|
| Assertions go through the rendered output (assertSee) and the public component
| state (assertSet) rather than reaching into #[Computed] properties, matching
| the M8 page-test convention.
|
| WRITTEN under the M8 cadence — NOT executed here (the full suite runs once at
| the M8-end gate).
|
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->store = Store::factory()->create(['timezone' => 'UTC', 'is_default' => true]);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['stock.access', 'availability.view']);

    // Freeze "today" so the default window and seeded summaries align.
    Carbon::setTestNow(Carbon::parse('2026-06-20 09:00:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders the availability page for a user with availability.view', function () {
    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->assertOk()
        ->assertSee('Equipment Availability');
});

it('forbids the page for a user without availability.view', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer);

    Volt::test('availability.index')->assertForbidden();
});

it('defaults to the default store and a four-week window', function () {
    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->assertSet('storeId', $this->store->id)
        ->assertSet('from', '2026-06-20')
        ->assertSet('to', '2026-07-18');
});

it('renders calendar cells from the daily-summary read model', function () {
    $product = Product::factory()->create(['name' => 'JBL EON615 Speaker']);

    AvailabilityDailySummary::factory()
        ->day(Carbon::parse('2026-06-20', 'UTC'), 5, 8)
        ->create(['product_id' => $product->id, 'store_id' => $this->store->id]);

    AvailabilityDailySummary::factory()
        ->day(Carbon::parse('2026-06-21', 'UTC'), -2, 0)
        ->create(['product_id' => $product->id, 'store_id' => $this->store->id]);

    $this->actingAs($this->user);

    // The grid renders the product row and the worst-available figure per cell.
    // The default landing is the by-group view, so switch to the calendar grid.
    Volt::test('availability.index')
        ->call('showCalendar')
        ->assertOk()
        ->assertSee('JBL EON615 Speaker')
        ->assertSee('cell-'.$product->id.'-2026-06-21', false)
        ->assertSee('Shortage');
});

it('reflects the pending check-in count in a calendar cell', function () {
    $product = Product::factory()->create(['name' => 'Returning Product']);

    $summary = AvailabilityDailySummary::factory()
        ->day(Carbon::parse('2026-06-20', 'UTC'), 3, 3)
        ->create(['product_id' => $product->id, 'store_id' => $this->store->id]);
    $summary->update(['pending_checkin_quantity' => 4]);

    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->call('showCalendar')
        ->assertOk()
        ->assertSee('Returning Product')
        ->assertSee('Pending check-in');
});

it('narrows the calendar to the selected product filter', function () {
    $shown = Product::factory()->create(['name' => 'Shown Product']);
    $hidden = Product::factory()->create(['name' => 'Hidden Product']);

    foreach ([$shown, $hidden] as $p) {
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-06-20', 'UTC'), 2, 2)
            ->create(['product_id' => $p->id, 'store_id' => $this->store->id]);
    }

    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->call('showCalendar')
        ->set('productIds', [$shown->id])
        ->assertSee('row-'.$shown->id, false)
        ->assertDontSee('row-'.$hidden->id, false);
});

it('switches to the per-product gantt and renders demand bars', function () {
    $product = Product::factory()->create(['name' => 'Gantt Product']);

    Demand::factory()
        ->window(Carbon::parse('2026-06-22 09:00', 'UTC'), Carbon::parse('2026-06-25 17:00', 'UTC'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 2,
        ]);

    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->call('showGantt', $product->id)
        ->assertSet('viewMode', 'gantt')
        ->assertSet('ganttProductId', $product->id)
        ->assertSee('Gantt Product')
        ->assertSee('Back to calendar')
        // The single demand bar renders, keyed by demand id.
        ->assertSee('bar-', false);
});

it('shifts the window by whole weeks', function () {
    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->call('shiftWindow', 1)
        ->assertSet('from', '2026-06-27')
        ->assertSet('to', '2026-07-25')
        ->call('shiftWindow', -1)
        ->assertSet('from', '2026-06-20')
        ->assertSet('to', '2026-07-18');
});

it('returns to the calendar view from the gantt', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->call('showGantt', $product->id)
        ->assertSet('viewMode', 'gantt')
        ->call('showCalendar')
        ->assertSet('viewMode', 'calendar')
        ->assertSet('ganttProductId', null);
});

it('shows a shortage summary badge when the window contains shortages', function () {
    $product = Product::factory()->create();

    AvailabilityDailySummary::factory()
        ->day(Carbon::parse('2026-06-21', 'UTC'), -1, 0)
        ->create(['product_id' => $product->id, 'store_id' => $this->store->id]);

    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->assertOk()
        ->assertSee('shortage');
});

it('surfaces the kit/composed-product calendar caveat', function () {
    $this->actingAs($this->user);

    Volt::test('availability.index')
        ->call('showCalendar')
        ->assertOk()
        ->assertSee('not shown on the calendar');
});

it('re-renders without error when the store availability changes', function () {
    $product = Product::factory()->create(['name' => 'Live Product']);

    $this->actingAs($this->user);

    $component = Volt::test('availability.index')->call('showCalendar');
    $component->assertOk();

    // Availability data appears, then the broadcast handler fires and the grid
    // re-renders with the now-present product row.
    AvailabilityDailySummary::factory()
        ->day(Carbon::parse('2026-06-20', 'UTC'), 4, 4)
        ->create(['product_id' => $product->id, 'store_id' => $this->store->id]);

    $component->call('onStoreAvailabilityChanged')
        ->assertOk()
        ->assertSee('Live Product');
});
