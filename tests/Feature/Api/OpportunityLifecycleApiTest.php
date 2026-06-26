<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\SetDealPrice;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\SetDealPriceData;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\CustomField;
use App\Models\CustomView;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function lifecycleWriteToken(User $user): string
{
    return $user->createToken('test', ['opportunities:write'])->plainTextToken;
}

function lifecycleReadToken(User $user): string
{
    return $user->createToken('test', ['opportunities:read'])->plainTextToken;
}

/**
 * Build a quotation carrying one manual line via the event-sourcing pipeline.
 */
function lifecycleQuotation(User $actor, Store $store): Opportunity
{
    Auth::login($actor);

    try {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Lifecycle API',
            'store_id' => $store->id,
            'starts_at' => '2026-12-01T09:00:00Z',
            'ends_at' => '2026-12-05T17:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'PA Stack',
            'quantity' => '2',
            'unit_price' => 5000,
        ]));
        (new ConvertToQuotation)($opportunity->refresh());

        return $opportunity->refresh();
    } finally {
        Auth::logout();
    }
}

describe('POST /api/v1/opportunities/{id}/clone', function () {
    it('clones an opportunity into a new draft with copied lines', function () {
        $source = lifecycleQuotation($this->owner, $this->store);
        $token = lifecycleWriteToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$source->id}/clone")
            ->assertCreated()
            ->assertJsonPath('opportunity.state', OpportunityState::Draft->value)
            ->assertJsonPath('opportunity.subject', $source->subject);

        $cloneId = $response->json('opportunity.id');
        expect($cloneId)->not->toBe($source->id)
            ->and($response->json('opportunity.items'))->not->toBeEmpty();
    });

    it('requires the opportunities:write ability', function () {
        $source = lifecycleQuotation($this->owner, $this->store);
        $token = lifecycleReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$source->id}/clone")
            ->assertForbidden();
    });
});

describe('backward transition endpoints', function () {
    it('reinstates a lost quotation via the API', function () {
        $opportunity = lifecycleQuotation($this->owner, $this->store);
        Auth::login($this->owner);
        (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::QuotationLost);
        Auth::logout();

        $token = lifecycleWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/reinstate", ['reason' => 'client returned'])
            ->assertOk()
            ->assertJsonPath('opportunity.status', OpportunityStatus::QuotationProvisional->statusValue());
    });

    it('reopens a completed order via the API', function () {
        $opportunity = lifecycleQuotation($this->owner, $this->store);
        Auth::login($this->owner);
        (new ConvertToOrder)($opportunity->refresh());
        (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::OrderComplete);
        Auth::logout();

        $token = lifecycleWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/reopen", ['reason' => 'more work'])
            ->assertOk()
            ->assertJsonPath('opportunity.status', OpportunityStatus::OrderActive->statusValue());
    });

    it('reverts an undispatched order back to a quotation via the API', function () {
        $opportunity = lifecycleQuotation($this->owner, $this->store);
        Auth::login($this->owner);
        (new ConvertToOrder)($opportunity->refresh());
        Auth::logout();

        $token = lifecycleWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/revert_to_quotation", ['reason' => 'too early'])
            ->assertOk()
            ->assertJsonPath('opportunity.state', OpportunityState::Quotation->value)
            ->assertJsonPath('opportunity.exchange_rate_locked', false);
    });

    it('reverts a provisional quotation back to draft via the API', function () {
        $opportunity = lifecycleQuotation($this->owner, $this->store);
        $token = lifecycleWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/revert_to_draft", ['reason' => 'still scoping'])
            ->assertOk()
            ->assertJsonPath('opportunity.state', OpportunityState::Draft->value);
    });

    it('rejects reverting an order with dispatched assets with 422', function () {
        $product = Product::factory()->rental()->serialised()->create();
        Auth::login($this->owner);

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Dispatched revert',
            'store_id' => $this->store->id,
            'starts_at' => '2026-12-01T09:00:00Z',
            'ends_at' => '2026-12-05T17:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
        (new ConvertToQuotation)($opportunity);
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
        ]));
        (new ConvertToOrder)($opportunity->refresh());
        $item = $opportunity->items()->firstOrFail();
        $stock = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);
        (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $stock->id]));
        $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();
        (new DispatchAsset)($row, DispatchAssetData::from([]));
        Auth::logout();

        $token = lifecycleWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/revert_to_quotation")
            ->assertStatus(422);
    });
});

describe('lock endpoints', function () {
    it('applies and releases FX/tax locks via the API', function () {
        $opportunity = lifecycleQuotation($this->owner, $this->store);
        Auth::login($this->owner);
        (new ConvertToOrder)($opportunity->refresh());
        Auth::logout();

        $token = lifecycleWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/unlock_locks", ['reason' => 'correcting rates'])
            ->assertOk()
            ->assertJsonPath('opportunity.exchange_rate_locked', false)
            ->assertJsonPath('opportunity.tax_locked', false);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/lock_locks", ['reason' => 'freeze again'])
            ->assertOk()
            ->assertJsonPath('opportunity.exchange_rate_locked', true)
            ->assertJsonPath('opportunity.tax_locked', true);
    });

    it('requires the opportunities:write ability for lock mutations', function () {
        $opportunity = lifecycleQuotation($this->owner, $this->store);
        Auth::login($this->owner);
        (new ConvertToOrder)($opportunity->refresh());
        Auth::logout();

        $token = lifecycleReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/unlock_locks")
            ->assertForbidden();
    });
});

describe('GET /api/v1/opportunities/{id} meta block', function () {
    it('reports can_edit false for a closed opportunity', function () {
        $opportunity = lifecycleQuotation($this->owner, $this->store);
        Auth::login($this->owner);
        (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::QuotationLost);
        Auth::logout();

        $token = lifecycleReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}")
            ->assertOk()
            ->assertJsonPath('meta.can_edit', false)
            ->assertJsonPath('meta.can_destroy', true);
    });
});

describe('GET /api/v1/opportunities index view projection', function () {
    it('filters custom field columns when the saved view uses cf.* keys', function () {
        CustomField::factory()->create([
            'name' => 'po_reference',
            'module_type' => 'Opportunity',
        ]);

        $opportunity = Opportunity::factory()->create(['subject' => 'CF View Test']);
        $opportunity->syncCustomFields(['po_reference' => 'PO-VIEW-1']);

        $view = CustomView::query()->create([
            'name' => 'CF sparse',
            'entity_type' => 'opportunities',
            'visibility' => 'system',
            'user_id' => null,
            'is_default' => false,
            'columns' => ['subject', 'cf.po_reference'],
            'filters' => [],
            'sort_column' => 'created_at',
            'sort_direction' => 'desc',
            'per_page' => 20,
            'config' => [],
        ]);

        $token = lifecycleReadToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities?view_id='.$view->id)
            ->assertOk()
            ->assertJsonPath('meta.view.id', $view->id);

        $first = $response->json('opportunities.0');
        expect(array_keys($first))->toEqualCanonicalizing(['id', 'subject', 'custom_fields'])
            ->and($first['custom_fields']['po_reference'])->toBe('PO-VIEW-1');
    });
});

describe('GET /api/v1/opportunities/{id}/available_actions edge cases', function () {
    it('reports deal_price_active when a deal override blocks locking', function () {
        $opportunity = lifecycleQuotation($this->owner, $this->store);
        Auth::login($this->owner);
        (new SetDealPrice)($opportunity, SetDealPriceData::from(['deal_total' => '99.00']));
        Auth::logout();

        $token = lifecycleReadToken($this->owner);
        $actions = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}/available_actions")
            ->assertOk()
            ->json('available_actions');

        $unlock = collect($actions)->firstWhere('key', 'unlock_locks');
        expect($unlock['allowed'])->toBeFalse()
            ->and($unlock['code'])->toBe('deal_price_active');
    });

    it('reports dispatched when revert_to_quotation is blocked by dispatch history', function () {
        $product = Product::factory()->rental()->serialised()->create();
        Auth::login($this->owner);

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Dispatched actions',
            'store_id' => $this->store->id,
            'starts_at' => '2026-12-01T09:00:00Z',
            'ends_at' => '2026-12-05T17:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
        (new ConvertToQuotation)($opportunity);
        (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
        ]));
        (new ConvertToOrder)($opportunity->refresh());
        $item = $opportunity->items()->firstOrFail();
        $stock = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);
        (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $stock->id]));
        $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();
        (new DispatchAsset)($row, DispatchAssetData::from([]));
        Auth::logout();

        $token = lifecycleReadToken($this->owner);
        $actions = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}/available_actions")
            ->assertOk()
            ->json('available_actions');

        $revert = collect($actions)->firstWhere('key', 'revert_to_quotation');
        expect($revert['allowed'])->toBeFalse()
            ->and($revert['code'])->toBe('dispatched');
    });
});

describe('POST /api/v1/opportunities/{id}/convert_to_order', function () {
    it('accepts optional shortage_notes on conversion', function () {
        $opportunity = lifecycleQuotation($this->owner, $this->store);
        $token = lifecycleWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/convert_to_order", [
                'shortage_notes' => 'Client approved shortfall',
            ])
            ->assertOk()
            ->assertJsonPath('opportunity.state', OpportunityState::Order->value);
    });
});
