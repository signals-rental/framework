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
use App\Enums\LineItemTransactionType;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;

/**
 * Exercises the opportunity read sub-resources (assets / availability / activity)
 * and the new DTO completeness fields (versions, tag_list, dispatched_quantity).
 */
beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->rental()->serialised()->create();
});

/**
 * Build an event-sourced Order line with `$quantity` serialised units, returning
 * the parent opportunity.
 */
function subResourceOrder(User $actor, Store $store, Product $product, string $quantity = '3'): Opportunity
{
    Auth::login($actor);

    try {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Sub-resource order',
            'store_id' => $store->id,
            'starts_at' => '2026-09-01T09:00:00Z',
            'ends_at' => '2026-09-05T17:00:00Z',
        ]));

        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
        (new ConvertToQuotation)($opportunity->refresh());

        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $product->name,
            'item_id' => $product->id,
            'item_type' => Product::class,
            'quantity' => $quantity,
            'transaction_type' => LineItemTransactionType::Rental->value,
        ]));

        (new ConvertToOrder)($opportunity->refresh());

        return $opportunity->refresh();
    } finally {
        Auth::logout();
    }
}

function subResourceReadToken(User $user): string
{
    return $user->createToken('test', ['opportunities:read'])->plainTextToken;
}

// ---------------------------------------------------------------------------
// DTO completeness
// ---------------------------------------------------------------------------

describe('OpportunityData completeness', function () {
    it('serialises the version summary fields and tag_list', function () {
        $opportunity = Opportunity::factory()->create([
            'tag_list' => ['vip', 'rush'],
            'active_version_id' => 0,
            'version_count' => 0,
            'has_alternatives' => false,
        ]);
        $token = subResourceReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities/'.$opportunity->id)
            ->assertOk()
            ->assertJsonPath('opportunity.tag_list', ['vip', 'rush'])
            ->assertJsonPath('opportunity.active_version_id', 0)
            ->assertJsonPath('opportunity.version_count', 0)
            ->assertJsonPath('opportunity.has_alternatives', false);
    });

    it('sideloads versions via ?include=versions', function () {
        $opportunity = Opportunity::factory()->quotation()->create();
        $version = OpportunityVersion::factory()->create([
            'opportunity_id' => $opportunity->id,
            'version_number' => 1,
            'is_active' => true,
        ]);
        $token = subResourceReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities/'.$opportunity->id.'?include=versions')
            ->assertOk()
            ->assertJsonPath('opportunity.versions.0.id', $version->id)
            ->assertJsonPath('opportunity.versions.0.version_number', 1);
    });

    it('exposes dispatched_quantity and returned_quantity on line items', function () {
        $opportunity = subResourceOrder($this->owner, $this->store, $this->product, '3');
        $token = subResourceReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities/'.$opportunity->id.'?include=items')
            ->assertOk()
            ->assertJsonStructure([
                'opportunity' => [
                    'items' => ['*' => ['dispatched_quantity', 'returned_quantity']],
                ],
            ])
            ->assertJsonPath('opportunity.items.0.dispatched_quantity', '0.00');
    });
});

// ---------------------------------------------------------------------------
// GET /opportunities/{id}/assets
// ---------------------------------------------------------------------------

describe('GET /api/v1/opportunities/{id}/assets', function () {
    it('lists every line asset assignment flat with pagination meta', function () {
        $opportunity = subResourceOrder($this->owner, $this->store, $this->product, '3');
        $item = $opportunity->allItems()->firstOrFail();

        $assetA = StockLevel::factory()->serialised()->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);
        $assetB = StockLevel::factory()->serialised()->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);

        Auth::login($this->owner);
        (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $assetA->id]));
        (new AllocateAsset)($item->refresh(), AllocateAssetData::from(['stock_level_id' => $assetB->id]));
        Auth::logout();

        $token = subResourceReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities/'.$opportunity->id.'/assets')
            ->assertOk()
            ->assertJsonStructure([
                'assets' => ['*' => ['id', 'opportunity_item_id', 'stock_level_id', 'status', 'status_label']],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 2);
    });

    it('filters assets by status with a Ransack _eq predicate', function () {
        $opportunity = subResourceOrder($this->owner, $this->store, $this->product, '2');
        $item = $opportunity->allItems()->firstOrFail();

        $asset = StockLevel::factory()->serialised()->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);
        Auth::login($this->owner);
        (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));
        Auth::logout();

        $token = subResourceReadToken($this->owner);

        // Allocated (0) matches; Dispatched (2) does not.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities/'.$opportunity->id.'/assets?q[status_eq]='.AssetAssignmentStatus::Allocated->value)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities/'.$opportunity->id.'/assets?q[status_eq]='.AssetAssignmentStatus::Dispatched->value)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    });

    it('requires the opportunities:read ability', function () {
        $opportunity = Opportunity::factory()->create();
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities/'.$opportunity->id.'/assets')
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// GET /opportunities/{id}/availability
// ---------------------------------------------------------------------------

describe('GET /api/v1/opportunities/{id}/availability', function () {
    it('returns the per-line availability picture', function () {
        $opportunity = subResourceOrder($this->owner, $this->store, $this->product, '2');
        // Give the product some stock so availability resolves.
        StockLevel::factory()->serialised()->count(2)->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);

        $token = subResourceReadToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities/'.$opportunity->id.'/availability')
            ->assertOk()
            ->assertJsonStructure([
                'availability' => ['*' => ['opportunity_item_id', 'product_id', 'store_id', 'requested_quantity', 'available_for_item', 'shortage_quantity', 'has_shortage', 'from', 'to']],
                'meta' => ['total', 'per_page', 'page'],
            ]);

        expect($response->json('availability.0.product_id'))->toBe($this->product->id);
    });

    it('requires the opportunities:read ability', function () {
        $opportunity = Opportunity::factory()->create();
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities/'.$opportunity->id.'/availability')
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// GET /opportunities/{id}/activity
// ---------------------------------------------------------------------------

describe('GET /api/v1/opportunities/{id}/activity', function () {
    it('returns the scoped audit timeline newest-first', function () {
        $opportunity = Opportunity::factory()->create();

        $older = ActionLog::factory()->create([
            'auditable_type' => Opportunity::class,
            'auditable_id' => $opportunity->id,
            'action' => 'opportunity.created',
            'created_at' => now()->subHour(),
        ]);
        $newer = ActionLog::factory()->create([
            'auditable_type' => Opportunity::class,
            'auditable_id' => $opportunity->id,
            'action' => 'opportunity.updated',
            'created_at' => now(),
        ]);
        // Noise: a log for a different opportunity must be excluded.
        ActionLog::factory()->create([
            'auditable_type' => Opportunity::class,
            'auditable_id' => $opportunity->id + 999,
            'action' => 'opportunity.created',
        ]);

        $token = $this->owner->createToken('test', ['action-log:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities/'.$opportunity->id.'/activity')
            ->assertOk()
            ->assertJsonStructure([
                'activity' => ['*' => ['id', 'action', 'auditable_id', 'created_at']],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('activity.0.id', $newer->id)
            ->assertJsonPath('activity.1.id', $older->id);
    });

    it('requires the action-log:read ability', function () {
        $opportunity = Opportunity::factory()->create();
        $token = $this->owner->createToken('test', ['opportunities:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities/'.$opportunity->id.'/activity')
            ->assertForbidden();
    });
});
