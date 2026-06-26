<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ClearDealPrice;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Actions\Opportunities\DispatchBulkQuantity;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Actions\Opportunities\SetDealPrice;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Data\Opportunities\SetDealPriceData;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Verbs\Events\Opportunities\BulkQuantityAdjusted;
use App\Verbs\Events\Opportunities\BulkQuantityDispatched;
use App\Verbs\Events\Opportunities\ItemDiscountSet;
use App\Verbs\Events\Opportunities\OpportunityStatusChanged;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

/**
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function guardQuotationWithManualLine(Store $store): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Guard rejections',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Manual line',
        'quantity' => '2',
        'unit_price' => 5000,
    ]));

    return [$opportunity->refresh(), $opportunity->items()->firstOrFail()];
}

/**
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function guardOrderWithBulkLine(Store $store, Product $bulkProduct): array
{
    StockLevel::factory()->bulk()->create([
        'product_id' => $bulkProduct->id,
        'store_id' => $store->id,
        'quantity_held' => 100,
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Bulk guards',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $bulkProduct->name,
        'itemable_id' => $bulkProduct->id,
        'itemable_type' => Product::class,
        'quantity' => '50',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->refresh()->items()->firstOrFail();
    (new ConvertToOrder)($opportunity->refresh());

    return [$opportunity->refresh(), $item->refresh()];
}

// ---------------------------------------------------------------------------
// Shared guard branches — one representative event each (COV-5 lean tail)
// ---------------------------------------------------------------------------

it('rejects mutating a removed line item via assertItemMutable', function () {
    [, $item] = guardQuotationWithManualLine($this->store);
    $stateId = $item->state_id;

    (new RemoveOpportunityItem)($item->refresh());

    expect(OpportunityItem::query()->whereKey($item->id)->exists())->toBeFalse();

    expect(function () use ($stateId) {
        ItemDiscountSet::fire(
            opportunity_item_id: $stateId,
            discount_percent: '10',
        );
        Verbs::commit();
    })->toThrow(EventNotValid::class);
});

it('rejects setting deal price on a closed opportunity', function () {
    [$opportunity] = guardQuotationWithManualLine($this->store);

    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationLost);

    expect(fn () => (new SetDealPrice)($opportunity->refresh(), SetDealPriceData::from(['deal_total' => 7500])))
        ->toThrow(EventNotValid::class);
});

it('rejects clearing deal price on a closed opportunity', function () {
    [$opportunity] = guardQuotationWithManualLine($this->store);

    (new SetDealPrice)($opportunity->refresh(), SetDealPriceData::from(['deal_total' => 7500]));
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationLost);

    expect(fn () => (new ClearDealPrice)($opportunity->refresh()))
        ->toThrow(EventNotValid::class);
});

it('rejects bulk dispatch on a quotation', function () {
    $bulkProduct = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $bulkProduct->id,
        'store_id' => $this->store->id,
        'quantity_held' => 100,
    ]);

    [$opportunity] = guardQuotationWithManualLine($this->store);

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $bulkProduct->name,
        'itemable_id' => $bulkProduct->id,
        'itemable_type' => Product::class,
        'quantity' => '10',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->refresh()->items()->latest('id')->firstOrFail();

    expect(function () use ($item) {
        BulkQuantityDispatched::fire(
            opportunity_item_id: $item->state_id,
            quantity: '1',
        );
        Verbs::commit();
    })->toThrow(EventNotValid::class);
});

it('rejects bulk dispatch with a non-positive quantity', function () {
    $bulkProduct = Product::factory()->rental()->bulk()->create();
    [, $item] = guardOrderWithBulkLine($this->store, $bulkProduct);

    expect(function () use ($item) {
        BulkQuantityDispatched::fire(
            opportunity_item_id: $item->state_id,
            quantity: '0',
        );
        Verbs::commit();
    })->toThrow(EventNotValid::class);
});

it('rejects creating a quote version on a closed opportunity', function () {
    [$opportunity] = guardQuotationWithManualLine($this->store);

    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationLost);

    expect(fn () => (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([])))
        ->toThrow(EventNotValid::class);
});

it('rejects changing status on an already-closed opportunity', function () {
    [$opportunity] = guardQuotationWithManualLine($this->store);

    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationLost);

    expect(fn () => (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationReserved))
        ->toThrow(EventNotValid::class);
});

it('rejects changing to a status invalid for the current opportunity state', function () {
    [$opportunity] = guardQuotationWithManualLine($this->store);

    expect(function () use ($opportunity) {
        OpportunityStatusChanged::fire(
            opportunity_id: $opportunity->state_id,
            to_status: 99,
        );
        Verbs::commit();
    })->toThrow(EventNotValid::class);
});

it('rejects reducing a bulk line below its dispatched quantity via ItemQuantityChanged', function () {
    $bulkProduct = Product::factory()->rental()->bulk()->create();
    [, $item] = guardOrderWithBulkLine($this->store, $bulkProduct);

    (new DispatchBulkQuantity)($item->refresh(), BulkDispatchData::from(['quantity' => '30']));

    expect(fn () => (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '20'])))
        ->toThrow(EventNotValid::class);
});

it('rejects adjusting bulk quantity below zero', function () {
    $bulkProduct = Product::factory()->rental()->bulk()->create();
    [, $item] = guardOrderWithBulkLine($this->store, $bulkProduct);

    expect(function () use ($item) {
        BulkQuantityAdjusted::fire(
            opportunity_item_id: $item->state_id,
            new_quantity: '-1',
            reason: 'invalid',
        );
        Verbs::commit();
    })->toThrow(EventNotValid::class);
});
