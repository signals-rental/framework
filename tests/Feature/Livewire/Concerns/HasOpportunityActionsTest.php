<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DeleteOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\SetDealPrice;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\SetDealPriceData;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Enums\ShortagePolicy;
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

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function covLiveOpportunity(User $actor, int $storeId, string $subject = 'COV live'): Opportunity
{
    Auth::login($actor);

    $result = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => $subject,
        'store_id' => $storeId,
    ]));

    return Opportunity::query()->whereKey($result->id)->firstOrFail();
}

/**
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function covShortQuotationForGate(Store $store, ShortagePolicy $policy, User $actor): array
{
    Auth::login($actor);
    $store->update(['shortage_policy' => $policy->value]);
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
        'subject' => 'Validation flash',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '3',
    ]));
    (new ConvertToQuotation)($opportunity->fresh());

    return [$opportunity->fresh(), $opportunity->items()->firstOrFail()];
}

it('ignores openConvertModal and openConfirmModal calls with unknown keys', function () {
    $opportunity = Opportunity::factory()->create();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('openConvertModal', 'not_a_real_key')
        ->assertSet('pendingConvertKey', null)
        ->call('openConfirmModal', 'not_a_real_key')
        ->assertSet('pendingConfirmKey', null);
});

it('returns early from confirmConvert and confirmTransition when no pending key is set', function () {
    $opportunity = Opportunity::factory()->quotation()->create();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('confirmConvert')
        ->assertNotDispatched('toast')
        ->call('confirmTransition')
        ->assertNotDispatched('toast');
});

it('converts to order via the convert modal', function () {
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'Convert to order modal');
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 5000,
    ]));

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->call('openConvertModal', 'convert_to_order')
        ->assertSet('pendingConvertKey', 'convert_to_order')
        ->call('confirmConvert')
        ->assertSet('pendingConvertKey', null)
        ->assertDispatched('toast', type: 'success', message: 'Converted to order');

    expect($opportunity->fresh()->state)->toBe(OpportunityState::Order);
});

it('confirms reinstate, reopen, revert, and archive transitions via the shared modal', function () {
    $lost = covLiveOpportunity($this->owner, $this->store->id, 'Reinstate modal');
    (new ConvertToQuotation)($lost);
    (new ChangeOpportunityStatus)($lost->fresh(), OpportunityStatus::QuotationLost);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $lost->fresh()])
        ->call('openConfirmModal', 'reinstate')
        ->call('confirmTransition')
        ->assertDispatched('toast', type: 'success', message: 'Opportunity reinstated');

    expect($lost->fresh()->statusEnum())->toBe(OpportunityStatus::QuotationProvisional);

    $completed = covLiveOpportunity($this->owner, $this->store->id, 'Reopen modal');
    (new ConvertToQuotation)($completed);
    (new AddOpportunityItem)($completed->fresh(), AddOpportunityItemData::from([
        'name' => 'Kit',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    (new ConvertToOrder)($completed->fresh());
    (new ChangeOpportunityStatus)($completed->fresh(), OpportunityStatus::OrderComplete);

    Volt::test('opportunities.show', ['opportunity' => $completed->fresh()])
        ->call('openConfirmModal', 'reopen')
        ->call('confirmTransition')
        ->assertDispatched('toast', type: 'success', message: 'Order re-opened');

    expect($completed->fresh()->statusEnum())->toBe(OpportunityStatus::OrderActive);

    $order = covLiveOpportunity($this->owner, $this->store->id, 'Revert modal');
    (new ConvertToQuotation)($order);
    (new AddOpportunityItem)($order->fresh(), AddOpportunityItemData::from([
        'name' => 'Undispatched',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    (new ConvertToOrder)($order->fresh());

    Volt::test('opportunities.show', ['opportunity' => $order->fresh()])
        ->call('openConfirmModal', 'revert_to_quotation')
        ->call('confirmTransition')
        ->assertDispatched('toast', type: 'success', message: 'Reverted to quotation');

    expect($order->fresh()->state)->toBe(OpportunityState::Quotation);

    $quote = covLiveOpportunity($this->owner, $this->store->id, 'Revert draft modal');
    (new ConvertToQuotation)($quote);

    Volt::test('opportunities.show', ['opportunity' => $quote->fresh()])
        ->call('openConfirmModal', 'revert_to_draft')
        ->call('confirmTransition')
        ->assertDispatched('toast', type: 'success', message: 'Reverted to draft');

    expect($quote->fresh()->state)->toBe(OpportunityState::Draft);

    $archiveTarget = covLiveOpportunity($this->owner, $this->store->id, 'Archive modal');

    Volt::test('opportunities.show', ['opportunity' => $archiveTarget])
        ->call('openConfirmModal', 'delete')
        ->call('confirmTransition')
        ->assertRedirect(route('opportunities.index'));

    expect($archiveTarget->fresh()->trashed())->toBeTrue();
});

it('offers no statusOptions when the opportunity is closed', function () {
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'Closed picker');
    (new ConvertToQuotation)($opportunity);
    (new ChangeOpportunityStatus)($opportunity->fresh(), OpportunityStatus::QuotationLost);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->assertViewHas('statusOptions', fn (array $options): bool => $options === []);
});

it('surfaces deal_price_active and dispatched verdicts in availableActions', function () {
    $withDeal = covLiveOpportunity($this->owner, $this->store->id, 'Deal blocks lock');
    (new ConvertToQuotation)($withDeal);
    (new SetDealPrice)($withDeal->fresh(), SetDealPriceData::from(['deal_total' => '50.00']));

    $this->actingAs($this->owner);

    $dealActions = collect(
        Volt::test('opportunities.show', ['opportunity' => $withDeal->fresh()])->viewData('availableActions')
    )->keyBy('key');

    expect($dealActions['unlock_locks']['allowed'])->toBeFalse()
        ->and($dealActions['unlock_locks']['code'])->toBe('deal_price_active');

    $dispatched = covLiveOpportunity($this->owner, $this->store->id, 'Bulk dispatched');
    (new ConvertToQuotation)($dispatched);
    $product = Product::factory()->rental()->serialised()->create();
    (new AddOpportunityItem)($dispatched->fresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($dispatched->fresh());
    $item = $dispatched->fresh()->items()->firstOrFail();
    $stock = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $stock->id]));
    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();
    (new DispatchAsset)($row, DispatchAssetData::from([]));

    $dispatchActions = collect(
        Volt::test('opportunities.show', ['opportunity' => $dispatched->fresh()])->viewData('availableActions')
    )->keyBy('key');

    expect($dispatchActions['revert_to_quotation']['allowed'])->toBeFalse()
        ->and($dispatchActions['revert_to_quotation']['code'])->toBe('dispatched');
});

it('unlocks rates when locks are active via unlockRates', function () {
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'Unlock modal');
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 5000,
    ]));
    (new ConvertToOrder)($opportunity->fresh());

    expect($opportunity->fresh()->exchange_rate_locked)->toBeTrue()
        ->and($opportunity->fresh()->tax_locked)->toBeTrue();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->call('unlockRates');

    $opportunity->refresh();

    expect($opportunity->exchange_rate_locked)->toBeFalse()
        ->and($opportunity->tax_locked)->toBeFalse();
});

it('flashes permission errors for clone, archive, restore, and runTransition failures', function () {
    $draft = covLiveOpportunity($this->owner, $this->store->id, 'Auth guards draft');
    $archived = covLiveOpportunity($this->owner, $this->store->id, 'Auth guards archived');
    (new DeleteOpportunity)($archived);
    $archived = $archived->fresh();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    Volt::test('opportunities.show', ['opportunity' => $draft])
        ->call('cloneOpportunity')
        ->assertSee('You do not have permission to clone this opportunity.');

    Volt::test('opportunities.show', ['opportunity' => $draft])
        ->call('archive')
        ->assertSee('You do not have permission to archive this opportunity.');

    Volt::test('opportunities.show', ['opportunity' => $archived])
        ->call('restore')
        ->assertSee('You do not have permission to restore this opportunity.');
});

it('flashes the first validation error from runTransition', function () {
    Queue::fake();

    $salesUser = User::factory()->create();
    $salesUser->assignRole('Sales');

    [$opportunity] = covShortQuotationForGate($this->store, ShortagePolicy::Block, $salesUser);

    $this->actingAs($salesUser);

    $flashed = captureFlashedMessages(function () use ($opportunity): void {
        Volt::test('opportunities.show', ['opportunity' => $opportunity])
            ->call('convertToOrder')
            ->assertSee('unresolved shortage');
    });

    expect($flashed['error'] ?? null)
        ->toContain('unresolved shortage')
        ->and($opportunity->fresh()->state)->toBe(OpportunityState::Quotation);
});

it('redirects cloneOpportunity on success', function () {
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'Clone redirect');

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('cloneOpportunity')
        ->assertRedirect();

    expect(Opportunity::query()->count())->toBe(2);
});
