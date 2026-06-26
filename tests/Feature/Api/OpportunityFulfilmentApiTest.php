<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\PrepareAsset;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create();
    $this->serialProduct = Product::factory()->rental()->serialised()->create();
    $this->bulkProduct = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $this->bulkProduct->id,
        'store_id' => $this->store->id,
        'quantity_held' => 100,
    ]);
});

function fulfilmentWriteToken(User $user): string
{
    return $user->createToken('test', ['opportunities:write'])->plainTextToken;
}

function fulfilmentReadToken(User $user): string
{
    return $user->createToken('test', ['opportunities:read'])->plainTextToken;
}

/**
 * @return array{0: Opportunity, 1: OpportunityItem, 2: StockLevel, 3: OpportunityItemAsset}
 */
function fulfilmentAllocatedAsset(User $actor, Store $store, Product $product): array
{
    Auth::login($actor);

    try {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Fulfilment API',
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
            'quantity' => '2',
            'transaction_type' => LineItemTransactionType::Rental->value,
        ]));
        (new ConvertToOrder)($opportunity->refresh());

        $item = $opportunity->items()->firstOrFail();
        $stock = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
        ]);
        (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $stock->id]));
        $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();

        return [$opportunity->refresh(), $item, $stock, $row];
    } finally {
        Auth::logout();
    }
}

/**
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function fulfilmentBulkLine(User $actor, Store $store, Product $product, string $quantity = '50'): array
{
    Auth::login($actor);

    try {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Bulk fulfilment API',
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

        return [$opportunity->refresh(), $opportunity->items()->firstOrFail()];
    } finally {
        Auth::logout();
    }
}

describe('PATCH /api/v1/opportunities/{id}/items/{item}/assets/{asset}', function () {
    it('runs the dispatch, on_hire, return, and check asset actions', function () {
        [$opportunity, $item, , $row] = fulfilmentAllocatedAsset($this->owner, $this->store, $this->serialProduct);
        $token = fulfilmentWriteToken($this->owner);
        $base = "/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/assets/{$row->id}";

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, ['action' => 'dispatch'])
            ->assertOk()
            ->assertJsonPath('asset.status', AssetAssignmentStatus::Dispatched->value);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, ['action' => 'on_hire'])
            ->assertOk()
            ->assertJsonPath('asset.status', AssetAssignmentStatus::OnHire->value);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, ['action' => 'return'])
            ->assertOk()
            ->assertJsonPath('asset.status', AssetAssignmentStatus::CheckedIn->value);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, ['action' => 'check', 'condition' => AssetCondition::Good->value])
            ->assertOk()
            ->assertJsonPath('asset.status', AssetAssignmentStatus::Finalised->value);
    });

    it('reverts asset preparation and manages container assignment', function () {
        [$opportunity, $item, , $row] = fulfilmentAllocatedAsset($this->owner, $this->store, $this->serialProduct);
        Auth::login($this->owner);
        (new PrepareAsset)($row);
        Auth::logout();

        $token = fulfilmentWriteToken($this->owner);
        $base = "/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/assets/{$row->id}";

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, ['action' => 'revert'])
            ->assertOk()
            ->assertJsonPath('asset.status', AssetAssignmentStatus::Allocated->value);

        $container = StockLevel::factory()->create(['store_id' => $this->store->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, ['action' => 'set_container', 'container_stock_level_id' => $container->id])
            ->assertOk()
            ->assertJsonPath('asset.container_stock_level_id', $container->id);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, ['action' => 'clear_container'])
            ->assertOk()
            ->assertJsonPath('asset.container_stock_level_id', null);
    });

    it('substitutes the physical asset on an assignment', function () {
        [$opportunity, $item, , $row] = fulfilmentAllocatedAsset($this->owner, $this->store, $this->serialProduct);
        $replacement = StockLevel::factory()->serialised()->create([
            'product_id' => $this->serialProduct->id,
            'store_id' => $this->store->id,
        ]);
        $token = fulfilmentWriteToken($this->owner);
        $base = "/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/assets/{$row->id}";

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, [
                'action' => 'substitute',
                'new_stock_level_id' => $replacement->id,
                'reason' => 'damaged unit',
            ])
            ->assertOk()
            ->assertJsonPath('asset.stock_level_id', $replacement->id);
    });

    it('reverts asset dispatch status via revert_status', function () {
        [$opportunity, $item, , $row] = fulfilmentAllocatedAsset($this->owner, $this->store, $this->serialProduct);
        $token = fulfilmentWriteToken($this->owner);
        $base = "/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/assets/{$row->id}";

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, ['action' => 'dispatch'])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, [
                'action' => 'revert_status',
                'revert_to' => AssetAssignmentStatus::Prepared->value,
                'reason' => 'loaded by mistake',
            ])
            ->assertOk()
            ->assertJsonPath('asset.status', AssetAssignmentStatus::Prepared->value);
    });

    it('404s when the asset belongs to another line item', function () {
        [$opportunityA, $itemA, , $rowA] = fulfilmentAllocatedAsset($this->owner, $this->store, $this->serialProduct);
        [$opportunityB, $itemB] = fulfilmentBulkLine($this->owner, $this->store, $this->bulkProduct);
        $token = fulfilmentWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunityB->id}/items/{$itemB->id}/assets/{$rowA->id}", [
                'action' => 'prepare',
            ])
            ->assertNotFound();

        expect($opportunityA->id)->not->toBe($opportunityB->id);
    });
});

describe('PATCH /api/v1/opportunities/{id}/items/{item}/fulfilment', function () {
    it('dispatches, returns, and adjusts bulk quantity', function () {
        [$opportunity, $item] = fulfilmentBulkLine($this->owner, $this->store, $this->bulkProduct, '50');
        $token = fulfilmentWriteToken($this->owner);
        $base = "/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/fulfilment";

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, ['action' => 'dispatch', 'quantity' => '30'])
            ->assertOk()
            ->assertJsonPath('item.dispatched_quantity', '30.00');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, ['action' => 'return', 'quantity' => '10'])
            ->assertOk()
            ->assertJsonPath('item.returned_quantity', '10.00');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, ['action' => 'adjust', 'new_quantity' => '40', 'reason' => 'scope change'])
            ->assertOk()
            ->assertJsonPath('item.quantity', '40.00');
    });

    it('rejects an unknown bulk fulfilment action with 422', function () {
        [$opportunity, $item] = fulfilmentBulkLine($this->owner, $this->store, $this->bulkProduct);
        $token = fulfilmentWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/fulfilment", [
                'action' => 'teleport',
            ])
            ->assertStatus(422);
    });
});

describe('batch fulfilment endpoints', function () {
    it('quick_prepares allocated assets', function () {
        [$opportunity, $item, , $row] = fulfilmentAllocatedAsset($this->owner, $this->store, $this->serialProduct);
        $token = fulfilmentWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/quick_prepare", [
                'asset_ids' => [$row->id],
            ])
            ->assertOk();

        expect($row->refresh()->status)->toBe(AssetAssignmentStatus::Prepared);
    });

    it('quick_book_out dispatches allocated assets in one request', function () {
        [$opportunity, $item, , $row] = fulfilmentAllocatedAsset($this->owner, $this->store, $this->serialProduct);
        $token = fulfilmentWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/quick_book_out", [
                'asset_ids' => [$row->id],
            ])
            ->assertOk();

        expect($row->refresh()->status)->toBe(AssetAssignmentStatus::Dispatched);
    });

    it('quick_check_in returns dispatched assets', function () {
        [$opportunity, $item, , $row] = fulfilmentAllocatedAsset($this->owner, $this->store, $this->serialProduct);
        $token = fulfilmentWriteToken($this->owner);
        $assetBase = "/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/assets/{$row->id}";

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($assetBase, ['action' => 'dispatch'])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/quick_check_in", [
                'asset_ids' => [$row->id],
            ])
            ->assertOk();

        expect($row->refresh()->status)->toBe(AssetAssignmentStatus::CheckedIn);
    });

    it('requires the opportunities:write ability for quick_book_out', function () {
        [$opportunity, , , $row] = fulfilmentAllocatedAsset($this->owner, $this->store, $this->serialProduct);
        $token = fulfilmentReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/quick_book_out", [
                'asset_ids' => [$row->id],
            ])
            ->assertForbidden();
    });
});

describe('PATCH /api/v1/opportunities/{id}/items/{item} extended fields', function () {
    it('updates unit price, discount, optional flag, and dates', function () {
        [$opportunity, $item] = fulfilmentBulkLine($this->owner, $this->store, $this->bulkProduct, '5');
        $token = fulfilmentWriteToken($this->owner);
        $base = "/api/v1/opportunities/{$opportunity->id}/items/{$item->id}";

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson($base, [
                'unit_price' => '60.00',
                'discount_percent' => '10',
                'is_optional' => true,
                'starts_at' => '2026-09-02T09:00:00Z',
                'ends_at' => '2026-09-04T17:00:00Z',
            ])
            ->assertOk();

        $item->refresh();
        expect($item->unit_price)->toBe(6000) // integer minor-units cast
            ->and($item->discount_percent)->toBe('10.00') // decimal:2 cast returns a string
            ->and($item->is_optional)->toBeTrue()
            ->and($item->starts_at)->not->toBeNull();
    });

    it('substitutes a line via the RMS item_id alias', function () {
        [$opportunity, $item] = fulfilmentBulkLine($this->owner, $this->store, $this->bulkProduct, '5');
        $replacement = Product::factory()->rental()->bulk()->create();
        $token = fulfilmentWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunity->id}/items/{$item->id}", [
                'item_id' => $replacement->id,
                'itemable_type' => Product::class,
            ])
            ->assertOk();

        expect($item->fresh()->itemable_id)->toBe($replacement->id);
    });
});
