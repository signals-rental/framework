<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Models\AvailabilityEvent;
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
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

function makeOpportunityWithDates(Store $store): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Demand',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('creates a single product-level demand for a bulk product line', function () {
    $opportunity = makeOpportunityWithDates($this->store);
    $product = Product::factory()->rental()->bulk()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '3',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->items()->firstOrFail();

    $demands = Demand::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->get();

    expect($demands)->toHaveCount(1);
    expect($demands->first()->asset_id)->toBeNull()
        ->and((int) $demands->first()->quantity)->toBe(3);
});

it('creates one demand per allocated asset for a serialised product line', function () {
    $opportunity = makeOpportunityWithDates($this->store);
    $product = Product::factory()->rental()->serialised()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '2',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->items()->firstOrFail();

    // Allocate two serialised assets to the line.
    $levelA = StockLevel::factory()->serialised()->create();
    $levelB = StockLevel::factory()->serialised()->create();
    OpportunityItemAsset::factory()->for($item, 'item')->create(['stock_level_id' => $levelA->id]);
    OpportunityItemAsset::factory()->for($item, 'item')->create(['stock_level_id' => $levelB->id]);

    // Re-sync demands now the assets exist (a quantity change is the natural way
    // to drive a resync through the lifecycle).
    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '2']));

    $demands = Demand::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->get();

    expect($demands)->toHaveCount(2)
        ->and($demands->whereNotNull('asset_id')->count())->toBe(2);
});

it('updates demands when the quantity changes', function () {
    $opportunity = makeOpportunityWithDates($this->store);
    $product = Product::factory()->rental()->bulk()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->items()->firstOrFail();
    expect((int) Demand::query()->where('source_id', $item->id)->sole()->quantity)->toBe(1);

    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '4']));

    expect((int) Demand::query()->where('source_id', $item->id)->sole()->quantity)->toBe(4);
});

it('voids demands when the line is removed', function () {
    $opportunity = makeOpportunityWithDates($this->store);
    $product = Product::factory()->rental()->bulk()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '2',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->items()->firstOrFail();
    $itemId = $item->id;

    (new RemoveOpportunityItem)($item->refresh());

    $demands = Demand::query()->where('source_id', $itemId)->get();

    // releaseDemands voids rather than deletes.
    expect($demands)->not->toBeEmpty()
        ->and($demands->every(fn ($d): bool => $d->phase === DemandPhase::Void && $d->is_active === false))->toBeTrue();
});

it('rebuilds demands idempotently on replay without churning availability events', function () {
    $opportunity = makeOpportunityWithDates($this->store);
    $product = Product::factory()->rental()->bulk()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '2',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $item = $opportunity->items()->firstOrFail();

    $demandsBefore = Demand::query()->where('source_id', $item->id)->count();
    $availabilityEventsBefore = AvailabilityEvent::query()->count();

    expect($demandsBefore)->toBe(1)
        ->and($availabilityEventsBefore)->toBeGreaterThan(0);

    // Replay re-runs every handle() (projection + totals + audit), but the demand
    // sync is wrapped in Verbs::unlessReplaying(), so demand rows and
    // availability_events must NOT be duplicated.
    Verbs::replay();

    expect(Demand::query()->where('source_id', $item->id)->count())->toBe($demandsBefore)
        ->and(AvailabilityEvent::query()->count())->toBe($availabilityEventsBefore);

    // The priced projection survives replay unchanged.
    expect((int) OpportunityItem::query()->whereKey($item->id)->value('total'))->toBe(0)
        ->and(Opportunity::query()->whereKey($opportunity->id)->value('charge_total'))->toBeInt();
});
