<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Jobs\DeliverWebhook;
use App\Models\CustomField;
use App\Models\CustomView;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\assertSoftDeleted;

/**
 * Create an opportunity through the real event-sourcing pipeline so the row has a
 * genuine Verbs state/snapshot and can be transitioned/updated/deleted. Factory
 * rows bypass the event stream and have a synthetic state_id, so they are only
 * safe for read-only (index/show) assertions.
 *
 * The lifecycle actions authorize via Gate, so a user is logged in for the
 * duration of the action and then logged out — the subsequent HTTP request must
 * authenticate solely via its Sanctum bearer token (otherwise a lingering
 * session user would bypass the token-ability check under auth:sanctum).
 */
/**
 * @param  array<string, mixed>  $attributes
 */
function createOpportunityViaEvent(User $actor, array $attributes = []): Opportunity
{
    Auth::login($actor);

    try {
        $data = CreateOpportunityData::from(array_merge(['subject' => 'Test Opportunity'], $attributes));
        $result = (new CreateOpportunity)($data);

        return Opportunity::query()->whereKey($result->id)->firstOrFail();
    } finally {
        Auth::logout();
    }
}

/**
 * Run a lifecycle action against an opportunity as an authenticated actor, then
 * log out so the session does not leak into a tokened HTTP request.
 *
 * @param  callable(): mixed  $fn
 */
function asAuthenticated(User $actor, callable $fn): void
{
    Auth::login($actor);

    try {
        $fn();
    } finally {
        Auth::logout();
    }
}

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

function readToken(User $user): string
{
    return $user->createToken('test', ['opportunities:read'])->plainTextToken;
}

function writeToken(User $user): string
{
    return $user->createToken('test', ['opportunities:write'])->plainTextToken;
}

describe('GET /api/v1/opportunities', function () {
    it('lists opportunities with pagination meta', function () {
        Opportunity::factory()->count(3)->create();
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities')
            ->assertOk()
            ->assertJsonStructure([
                'opportunities' => [
                    '*' => ['id', 'subject', 'state', 'state_label', 'status', 'status_label', 'availability_phase', 'charge_total', 'custom_fields', 'created_at', 'updated_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 3);
    });

    it('filters by state with a Ransack _eq predicate', function () {
        Opportunity::factory()->create();
        Opportunity::factory()->quotation()->create();
        Opportunity::factory()->order()->create();
        $token = readToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities?q[state_eq]='.OpportunityState::Quotation->value)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        expect($response->json('opportunities.0.state'))->toBe(OpportunityState::Quotation->value);
    });

    it('respects per_page pagination', function () {
        Opportunity::factory()->count(5)->create();
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities?per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'opportunities');
    });

    it('eager-loads relationships via ?include=', function () {
        $member = Member::factory()->create();
        Opportunity::factory()->create(['member_id' => $member->id]);
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities?include=member')
            ->assertOk()
            ->assertJsonPath('opportunities.0.member.id', $member->id);
    });

    it('applies a saved custom view via view_id with sparse columns', function () {
        Opportunity::factory()->create(['subject' => 'View Test', 'reference' => 'PO-9']);
        $view = CustomView::query()->create([
            'name' => 'Sparse',
            'entity_type' => 'opportunities',
            'visibility' => 'system',
            'user_id' => null,
            'is_default' => false,
            'columns' => ['subject', 'reference'],
            'filters' => [],
            'sort_column' => 'created_at',
            'sort_direction' => 'desc',
            'per_page' => 20,
            'config' => [],
        ]);
        $token = readToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities?view_id='.$view->id)
            ->assertOk()
            ->assertJsonPath('meta.view.id', $view->id);

        $first = $response->json('opportunities.0');
        expect(array_keys($first))->toEqualCanonicalizing(['id', 'subject', 'reference']);
    });

    it('requires the opportunities:read ability', function () {
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities')
            ->assertForbidden();
    });

    it('rejects an unauthenticated request', function () {
        $this->getJson('/api/v1/opportunities')->assertUnauthorized();
    });
});

describe('GET /api/v1/opportunities/{id}?include=items.assets', function () {
    it('returns the nested items + assets structure with money as decimal strings', function () {
        $opportunity = Opportunity::factory()->create();
        $item = OpportunityItem::factory()->for($opportunity)->create([
            'name' => 'PA Stack',
            'unit_price' => 7500,
            'total' => 15000,
            'quantity' => 2,
        ]);
        $stockLevel = StockLevel::factory()->serialised()->create(['item_name' => 'Speaker #1']);
        OpportunityItemAsset::factory()
            ->for($item, 'item')
            ->create(['stock_level_id' => $stockLevel->id]);

        $token = readToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}?include=items.assets")
            ->assertOk()
            ->assertJsonPath('opportunity.items.0.name', 'PA Stack')
            ->assertJsonPath('opportunity.items.0.unit_price', '75.00')
            ->assertJsonPath('opportunity.items.0.total', '150.00')
            ->assertJsonPath('opportunity.items.0.quantity', '2.00')
            ->assertJsonPath('opportunity.items.0.charge_period_label', 'Day')
            ->assertJsonPath('opportunity.items.0.transaction_type_label', 'Rental')
            ->assertJsonPath('opportunity.items.0.assets.0.stock_level_id', $stockLevel->id)
            ->assertJsonPath('opportunity.items.0.assets.0.status_label', 'Allocated');

        expect($response->json('opportunity.items.0.assets'))->toHaveCount(1);
    });

    it('omits items when not requested via include', function () {
        $opportunity = Opportunity::factory()->create();
        OpportunityItem::factory()->for($opportunity)->create();
        $token = readToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}")
            ->assertOk();

        expect($response->json('opportunity'))->not->toHaveKey('items');
    });
});

describe('GET /api/v1/opportunities/{id}', function () {
    it('shows a single opportunity', function () {
        $opportunity = Opportunity::factory()->create(['subject' => 'Single']);
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}")
            ->assertOk()
            ->assertJsonPath('opportunity.id', $opportunity->id)
            ->assertJsonPath('opportunity.subject', 'Single');
    });

    it('404s for a soft-deleted opportunity', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        $opportunity->delete();
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}")
            ->assertNotFound();
    });

    it('populates custom_fields on the show endpoint (default eager-load)', function () {
        CustomField::factory()->create([
            'name' => 'po_reference',
            'module_type' => 'Opportunity',
        ]);

        $opportunity = Opportunity::factory()->create(['subject' => 'With CF']);
        $opportunity->syncCustomFields(['po_reference' => 'PO-123']);

        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}")
            ->assertOk()
            ->assertJsonPath('opportunity.custom_fields.po_reference', 'PO-123');
    });

    it('populates custom_fields on the index endpoint without an explicit include', function () {
        CustomField::factory()->create([
            'name' => 'po_reference',
            'module_type' => 'Opportunity',
        ]);

        $opportunity = Opportunity::factory()->create();
        $opportunity->syncCustomFields(['po_reference' => 'PO-456']);

        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/opportunities')
            ->assertOk()
            ->assertJsonPath('opportunities.0.custom_fields.po_reference', 'PO-456');
    });
});

describe('POST /api/v1/opportunities', function () {
    it('creates an opportunity via the event and projects the row plus an audit log', function () {
        $token = writeToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/opportunities', [
                'subject' => 'Glastonbury Main Stage',
                'reference' => 'PO-555',
            ])
            ->assertCreated()
            ->assertJsonPath('opportunity.subject', 'Glastonbury Main Stage')
            ->assertJsonPath('opportunity.state', OpportunityState::Draft->value)
            ->assertJsonPath('opportunity.status_label', 'Open');

        $id = $response->json('opportunity.id');

        $this->assertDatabaseHas('opportunities', [
            'id' => $id,
            'subject' => 'Glastonbury Main Stage',
            'state' => OpportunityState::Draft->value,
        ]);

        $this->assertDatabaseHas('action_logs', [
            'action' => 'opportunity.created',
            'auditable_type' => Opportunity::class,
            'auditable_id' => $id,
        ]);
    });

    it('validates a missing subject', function () {
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/opportunities', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject']);
    });

    it('requires the opportunities:write ability', function () {
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/opportunities', ['subject' => 'Nope'])
            ->assertForbidden();
    });
});

describe('PUT /api/v1/opportunities/{id}', function () {
    it('updates header fields via the event', function () {
        $opportunity = createOpportunityViaEvent($this->owner, ['subject' => 'Before']);
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['subject' => 'After'])
            ->assertOk()
            ->assertJsonPath('opportunity.subject', 'After');

        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'subject' => 'After']);
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = Opportunity::factory()->create();
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['subject' => 'X'])
            ->assertForbidden();
    });
});

describe('transition endpoints', function () {
    it('converts a draft to a quotation', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/convert_to_quotation")
            ->assertOk()
            ->assertJsonPath('opportunity.state', OpportunityState::Quotation->value);

        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'state' => OpportunityState::Quotation->value,
        ]);
    });

    it('converts a quotation to an order', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        asAuthenticated($this->owner, function () use ($opportunity) {
            (new ConvertToQuotation)($opportunity);
            // An order must carry at least one line item to be confirmed
            // (opportunity-lifecycle.md §12.1 convert guard).
            (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
                'name' => 'Line', 'quantity' => '1', 'unit_price' => 5000,
            ]));
        });
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/convert_to_order")
            ->assertOk()
            ->assertJsonPath('opportunity.state', OpportunityState::Order->value);
    });

    it('changes status within the current state', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        asAuthenticated($this->owner, fn () => (new ConvertToQuotation)($opportunity));
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/change_status", [
                'status' => OpportunityStatus::QuotationReserved->statusValue(),
            ])
            ->assertOk()
            ->assertJsonPath('opportunity.status', OpportunityStatus::QuotationReserved->statusValue());
    });

    it('rejects an invalid status for the current state with 422', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        $token = writeToken($this->owner);

        // Draft only has status 0; status 5 is an Order status.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/change_status", ['status' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    });

    it('rejects an invalid transition with 422', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        asAuthenticated($this->owner, function () use ($opportunity) {
            (new ConvertToQuotation)($opportunity);
            // An order must carry at least one line item to be confirmed
            // (opportunity-lifecycle.md §12.1 convert guard).
            (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
                'name' => 'Line', 'quantity' => '1', 'unit_price' => 5000,
            ]));
            (new ConvertToOrder)($opportunity->refresh());
        });
        $token = writeToken($this->owner);

        // Already an Order — converting to quotation is invalid.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/convert_to_quotation")
            ->assertStatus(422);
    });

    it('requires the opportunities:write ability for transitions', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/convert_to_quotation")
            ->assertForbidden();
    });
});

describe('DELETE /api/v1/opportunities/{id}', function () {
    it('soft-deletes the opportunity via the event', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}")
            ->assertNoContent();

        assertSoftDeleted('opportunities', ['id' => $opportunity->id]);

        $this->assertDatabaseHas('action_logs', [
            'action' => 'opportunity.deleted',
            'auditable_type' => Opportunity::class,
            'auditable_id' => $opportunity->id,
        ]);
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = Opportunity::factory()->create();
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}")
            ->assertForbidden();
    });
});

describe('POST /api/v1/opportunities/{id}/restore', function () {
    it('un-trashes a soft-deleted opportunity via the event', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        asAuthenticated($this->owner, fn () => $opportunity->delete());
        assertSoftDeleted('opportunities', ['id' => $opportunity->id]);

        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/restore")
            ->assertOk()
            ->assertJsonPath('opportunity.id', $opportunity->id);

        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'deleted_at' => null]);

        $this->assertDatabaseHas('action_logs', [
            'action' => 'opportunity.restored',
            'auditable_type' => Opportunity::class,
            'auditable_id' => $opportunity->id,
        ]);
    });

    it('dispatches the opportunity.restored webhook', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        asAuthenticated($this->owner, fn () => $opportunity->delete());

        Webhook::factory()->create([
            'user_id' => $this->owner->id,
            'events' => ['*'],
            'is_active' => true,
        ]);

        Queue::fake();

        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/restore")
            ->assertOk();

        // Restore is a lifecycle transition, so it ships the lean id + action
        // envelope (not the full opportunity DTO) per the webhook bridge.
        Queue::assertPushed(
            DeliverWebhook::class,
            fn (DeliverWebhook $job): bool => $job->event === 'opportunity.restored'
                && ($job->payload['id'] ?? null) === $opportunity->id
                && ($job->payload['action'] ?? null) === 'opportunity.restored',
        );
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = createOpportunityViaEvent($this->owner);
        asAuthenticated($this->owner, fn () => $opportunity->delete());

        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/restore")
            ->assertForbidden();
    });
});

describe('Asset allocation endpoints', function () {
    /**
     * Build an event-sourced Order with one serialised line + a free serialised
     * asset, returning [$opportunity, $item, $asset].
     *
     * @return array{0: Opportunity, 1: OpportunityItem, 2: StockLevel}
     */
    function orderWithSerialisedLine(User $actor): array
    {
        $store = Store::factory()->create();
        $product = Product::factory()->rental()->serialised()->create();

        $opportunity = createOpportunityViaEvent($actor, [
            'store_id' => $store->id,
            'starts_at' => '2026-10-01T09:00:00Z',
            'ends_at' => '2026-10-05T17:00:00Z',
        ]);

        asAuthenticated($actor, function () use ($opportunity, $product): void {
            (new ConvertToQuotation)($opportunity);
            // The line item must exist before conversion — an order must carry at
            // least one item to be confirmed (opportunity-lifecycle.md §12.1).
            (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
                'name' => $product->name,
                'itemable_id' => $product->id,
                'itemable_type' => Product::class,
                'quantity' => '2',
                'transaction_type' => LineItemTransactionType::Rental->value,
            ]));
            (new ConvertToOrder)($opportunity->refresh());
        });

        $item = $opportunity->items()->firstOrFail();
        $asset = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
        ]);

        return [$opportunity, $item, $asset];
    }

    it('allocates an asset to a line item', function () {
        [$opportunity, $item, $asset] = orderWithSerialisedLine($this->owner);
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/assets", [
                'stock_level_id' => $asset->id,
            ])
            ->assertCreated()
            ->assertJsonPath('asset.stock_level_id', $asset->id)
            ->assertJsonPath('asset.status', 0);

        expect(OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->count())->toBe(1);
    });

    it('prepares then deallocates an asset', function () {
        [$opportunity, $item, $asset] = orderWithSerialisedLine($this->owner);
        $token = writeToken($this->owner);
        $base = "/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/assets";

        $assetId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson($base, ['stock_level_id' => $asset->id])
            ->json('asset.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("{$base}/{$assetId}", ['action' => 'prepare'])
            ->assertOk()
            ->assertJsonPath('asset.status', 1);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("{$base}/{$assetId}", ['reason' => 'no longer needed'])
            ->assertNoContent();

        expect(OpportunityItemAsset::query()->whereKey($assetId)->exists())->toBeFalse();
    });

    it('rejects an unknown asset action with 422', function () {
        [$opportunity, $item, $asset] = orderWithSerialisedLine($this->owner);
        $token = writeToken($this->owner);
        $base = "/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/assets";

        $assetId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson($base, ['stock_level_id' => $asset->id])
            ->json('asset.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("{$base}/{$assetId}", ['action' => 'teleport'])
            ->assertStatus(422);
    });

    it('batch-allocates via quick_allocate', function () {
        [$opportunity, $item, $asset] = orderWithSerialisedLine($this->owner);
        $second = StockLevel::factory()->serialised()->create([
            'product_id' => $asset->product_id,
            'store_id' => $asset->store_id,
        ]);
        $token = writeToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/quick_allocate", [
                'allocations' => [
                    ['opportunity_item_id' => $item->id, 'stock_level_id' => $asset->id],
                    ['opportunity_item_id' => $item->id, 'stock_level_id' => $second->id],
                ],
            ])
            ->assertOk();

        expect(OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->count())->toBe(2);
    });

    it('requires the opportunities:write ability to allocate', function () {
        [$opportunity, $item, $asset] = orderWithSerialisedLine($this->owner);
        $token = readToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/items/{$item->id}/assets", [
                'stock_level_id' => $asset->id,
            ])
            ->assertForbidden();
    });
});
