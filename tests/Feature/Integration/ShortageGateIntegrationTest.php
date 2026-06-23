<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
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
use App\Models\ShortageAcknowledgement;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Exceptions\EventNotValid;

/*
|--------------------------------------------------------------------------
| M6 — Shortage gate + R1 invariant guards (composed at the boundaries)
|--------------------------------------------------------------------------
|
| These integration tests exercise the convert-to-order shortage gate
| (Block/Warn/Allow) and the R1 lifecycle invariants through the real
| actions, plus the postpone → Held demand retention (R1).
|
*/

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * A quotation whose single product line is short under the given policy:
 * 5 units held, 4 committed elsewhere over the window, this line wants 3.
 *
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function shortQuotationForGate(Store $store, ShortagePolicy $policy): array
{
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
        'subject' => 'Gate slice',
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

it('blocks convert-to-order under a Block policy when a shortage exists and rolls back', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $this->actingAs($user);

    [$opportunity] = shortQuotationForGate($this->store, ShortagePolicy::Block);

    expect(fn () => (new ConvertToOrder)($opportunity))->toThrow(ValidationException::class);

    // Atomic: still a quotation, no acknowledgement persisted.
    expect($opportunity->fresh()->state)->toBe(OpportunityState::Quotation)
        ->and(ShortageAcknowledgement::query()->count())->toBe(0);
});

it('proceeds under a Warn policy, recording an acknowledgement, and lands an Order', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $this->actingAs($user);

    [$opportunity] = shortQuotationForGate($this->store, ShortagePolicy::Warn);

    (new ConvertToOrder)($opportunity);

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::OrderActive)
        ->and(ShortageAcknowledgement::query()->count())->toBe(1);
});

it('proceeds under an Allow policy without recording an acknowledgement', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $this->actingAs($user);

    [$opportunity] = shortQuotationForGate($this->store, ShortagePolicy::Allow);

    (new ConvertToOrder)($opportunity);

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::OrderActive)
        ->and(ShortageAcknowledgement::query()->count())->toBe(0);
});

it('overrides a Block policy with the shortages.ignore permission, recording the override', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $user->givePermissionTo('shortages.ignore');
    $this->actingAs($user);

    [$opportunity] = shortQuotationForGate($this->store, ShortagePolicy::Block);

    (new ConvertToOrder)($opportunity, 'manager approved over the phone');

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::OrderActive);
    $ack = ShortageAcknowledgement::query()->sole();
    expect($ack->policy_at_time)->toBe(ShortagePolicy::Block)
        ->and($ack->permission_used)->toBeTrue();
});

describe('R1 lifecycle invariants', function () {
    beforeEach(function () {
        $this->actor = User::factory()->owner()->create();
        $this->actingAs($this->actor);
        $this->product = Product::factory()->rental()->serialised()->create();
    });

    it('rejects convert-to-order on a quotation with no items', function () {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Empty', 'store_id' => $this->store->id,
            'starts_at' => '2026-09-01T09:00:00Z', 'ends_at' => '2026-09-05T17:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
        (new ConvertToQuotation)($opportunity);

        expect(fn () => (new ConvertToOrder)($opportunity->refresh()))->toThrow(EventNotValid::class);
        expect($opportunity->fresh()->state)->toBe(OpportunityState::Quotation);
    });

    it('rejects completing an order while an asset is still out on hire', function () {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Unreturned', 'store_id' => $this->store->id,
            'starts_at' => '2026-09-01T09:00:00Z', 'ends_at' => '2026-09-05T17:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
        (new ConvertToQuotation)($opportunity);
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $this->product->name, 'itemable_id' => $this->product->id,
            'itemable_type' => Product::class, 'quantity' => '1',
            'transaction_type' => LineItemTransactionType::Rental->value,
        ]));
        (new ConvertToOrder)($opportunity->refresh());
        $item = $opportunity->items()->firstOrFail();
        $asset = StockLevel::factory()->serialised()->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);
        (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
        $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();
        (new DispatchAsset)($row, DispatchAssetData::from([]));

        // Asset is On Hire — completing must be rejected.
        expect(fn () => (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderComplete))
            ->toThrow(EventNotValid::class);
        expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::OrderOnHire);
    });

    it('rejects cancelling an order while an asset is still out on hire', function () {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Cancel-blocked', 'store_id' => $this->store->id,
            'starts_at' => '2026-09-01T09:00:00Z', 'ends_at' => '2026-09-05T17:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
        (new ConvertToQuotation)($opportunity);
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $this->product->name, 'itemable_id' => $this->product->id,
            'itemable_type' => Product::class, 'quantity' => '1',
        ]));
        (new ConvertToOrder)($opportunity->refresh());
        $item = $opportunity->items()->firstOrFail();
        $asset = StockLevel::factory()->serialised()->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);
        (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
        $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();
        (new DispatchAsset)($row, DispatchAssetData::from([]));

        expect(fn () => (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderCancelled))
            ->toThrow(EventNotValid::class);
        expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::OrderOnHire);
    });

    it('retains demand as Held when a reserved quotation is postponed (R1)', function () {
        $product = Product::factory()->rental()->bulk()->create();
        StockLevel::factory()->bulk()->create(['product_id' => $product->id, 'store_id' => $this->store->id, 'quantity_held' => 10]);

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Postpone', 'store_id' => $this->store->id,
            'starts_at' => '2026-09-01T09:00:00Z', 'ends_at' => '2026-09-05T17:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
        (new ConvertToQuotation)($opportunity);
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $product->name, 'itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => '3',
        ]));
        $item = $opportunity->items()->firstOrFail();

        // Reserve → demand becomes Committed (active).
        (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationReserved);
        expect(Demand::query()->where('source_id', $item->id)->sole()->phase)->toBe(DemandPhase::Committed);

        // Postpone → demand RETAINED as Held (not released), still active.
        (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationPostponed);
        $demand = Demand::query()->where('source_id', $item->id)->sole();
        expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::QuotationPostponed)
            ->and($demand->phase)->toBe(DemandPhase::Held)
            ->and($demand->is_active)->toBeTrue();
    });
});
