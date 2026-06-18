<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
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
        $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'API Items']));

        return Opportunity::query()->whereKey($created->id)->firstOrFail();
    } finally {
        Auth::logout();
    }
}

function addApiItem(User $actor, Opportunity $opportunity): OpportunityItem
{
    Auth::login($actor);

    try {
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'PA Stack', 'quantity' => '2', 'unit_price' => 5000,
        ]));

        return $opportunity->items()->latest('id')->firstOrFail();
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

        // 2 * £50.00 = £100.00 net (no tax wired).
        expect($response->json('opportunity.charge_total'))->toBe('100.00');

        $this->assertDatabaseHas('opportunity_items', [
            'opportunity_id' => $opportunity->id,
            'name' => 'Mixing Desk',
            'unit_price' => 5000,
            'total' => 10000,
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

    it('requires the opportunities:write ability', function () {
        $opportunity = makeApiOpportunity($this->owner);
        $token = itemReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/items", ['name' => 'X', 'quantity' => '1'])
            ->assertForbidden();
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

        // 4 * £50.00 = £200.00
        expect($response->json('opportunity.charge_total'))->toBe('200.00');
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
        addApiItem($this->owner, $opportunity); // computed 10000
        $token = itemWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/deal_price", ['deal_total' => '75.00'])
            ->assertOk()
            ->assertJsonPath('opportunity.charge_total', '75.00')
            ->assertJsonPath('opportunity.deal_total', '75.00');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/deal_price")
            ->assertOk()
            ->assertJsonPath('opportunity.charge_total', '100.00')
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
