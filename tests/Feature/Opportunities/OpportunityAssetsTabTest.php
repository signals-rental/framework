<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\ShortageDispatchPolicy;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
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
| Opportunity Assets / Fulfilment tab — RMS asset-allocation detail view
|--------------------------------------------------------------------------
|
| The assets tab is a full-width detail view with internal Livewire sub-tabs
| (Functions, Allocate, Prepare, Book out, Check in — a $subTab property, NOT routes)
| over one shared grouped asset table. Each sub-tab's scan bar resolves a typed asset
| number to the SAME M5 action classes the API uses; the tab only exposes the LEGAL
| next action for each asset's status, and the book-out path routes through the §7.4
| DispatchShortageGate (Block -> 422 flashed, Warn -> held items surfaced). It needs a
| LIVE Verbs opportunity (factory rows have no event stream), so the helper builds one
| through the real actions.
|
*/

beforeEach(function () {
    Queue::fake();
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->product = Product::factory()->rental()->serialised()->create();
});

/**
 * Build a live event-sourced Order with one serialised line for $quantity units,
 * returning the opportunity + its line item ready for asset allocation.
 *
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function makeAssetsTabOrder(User $actor, Store $store, Product $product, string $quantity = '2'): array
{
    Auth::login($actor);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Assets tab slice',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => $quantity,
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($opportunity->refresh());

    return [$opportunity->fresh(), $opportunity->items()->firstOrFail()];
}

function makeAssetsTabAsset(Store $store, Product $product, ?string $assetNumber = null): StockLevel
{
    return StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_number' => $assetNumber ?? ('AST-'.fake()->unique()->numberBetween(1000, 9999)),
    ]);
}

it('renders the assets tab with the sub-tabs and grouped table', function () {
    [$opportunity] = makeAssetsTabOrder($this->owner, $this->store, $this->product);

    $this->actingAs($this->owner);

    Volt::test('opportunities.assets', ['opportunity' => $opportunity])
        ->assertSet('subTab', 'functions')
        ->assertOk()
        ->assertSee('Functions')
        ->assertSee('Allocate')
        ->assertSee('Prepare')
        ->assertSee('Book out')
        ->assertSee('Check in')
        ->assertSee($this->product->name);
});

it('forbids the assets tab for a user without opportunities.view', function () {
    [$opportunity] = makeAssetsTabOrder($this->owner, $this->store, $this->product);

    $stranger = User::factory()->create();
    $this->actingAs($stranger);

    Volt::test('opportunities.assets', ['opportunity' => $opportunity])->assertForbidden();
});

it('switches the active internal sub-tab without leaving the route', function () {
    [$opportunity] = makeAssetsTabOrder($this->owner, $this->store, $this->product);

    $this->actingAs($this->owner);

    Volt::test('opportunities.assets', ['opportunity' => $opportunity])
        ->call('setSubTab', 'allocate')
        ->assertSet('subTab', 'allocate')
        ->call('setSubTab', 'nonsense')
        ->assertSet('subTab', 'functions');
});

it('allocates a free serialised asset via the Allocate scan bar', function () {
    [$opportunity, $item] = makeAssetsTabOrder($this->owner, $this->store, $this->product);
    $asset = makeAssetsTabAsset($this->store, $this->product, 'SCAN-100');

    $this->actingAs($this->owner);

    expect(OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->count())->toBe(0);

    Volt::test('opportunities.assets', ['opportunity' => $opportunity])
        ->set('subTab', 'allocate')
        ->set('scanAsset', 'SCAN-100')
        ->call('scanAllocate');

    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();
    expect($row->stock_level_id)->toBe($asset->id)
        ->and($row->status)->toBe(AssetAssignmentStatus::Allocated);
});

it('chains preparation when "Mark as prepared" is set on the Allocate scan bar', function () {
    [$opportunity, $item] = makeAssetsTabOrder($this->owner, $this->store, $this->product);
    makeAssetsTabAsset($this->store, $this->product, 'SCAN-PREP');

    $this->actingAs($this->owner);

    Volt::test('opportunities.assets', ['opportunity' => $opportunity])
        ->set('subTab', 'allocate')
        ->set('markPrepared', true)
        ->set('scanAsset', 'SCAN-PREP')
        ->call('scanAllocate');

    expect(OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole()->status)
        ->toBe(AssetAssignmentStatus::Prepared);
});

it('prepares, books out and checks in via the scan bars', function () {
    [$opportunity, $item] = makeAssetsTabOrder($this->owner, $this->store, $this->product);
    makeAssetsTabAsset($this->store, $this->product, 'SCAN-FLOW');

    Auth::login($this->owner);
    $assignment = (new AllocateAsset)($item, AllocateAssetData::from([
        'stock_level_id' => StockLevel::query()->where('asset_number', 'SCAN-FLOW')->sole()->id,
    ]));

    $this->actingAs($this->owner);

    // Prepare via the Prepare scan bar.
    Volt::test('opportunities.assets', ['opportunity' => $opportunity])
        ->set('subTab', 'prepare')
        ->set('scanAsset', 'SCAN-FLOW')
        ->call('scanPrepare');
    expect(OpportunityItemAsset::query()->whereKey($assignment->id)->firstOrFail()->status)
        ->toBe(AssetAssignmentStatus::Prepared);

    // Book out via the Book out scan bar.
    Volt::test('opportunities.assets', ['opportunity' => $opportunity->fresh()])
        ->set('subTab', 'book_out')
        ->set('scanAsset', 'SCAN-FLOW')
        ->call('scanBookOut');
    expect(OpportunityItemAsset::query()->whereKey($assignment->id)->firstOrFail()->status)
        ->toBe(AssetAssignmentStatus::Dispatched);

    // Check in via the Check in scan bar.
    Volt::test('opportunities.assets', ['opportunity' => $opportunity->fresh()])
        ->set('subTab', 'check_in')
        ->set('scanAsset', 'SCAN-FLOW')
        ->call('scanCheckIn');
    expect(OpportunityItemAsset::query()->whereKey($assignment->id)->firstOrFail()->status)
        ->toBe(AssetAssignmentStatus::CheckedIn);
});

it('flashes an error when a scan bar cannot resolve the asset number', function () {
    [$opportunity] = makeAssetsTabOrder($this->owner, $this->store, $this->product);

    $this->actingAs($this->owner);

    Volt::test('opportunities.assets', ['opportunity' => $opportunity])
        ->set('subTab', 'prepare')
        ->set('scanAsset', 'DOES-NOT-EXIST')
        ->call('scanPrepare')
        ->assertSee('No allocated asset numbered');
});

it('prepares the selected assets via the Functions Action menu', function () {
    [$opportunity, $item] = makeAssetsTabOrder($this->owner, $this->store, $this->product);

    Auth::login($this->owner);
    $assetA = makeAssetsTabAsset($this->store, $this->product, 'BULK-A');
    $assetB = makeAssetsTabAsset($this->store, $this->product, 'BULK-B');
    $a = (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $assetA->id]));
    $b = (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $assetB->id]));

    $this->actingAs($this->owner);

    Volt::test('opportunities.assets', ['opportunity' => $opportunity->fresh()])
        ->set('selected', [(string) $a->id, (string) $b->id])
        ->set('bulkAction', 'prepare')
        ->call('runBulkAction')
        ->assertSet('selected', [])
        ->assertSet('bulkAction', '');

    expect(OpportunityItemAsset::query()->whereKey($a->id)->firstOrFail()->status)->toBe(AssetAssignmentStatus::Prepared)
        ->and(OpportunityItemAsset::query()->whereKey($b->id)->firstOrFail()->status)->toBe(AssetAssignmentStatus::Prepared);
});

it('books the selected assets out via the Functions Action menu', function () {
    [$opportunity, $item] = makeAssetsTabOrder($this->owner, $this->store, $this->product);

    Auth::login($this->owner);
    $asset = makeAssetsTabAsset($this->store, $this->product, 'BULK-OUT');
    $assignment = (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    $this->actingAs($this->owner);

    Volt::test('opportunities.assets', ['opportunity' => $opportunity->fresh()])
        ->set('selected', [(string) $assignment->id])
        ->set('bulkAction', 'book_out')
        ->call('runBulkAction');

    expect(OpportunityItemAsset::query()->whereKey($assignment->id)->firstOrFail()->status)
        ->toBe(AssetAssignmentStatus::Dispatched);
});

it('toggles select-all across every asset row', function () {
    [$opportunity, $item] = makeAssetsTabOrder($this->owner, $this->store, $this->product);

    Auth::login($this->owner);
    $a = (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => makeAssetsTabAsset($this->store, $this->product)->id]));
    $b = (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => makeAssetsTabAsset($this->store, $this->product)->id]));

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.assets', ['opportunity' => $opportunity->fresh()])
        ->call('toggleSelectAll', true);

    expect($component->get('selected'))->toContain((string) $a->id, (string) $b->id);

    $component->call('toggleSelectAll', false)->assertSet('selected', []);
});

it('renders the Phase-4 sub-hire / transfer Action options disabled', function () {
    [$opportunity] = makeAssetsTabOrder($this->owner, $this->store, $this->product);

    $this->actingAs($this->owner);

    Volt::test('opportunities.assets', ['opportunity' => $opportunity])
        ->assertSee('Set sub-rent supplier — coming in Phase 4')
        ->assertSee('Transfer in — coming in Phase 4')
        ->assertSee('Clear transfer — coming in Phase 4');
});

it('does not offer book out on a quotation (assets allocated but not an order)', function () {
    Auth::login($this->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Quote slice',
        'store_id' => $this->store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $this->product->name,
        'itemable_id' => $this->product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    $opportunity = $opportunity->fresh();
    $item = $opportunity->items()->firstOrFail();
    $asset = makeAssetsTabAsset($this->store, $this->product);
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    $this->actingAs($this->owner);

    // On a quotation the allocated asset's chevron offers Prepare but withholds the
    // Book out action; the teleported row menu calls $wire.prepare but not dispatchAsset.
    Volt::test('opportunities.assets', ['opportunity' => $opportunity->fresh()])
        ->assertSee('$wire.prepare', escape: false)
        ->assertDontSee('$wire.dispatchAsset', escape: false);

    // And invoking dispatch directly is rejected — the asset stays Allocated.
    Volt::test('opportunities.assets', ['opportunity' => $opportunity->fresh()])
        ->call('dispatchAsset', OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole()->id);

    expect(OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole()->status)
        ->toBe(AssetAssignmentStatus::Allocated);
});

it('blocks book out under a Block store policy with an unresolved shortage (422 surfaced)', function () {
    $this->store->update(['shortage_dispatch_policy' => ShortageDispatchPolicy::Block->value]);

    [$opportunityA, $itemA] = makeAssetsTabOrder($this->owner, $this->store, $this->product, '1');
    $asset = makeAssetsTabAsset($this->store, $this->product);

    Auth::login($this->owner);
    $assignmentA = (new AllocateAsset)($itemA, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    // A separate committed demand consumes the asset's availability so the dispatched
    // line is short at dispatch time and the Block policy fires.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-09-01T09:00:00Z'), Carbon::parse('2026-09-05T17:00:00Z'))
        ->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 5,
            'source_type' => 'opportunity_item',
            'source_id' => 980001,
            'metadata' => [],
        ]);

    $this->actingAs($this->owner);

    // Both the per-row book-out and the scan-bar book-out are gated.
    Volt::test('opportunities.assets', ['opportunity' => $opportunityA->fresh()])
        ->call('dispatchAsset', $assignmentA->id)
        ->assertSee('Dispatch is blocked', escape: false);

    expect(OpportunityItemAsset::query()->whereKey($assignmentA->id)->firstOrFail()->status)
        ->toBe(AssetAssignmentStatus::Allocated);
});

it('renders read-only for a view-only user (no row actions, disabled controls)', function () {
    [$opportunity, $item] = makeAssetsTabOrder($this->owner, $this->store, $this->product);
    $asset = makeAssetsTabAsset($this->store, $this->product);

    Auth::login($this->owner);
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    Volt::test('opportunities.assets', ['opportunity' => $opportunity->fresh()])
        ->assertSet('canEdit', false)
        ->assertSee($this->product->name)
        ->assertDontSee('wire:click="prepare', escape: false);
});

it('rejects an illegal fulfilment call and leaves the status unchanged', function () {
    [$opportunity, $item] = makeAssetsTabOrder($this->owner, $this->store, $this->product);
    $asset = makeAssetsTabAsset($this->store, $this->product);

    Auth::login($this->owner);
    $assignment = (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    $this->actingAs($this->owner);

    // An Allocated asset cannot be marked on hire (only a Dispatched one can). The UI
    // never offers the button, but invoking the method must reject the 422 and leave
    // the asset Allocated.
    Volt::test('opportunities.assets', ['opportunity' => $opportunity])
        ->call('markOnHire', $assignment->id);

    expect(OpportunityItemAsset::query()->whereKey($assignment->id)->firstOrFail()->status)
        ->toBe(AssetAssignmentStatus::Allocated);
});

it('renders the Actions split-button on the assets tab (#1)', function () {
    [$opportunity] = makeAssetsTabOrder($this->owner, $this->store, $this->product);

    $this->actingAs($this->owner);

    // The shared Actions split-button (state-transition menu) must render on the
    // tab pages, not just Overview — the tab now `use`s HasOpportunityActions and
    // passes showActions => true to the shared header.
    Volt::test('opportunities.assets', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('Actions')
        // An order surfaces the Clone action verdict, computed by the shared trait.
        ->assertSee('Clone');
});

it('runs a shared Actions transition (clone) from the assets tab (#1)', function () {
    [$opportunity] = makeAssetsTabOrder($this->owner, $this->store, $this->product);

    $this->actingAs($this->owner);

    // The shared trait's cloneOpportunity wire method must work on the tab component.
    Volt::test('opportunities.assets', ['opportunity' => $opportunity])
        ->call('cloneOpportunity')
        ->assertRedirect();

    expect(Opportunity::query()->count())->toBe(2);
});

it('deallocates a row asset via the per-row action method (#3)', function () {
    [$opportunity, $item] = makeAssetsTabOrder($this->owner, $this->store, $this->product);
    $asset = makeAssetsTabAsset($this->store, $this->product);

    Auth::login($this->owner);
    $assignment = (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    $this->actingAs($this->owner);

    // The per-row action menu is teleported to <body>; its buttons call $wire.<method>
    // so the actions still fire. The server method itself must remove the assignment.
    Volt::test('opportunities.assets', ['opportunity' => $opportunity->fresh()])
        ->call('deallocate', $assignment->id);

    expect(OpportunityItemAsset::query()->whereKey($assignment->id)->exists())->toBeFalse();
});
