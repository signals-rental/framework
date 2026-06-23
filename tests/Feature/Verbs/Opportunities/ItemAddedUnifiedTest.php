<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\ItemAdded;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

/**
 * Build a real event-sourced opportunity (so it carries a Verbs state_id usable by
 * the item events) with concrete dates so rate-backed lines price deterministically.
 */
function makeUnifiedOpportunity(Store $store): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Unified',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

/**
 * Fire an ItemAdded event the way the AddOpportunityItem action does — allocate the
 * replay-stable projection PK via the SequenceAllocator, then fire + commit inside a
 * single DB transaction. Returns the allocated item id.
 *
 * The action itself is NOT used: it is not yet updated for the unified params.
 *
 * @param  array<string, mixed>  $payload
 */
function fireItemAdded(Opportunity $opportunity, array $payload): int
{
    $itemId = 0;

    DB::transaction(function () use ($opportunity, $payload, &$itemId): void {
        $itemId = app(SequenceAllocator::class)->next('opportunity_items');

        ItemAdded::fire(array_merge([
            'opportunity_item_id' => $itemId,
            'opportunity_id' => $opportunity->id,
            'starts_at' => '2026-07-01T09:00:00Z',
            'ends_at' => '2026-07-05T17:00:00Z',
        ], $payload));

        Verbs::commit();
    });

    return $itemId;
}

it('adds a group row with no pricing and no demand', function () {
    $opportunity = makeUnifiedOpportunity($this->store);

    $itemId = fireItemAdded($opportunity, [
        'item_type' => 'group',
        'name' => 'Audio Package',
        'quantity' => '1',
        'path' => '0001',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]);

    $item = OpportunityItem::query()->whereKey($itemId)->firstOrFail();

    expect($item->item_type)->toBe(OpportunityItemType::Group)
        ->and((int) $item->total)->toBe(0)
        ->and($item->path)->toBe('0001')
        ->and($item->itemable_id)->toBeNull()
        ->and($item->itemable_type)->toBeNull();

    $demands = Demand::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $itemId)
        ->get();

    expect($demands)->toBeEmpty();
});

it('adds a product row that is priced and generates demand', function () {
    $opportunity = makeUnifiedOpportunity($this->store);
    $product = Product::factory()->rental()->bulk()->create();

    $itemId = fireItemAdded($opportunity, [
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => 'product',
        'name' => $product->name,
        'quantity' => '3',
        'path' => '0002',
        'manual_unit_price' => 5000,
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]);

    $item = OpportunityItem::query()->whereKey($itemId)->firstOrFail();

    expect($item->item_type)->toBe(OpportunityItemType::Product)
        ->and($item->path)->toBe('0002')
        ->and($item->itemable_id)->toBe($product->id)
        ->and($item->itemable_type)->toBe(Product::class)
        ->and((int) $item->total)->toBeGreaterThan(0); // 3 * 5000 manual price

    $demands = Demand::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $itemId)
        ->get();

    expect($demands)->toHaveCount(1)
        ->and((int) $demands->first()->quantity)->toBe(3);
});

it('reproduces the unified structure unchanged on replay', function () {
    $opportunity = makeUnifiedOpportunity($this->store);
    $product = Product::factory()->rental()->bulk()->create();

    $itemId = fireItemAdded($opportunity, [
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => 'product',
        'name' => $product->name,
        'quantity' => '2',
        'path' => '0003',
        'manual_unit_price' => 5000,
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]);

    Verbs::replay();

    $item = OpportunityItem::query()->whereKey($itemId)->firstOrFail();

    expect($item->path)->toBe('0003')
        ->and($item->item_type)->toBe(OpportunityItemType::Product)
        ->and($item->itemable_id)->toBe($product->id)
        ->and($item->itemable_type)->toBe(Product::class);
});
