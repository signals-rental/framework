<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\DemandPhase;
use App\Enums\ShortagePolicy;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Opportunity Shortages tab (M8-4c) — shortage panel + resolver UI
|--------------------------------------------------------------------------
|
| The tab reads shortages live from the ShortageDetector, enumerates resolvers
| through the ShortageResolverRegistry, surfaces the ShortageConfirmationGate
| pre-check + store dispatch policy, and applies/transitions resolutions through
| the SAME action classes the API uses. It needs a LIVE Verbs opportunity (factory
| rows carry a synthetic state_id with no event stream and cannot fire item
| events), so the helper builds one through the real actions.
|
| TESTS ARE WRITTEN, NOT RUN (M8 cadence) — the full suite runs once at the M8-end
| gate.
|
*/

beforeEach(function () {
    Queue::fake();
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * Build an opportunity with a single product-backed line that is short over its
 * window: 5 units held, 4 committed elsewhere, this line wants 3 → only 1 free,
 * short by 2. Returns the live opportunity + its line item.
 *
 * @return array{0: Opportunity, 1: OpportunityItem, 2: Product}
 */
function shortOpportunityForTab(User $actor, Store $store, ShortagePolicy $policy = ShortagePolicy::Warn): array
{
    $store->update(['shortage_policy' => $policy->value]);

    Auth::login($actor);

    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-09-01T09:00:00Z'), Carbon::parse('2026-09-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => 970001,
            'metadata' => [],
        ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Shortage tab slice',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '3',
    ]));

    return [$opportunity->fresh(), $opportunity->items()->firstOrFail(), $product];
}

it('renders the shortages tab for a user with shortages.view', function () {
    [$opportunity] = shortOpportunityForTab($this->owner, $this->store);

    $this->actingAs($this->owner);

    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('Detected Shortages')
        ->assertSee('Conversion & Dispatch Checks');
});

it('forbids the shortages tab for a user without shortages.view', function () {
    [$opportunity] = shortOpportunityForTab($this->owner, $this->store);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])->assertForbidden();
});

it('lists each short line with the detector figures', function () {
    [$opportunity, $item, $product] = shortOpportunityForTab($this->owner, $this->store);

    $this->actingAs($this->owner);

    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])
        ->assertSee($product->name)
        // Requested 3 / available 1 / short by 2.
        ->assertSee('Short by')
        ->assertSeeHtml('shortage-'.$item->id);
});

it('shows the No shortages empty state when the opportunity is serviceable', function () {
    Auth::login($this->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'No shortage slice',
        'store_id' => $this->store->id,
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    $this->actingAs($this->owner);

    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])
        ->assertSee('No shortages');
});

it('enumerates applicable resolver options for a short line', function () {
    [$opportunity] = shortOpportunityForTab($this->owner, $this->store);

    $this->actingAs($this->owner);

    // Partial fulfilment always applies to a bulk shortfall — its option label
    // should appear among the resolver options.
    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])
        ->assertSee('Resolution options');
});

it('applies a resolver option and persists a resolution', function () {
    [$opportunity, $item] = shortOpportunityForTab($this->owner, $this->store);

    $this->actingAs($this->owner);

    expect(ShortageResolution::query()->forOpportunity($opportunity->id)->count())->toBe(0);

    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])
        ->call('selectResolution', $item->id, 'partial', 0)
        ->call('applyResolution');

    expect(ShortageResolution::query()->forOpportunity($opportunity->id)->count())->toBeGreaterThan(0);
});

it('confirms a pending resolution and updates its status', function () {
    [$opportunity, $item] = shortOpportunityForTab($this->owner, $this->store);

    $resolution = ShortageResolution::factory()->create([
        'resolver_key' => 'transfer',
        'resolution_type' => ShortageResolutionType::Transfer,
        'status' => ShortageResolutionStatus::Pending,
        'quantity_resolved' => 2,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $item->id,
        'quantity_allocated' => 2,
    ]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])
        ->call('confirmResolution', $resolution->id);

    expect($resolution->fresh()->status)->toBe(ShortageResolutionStatus::Confirmed);
});

it('rejects an illegal transition and leaves the status unchanged', function () {
    [$opportunity, $item] = shortOpportunityForTab($this->owner, $this->store);

    // A confirmed resolution cannot be fulfilled directly (§8.3: confirmed →
    // in_progress only). The UI greys the button out, but calling the method must
    // also reject the move (422) without mutating the record.
    $resolution = ShortageResolution::factory()->create([
        'resolver_key' => 'transfer',
        'resolution_type' => ShortageResolutionType::Transfer,
        'status' => ShortageResolutionStatus::Confirmed,
        'quantity_resolved' => 2,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $item->id,
        'quantity_allocated' => 2,
    ]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])
        ->call('fulfillResolution', $resolution->id);

    // Illegal move rejected — still Confirmed.
    expect($resolution->fresh()->status)->toBe(ShortageResolutionStatus::Confirmed);

    // ...and the legal move (Start) does take effect.
    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])
        ->call('startResolution', $resolution->id);

    expect($resolution->fresh()->status)->toBe(ShortageResolutionStatus::InProgress);
});

it('cancels a resolution with a reason', function () {
    [$opportunity, $item] = shortOpportunityForTab($this->owner, $this->store);

    $resolution = ShortageResolution::factory()->create([
        'resolver_key' => 'transfer',
        'resolution_type' => ShortageResolutionType::Transfer,
        'status' => ShortageResolutionStatus::Pending,
        'quantity_resolved' => 2,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $item->id,
        'quantity_allocated' => 2,
    ]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])
        ->call('promptReason', $resolution->id, 'cancel')
        ->set('transitionReason', 'Customer dropped the line')
        ->call('submitReasonTransition');

    expect($resolution->fresh()->status)->toBe(ShortageResolutionStatus::Cancelled)
        ->and($resolution->fresh()->cancellation_reason)->toBe('Customer dropped the line');
});

it('shows the gate pre-check decision driven by the store policy', function () {
    // Block policy on a store with unresolved shortages → the gate shows Block.
    [$opportunity] = shortOpportunityForTab($this->owner, $this->store, ShortagePolicy::Block);

    $this->actingAs($this->owner);

    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])
        ->assertSee('Convert to Order gate')
        ->assertSee('Conversion is blocked');
});

it('surfaces the store dispatch policy', function () {
    [$opportunity] = shortOpportunityForTab($this->owner, $this->store);

    $this->actingAs($this->owner);

    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])
        ->assertSee('Dispatch policy');
});

it('renders the shortages tab read-only for a view-only user (no Apply buttons)', function () {
    [$opportunity] = shortOpportunityForTab($this->owner, $this->store);

    // shortages.view but NOT shortages.resolve → read-only: the panel renders but
    // the Apply action buttons are withheld.
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view', 'shortages.view');
    $this->actingAs($viewer);

    Volt::test('opportunities.shortages', ['opportunity' => $opportunity])
        ->assertSet('canResolve', false)
        ->assertSee('Detected Shortages')
        ->assertSee('Resolution options')
        ->assertDontSee('wire:click="selectResolution', escape: false);
});
