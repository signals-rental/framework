<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\QuickBookOut;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\QuickBookOutData;
use App\Enums\LineItemTransactionType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->rental()->serialised()->create();
});

/**
 * Build an event-sourced Order with a single serialised line, allocate one asset,
 * and return the allocated assignment row (ready to book out).
 */
function bookableAssetRow(Store $store, Product $product): OpportunityItemAsset
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Bookable',
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
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($opportunity->refresh());

    /** @var OpportunityItem $item */
    $item = $opportunity->items()->firstOrFail();
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    return OpportunityItemAsset::query()
        ->where('opportunity_item_id', $item->id)
        ->where('stock_level_id', $asset->id)
        ->sole();
}

it('rejects a quick book-out asset that belongs to a different opportunity (404)', function () {
    // Target order whose book-out we attempt.
    $targetRow = bookableAssetRow($this->store, $this->product);
    $targetOpportunity = $targetRow->item->opportunity()->firstOrFail();

    // A foreign order's allocated asset row — belongs to a DIFFERENT opportunity.
    $foreignRow = bookableAssetRow($this->store, $this->product);

    expect($foreignRow->item->opportunity_id)->not->toBe($targetOpportunity->id);

    expect(fn () => (new QuickBookOut)($targetOpportunity->refresh(), QuickBookOutData::from([
        'asset_ids' => [$foreignRow->id],
    ])))->toThrow(NotFoundHttpException::class, 'does not belong to the opportunity');
});
