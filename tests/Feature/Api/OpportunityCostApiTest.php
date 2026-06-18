<?php

use App\Actions\Opportunities\AddOpportunityCost;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityCostData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityCostType;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
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

function costReadToken(User $user): string
{
    return $user->createToken('test', ['opportunities:read'])->plainTextToken;
}

function costWriteToken(User $user): string
{
    return $user->createToken('test', ['opportunities:write'])->plainTextToken;
}

function makeCostApiOpportunity(User $actor): Opportunity
{
    Auth::login($actor);

    try {
        $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'API Costs']));

        return Opportunity::query()->whereKey($created->id)->firstOrFail();
    } finally {
        Auth::logout();
    }
}

function addApiCost(User $actor, Opportunity $opportunity): OpportunityCost
{
    Auth::login($actor);

    try {
        (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
            'description' => 'Crew', 'amount' => 5000, 'quantity' => '2',
        ]));

        return $opportunity->costs()->latest('id')->firstOrFail();
    } finally {
        Auth::logout();
    }
}

describe('POST /api/v1/opportunities/{id}/costs', function () {
    it('adds a cost and returns the opportunity with refreshed totals', function () {
        $opportunity = makeCostApiOpportunity($this->owner);
        $token = costWriteToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/costs", [
                'description' => 'Van Hire',
                'cost_type' => OpportunityCostType::Delivery->value,
                'amount' => '30.00',
                'quantity' => '1',
            ])
            ->assertCreated()
            ->assertJsonPath('opportunity.id', $opportunity->id);

        // £30.00 delivery → charge_total £30.00, routed into the transit bucket.
        expect($response->json('opportunity.charge_total'))->toBe('30.00')
            ->and($response->json('opportunity.transit_charge_total'))->toBe('30.00');

        $this->assertDatabaseHas('opportunity_costs', [
            'opportunity_id' => $opportunity->id,
            'description' => 'Van Hire',
            'amount' => 3000,
        ]);
    });

    it('validates a missing description', function () {
        $opportunity = makeCostApiOpportunity($this->owner);
        $token = costWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/costs", ['amount' => '10.00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    });

    it('validates an invalid cost type', function () {
        $opportunity = makeCostApiOpportunity($this->owner);
        $token = costWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/costs", [
                'description' => 'X', 'cost_type' => 99,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cost_type']);
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = makeCostApiOpportunity($this->owner);
        $token = costReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/costs", ['description' => 'X'])
            ->assertForbidden();
    });
});

describe('PATCH /api/v1/opportunities/{id}/costs/{cost}', function () {
    it('updates the cost and re-rolls the totals', function () {
        $opportunity = makeCostApiOpportunity($this->owner);
        $cost = addApiCost($this->owner, $opportunity);
        $token = costWriteToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunity->id}/costs/{$cost->id}", [
                'amount' => '80.00', 'quantity' => '3',
            ])
            ->assertOk()
            ->assertJsonPath('opportunity.id', $opportunity->id);

        // 3 * £80.00 = £240.00
        expect($response->json('opportunity.charge_total'))->toBe('240.00');
    });

    it('404s when the cost does not belong to the opportunity', function () {
        $opportunityA = makeCostApiOpportunity($this->owner);
        $opportunityB = makeCostApiOpportunity($this->owner);
        $cost = addApiCost($this->owner, $opportunityA);
        $token = costWriteToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunityB->id}/costs/{$cost->id}", ['amount' => '10.00'])
            ->assertNotFound();
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = makeCostApiOpportunity($this->owner);
        $cost = addApiCost($this->owner, $opportunity);
        $token = costReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/opportunities/{$opportunity->id}/costs/{$cost->id}", ['amount' => '10.00'])
            ->assertForbidden();
    });
});

describe('DELETE /api/v1/opportunities/{id}/costs/{cost}', function () {
    it('removes the cost and returns the opportunity with zeroed totals', function () {
        $opportunity = makeCostApiOpportunity($this->owner);
        $cost = addApiCost($this->owner, $opportunity);
        $token = costWriteToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/costs/{$cost->id}")
            ->assertOk();

        expect($response->json('opportunity.charge_total'))->toBe('0.00');
        $this->assertDatabaseMissing('opportunity_costs', ['id' => $cost->id]);
    });

    it('requires the opportunities:write ability', function () {
        $opportunity = makeCostApiOpportunity($this->owner);
        $cost = addApiCost($this->owner, $opportunity);
        $token = costReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/opportunities/{$opportunity->id}/costs/{$cost->id}")
            ->assertForbidden();
    });
});

describe('GET /api/v1/opportunities/{id}?include=costs', function () {
    it('exposes costs as a lazy include', function () {
        $opportunity = makeCostApiOpportunity($this->owner);
        $cost = addApiCost($this->owner, $opportunity);
        $token = costReadToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}?include=costs")
            ->assertOk()
            ->assertJsonPath('opportunity.costs.0.id', $cost->id)
            ->assertJsonPath('opportunity.costs.0.amount', '50.00')
            ->assertJsonPath('opportunity.costs.0.cost_type_label', 'Miscellaneous');
    });
});
