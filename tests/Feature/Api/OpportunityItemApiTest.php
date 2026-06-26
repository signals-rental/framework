<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

function itemReadToken(User $user): string
{
    return $user->createToken('test', ['opportunities:read'])->plainTextToken;
}

function itemWriteToken(User $user): string
{
    return $user->createToken('test', ['opportunities:write'])->plainTextToken;
}

/**
 * Build a real event-sourced opportunity (logged in for the Gate, logged out so a
 * lingering session does not bypass the token-ability check).
 */
function makeApiOpportunity(User $actor): Opportunity
{
    Auth::login($actor);

    try {
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'API Items',
            'starts_at' => '2026-07-01T09:00:00Z',
            'ends_at' => '2026-07-05T17:00:00Z',
        ]));

        return Opportunity::query()->whereKey($created->id)->firstOrFail();
    } finally {
        Auth::logout();
    }
}

function addApiItem(User $actor, Opportunity $opportunity): OpportunityItem
{
    Auth::login($actor);

    try {
        $beforeIds = $opportunity->items()->pluck('id')->all();

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'PA Stack', 'quantity' => '2', 'unit_price' => 5000,
        ]));

        return $opportunity->fresh()->items
            ->first(fn (OpportunityItem $item): bool => ! in_array($item->id, $beforeIds, true))
            ?? $opportunity->items()->latest('id')->firstOrFail();
    } finally {
        Auth::logout();
    }
}

describe('POST /api/v1/opportunities/{id}/items', function () {
    it('adds a line item and returns the opportunity with refreshed totals', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $token = itemWriteToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/items", [
                'name' => 'Mixing Desk',
                'quantity' => '2',
                'unit_price' => '50.00',
            ])
            ->assertCreated()
            ->assertJsonPath('opportunity.id', $opportunity->id);

        // 2 * £50.00 * 4 chargeable days = £400.00 net (no tax wired).
        expect($response->json('opportunity.charge_total'))->toBe('400.00');

        $this->assertDatabaseHas('opportunity_items', [
            'opportunity_id' => $opportunity->id,
            'name' => 'Mixing Desk',
            'unit_price' => 5000,
            'total' => 40000,
        ]);
    });

    it('validates a missing name', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $token = itemWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/items", ['quantity' => '1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('rejects a sub-rental transaction type until Phase 4', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $token = itemWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/items", [
                'name' => 'Sub-hired rig',
                'quantity' => '1',
                'transaction_type' => LineItemTransactionType::SubRental->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['transaction_type'])
            ->assertJsonPath('errors.transaction_type.0', 'Sub-rental line items are not available until Phase 4.');

        // No line item was created — the guard rejected before firing the event.
        expect($opportunity->items()->count())->toBe(0);
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $token = itemReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/items", ['name' => 'X', 'quantity' => '1'])
            ->assertForbidden();
    });

    it('creates a group row when item_type is group', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $token = itemWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/items", [
                'item_type' => 'group',
                'name' => 'Front of House',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('opportunity_items', [
            'opportunity_id' => $opportunity->id,
            'name' => 'Front of House',
            'item_type' => OpportunityItemType::Group->value,
            'itemable_id' => null,
        ]);
    });

    it('creates an accessory under a product via principal_item_id', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $product = Product::factory()->create();

        Auth::login($this->owner);
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Principal',
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'unit_price' => 1000,
        ]));
        $principal = $opportunity->items()->firstOrFail();
        Auth::logout();

        $token = itemWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/items", [
                'item_type' => 'accessory',
                'name' => 'Mic Clip',
                'principal_item_id' => $principal->id,
                'quantity' => '2',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('opportunity_items', [
            'opportunity_id' => $opportunity->id,
            'name' => 'Mic Clip',
            'item_type' => OpportunityItemType::Accessory->value,
        ]);
    });
});

describe('PATCH /api/v1/opportunities/{id}/items/{item}', function () {
    it('changes the quantity and re-rolls the totals', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $item = addApiItem($this->owner, $opportunity);
        $token = itemWriteToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunity->id}/items/{$item->id}", ['quantity' => '4'])
            ->assertOk()
            ->assertJsonPath('opportunity.id', $opportunity->id);

        // 4 * £50.00 * 4 chargeable days = £800.00
        expect($response->json('opportunity.charge_total'))->toBe('800.00');
    });

    it('404s when the item does not belong to the opportunity', function () {
        $opportunityA = makeApiOpportunity($this->owner);
        $opportunityB = makeApiOpportunity($this->owner);
        $item = addApiItem($this->owner, $opportunityA);
        $token = itemWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunityB->id}/items/{$item->id}", ['quantity' => '3'])
            ->assertNotFound();
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $item = addApiItem($this->owner, $opportunity);
        $token = itemReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunity->id}/items/{$item->id}", ['quantity' => '3'])
            ->assertForbidden();
    });

    it('rolls back an earlier mutation when a later field in the same PATCH fails', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $item = addApiItem($this->owner, $opportunity);
        $token = itemWriteToken($this->owner);

        $originalQuantity = $item->fresh()->quantity;

        // quantity (valid) is applied first, then discount_percent (>100) fails
        // validation. The whole PATCH must roll back atomically.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunity->id}/items/{$item->id}", [
                'quantity' => '9',
                'discount_percent' => '200',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount_percent');

        // The quantity change must NOT have persisted.
        expect($item->fresh()->quantity)->toBe($originalQuantity);
    });

    it('renames a line item when only name is supplied', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $item = addApiItem($this->owner, $opportunity);
        $token = itemWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunity->id}/items/{$item->id}", [
                'name' => 'Renamed Stack',
            ])
            ->assertOk();

        expect($item->fresh()->name)->toBe('Renamed Stack');
    });
});

describe('PATCH /api/v1/opportunities/{id}/items/tree', function () {
    it('reorders flat items via the tree endpoint', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $a = addApiItem($this->owner, $opportunity);
        $b = addApiItem($this->owner, $opportunity);
        $c = addApiItem($this->owner, $opportunity);
        $token = itemWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunity->id}/items/tree", [
                'nodes' => [
                    ['id' => $c->id, 'depth' => 1],
                    ['id' => $a->id, 'depth' => 1],
                    ['id' => $b->id, 'depth' => 1],
                ],
            ])
            ->assertOk();

        expect($c->fresh()->path)->toBe('0001')
            ->and($a->fresh()->path)->toBe('0002')
            ->and($b->fresh()->path)->toBe('0003');
    });

    it('returns 422 when an illegal tree placement is submitted', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $product = Product::factory()->create();
        $token = itemWriteToken($this->owner);

        Auth::login($this->owner);
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Principal',
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'unit_price' => 1000,
        ]));
        $principal = $opportunity->items()->firstOrFail();
        Auth::logout();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/items", [
                'item_type' => 'accessory',
                'name' => 'Mic Clip',
                'principal_item_id' => $principal->id,
                'quantity' => '1',
            ])
            ->assertCreated();

        $accessory = $opportunity->items()->where('item_type', OpportunityItemType::Accessory)->firstOrFail();
        $beforePaths = [
            $principal->id => $principal->fresh()->path,
            $accessory->id => $accessory->fresh()->path,
        ];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunity->id}/items/tree", [
                'nodes' => [
                    ['id' => $accessory->id, 'depth' => 1],
                    ['id' => $principal->id, 'depth' => 1],
                ],
            ])
            ->assertStatus(422);

        expect($principal->fresh()->path)->toBe($beforePaths[$principal->id])
            ->and($accessory->fresh()->path)->toBe($beforePaths[$accessory->id]);
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $item = addApiItem($this->owner, $opportunity);
        $token = itemReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunity->id}/items/tree", [
                'nodes' => [['id' => $item->id, 'depth' => 1]],
            ])
            ->assertForbidden();
    });
});

describe('DELETE /api/v1/opportunities/{id}/items/{item}', function () {
    it('removes the item and returns the opportunity with zeroed totals', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $item = addApiItem($this->owner, $opportunity);
        $token = itemWriteToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/items/{$item->id}")
            ->assertOk();

        expect($response->json('opportunity.charge_total'))->toBe('0.00');
        $this->assertDatabaseMissing('opportunity_items', ['id' => $item->id]);
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $item = addApiItem($this->owner, $opportunity);
        $token = itemReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/items/{$item->id}")
            ->assertForbidden();
    });
});

describe('deal price endpoints', function () {
    it('sets and clears a manual deal-total override', function () {
        $opportunity = makeApiOpportunity($this->owner);
        addApiItem($this->owner, $opportunity); // computed 40000 (2 x 5000 x 4 days)
        $token = itemWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/deal_price", ['deal_total' => '75.00'])
            ->assertOk()
            ->assertJsonPath('opportunity.charge_total', '75.00')
            ->assertJsonPath('opportunity.deal_total', '75.00');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/deal_price")
            ->assertOk()
            ->assertJsonPath('opportunity.charge_total', '400.00')
            ->assertJsonPath('opportunity.deal_total', null);
    });

    it('validates the deal_total', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $token = itemWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/deal_price", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['deal_total']);
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $token = itemReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/deal_price", ['deal_total' => '10.00'])
            ->assertForbidden();
    });
});
