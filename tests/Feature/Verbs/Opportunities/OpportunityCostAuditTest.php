<?php

use App\Actions\Opportunities\AddOpportunityCost;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RemoveOpportunityCost;
use App\Actions\Opportunities\UpdateOpportunityCost;
use App\Data\Opportunities\AddOpportunityCostData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\UpdateOpportunityCostData;
use App\Enums\OpportunityCostType;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

function auditCostOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Audited costs']));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('writes an audit row against the opportunity for each cost mutation', function () {
    $opportunity = auditCostOpportunity();

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Crew',
        'cost_type' => OpportunityCostType::Labour->value,
        'amount' => 5000,
        'quantity' => '2',
    ]));
    $cost = $opportunity->costs()->firstOrFail();

    (new UpdateOpportunityCost)($cost->refresh(), UpdateOpportunityCostData::from(['amount' => 6000]));
    (new RemoveOpportunityCost)($cost->refresh());

    $actions = ActionLog::query()
        ->where('auditable_type', Opportunity::class)
        ->where('auditable_id', $opportunity->id)
        ->pluck('action')
        ->all();

    expect($actions)->toContain('opportunity.cost_added')
        ->toContain('opportunity.cost_updated')
        ->toContain('opportunity.cost_removed');
});

it('records the cost_added audit row with actor, auditable, and verb event id', function () {
    $opportunity = auditCostOpportunity();

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Insurance',
        'cost_type' => OpportunityCostType::Insurance->value,
        'amount' => 10000,
        'quantity' => '1',
    ]));

    $added = ActionLog::query()
        ->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.cost_added')
        ->sole();

    expect($added->auditable_type)->toBe(Opportunity::class)
        ->and($added->user_id)->toBe($this->actor->id)
        ->and($added->verb_event_id)->not->toBeNull()
        ->and($added->new_values)->not->toBeNull()
        ->and($added->old_values)->toBeNull();
});

it('records the cost_removed audit row with the removed cost snapshot', function () {
    $opportunity = auditCostOpportunity();

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Van',
        'cost_type' => OpportunityCostType::Delivery->value,
        'amount' => 3000,
        'quantity' => '1',
    ]));
    $cost = $opportunity->costs()->firstOrFail();

    (new RemoveOpportunityCost)($cost->refresh());

    $removed = ActionLog::query()
        ->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.cost_removed')
        ->sole();

    /** @var array<string, mixed>|null $oldValues */
    $oldValues = $removed->old_values;

    expect($removed->auditable_type)->toBe(Opportunity::class)
        ->and($removed->user_id)->toBe($this->actor->id)
        ->and($removed->new_values)->toBeNull()
        ->and($oldValues)->not->toBeNull()
        ->and($oldValues['description'] ?? null)->toBe('Van');
});

it('does not duplicate cost audit rows on replay', function () {
    $opportunity = auditCostOpportunity();

    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from([
        'description' => 'Surcharge',
        'cost_type' => OpportunityCostType::Surcharge->value,
        'amount' => 2500,
        'quantity' => '1',
    ]));
    $cost = $opportunity->costs()->firstOrFail();
    (new UpdateOpportunityCost)($cost->refresh(), UpdateOpportunityCostData::from(['amount' => 4000]));

    $countBefore = ActionLog::query()->where('auditable_id', $opportunity->id)->count();

    Verbs::replay();

    expect(ActionLog::query()->where('auditable_id', $opportunity->id)->count())->toBe($countBefore);
});
