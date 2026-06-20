<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\ReorderOpportunityItems;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityItemData;
use App\Data\Opportunities\ReorderOpportunityItemsData;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

/**
 * Create an event-sourced opportunity carrying a Verbs state_id so the item
 * events can target it.
 */
function reorderOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Reorder']));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

/**
 * Add an ad-hoc manual-priced line and return the fresh projection row.
 */
function reorderItem(Opportunity $opportunity, string $name): OpportunityItem
{
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $name,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    // Query the model directly: the `items()` relation default-orders by
    // `sort_order`, which would defeat `latest('id')` once the lines carry distinct
    // sort orders (the just-added line is always the highest id).
    return OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->latest('id')
        ->firstOrFail();
}

it('persists the new sort_order across the reordered items', function () {
    $opportunity = reorderOpportunity();
    $a = reorderItem($opportunity, 'A');
    $b = reorderItem($opportunity, 'B');
    $c = reorderItem($opportunity, 'C');

    // Added in order A,B,C -> sort_order 0,1,2.
    expect($a->refresh()->sort_order)->toBe(0)
        ->and($b->refresh()->sort_order)->toBe(1)
        ->and($c->refresh()->sort_order)->toBe(2);

    // Reorder to C,A,B.
    $result = (new ReorderOpportunityItems)(
        $opportunity->refresh(),
        ReorderOpportunityItemsData::from(['item_ids' => [$c->id, $a->id, $b->id]]),
    );

    expect($c->refresh()->sort_order)->toBe(0)
        ->and($a->refresh()->sort_order)->toBe(1)
        ->and($b->refresh()->sort_order)->toBe(2);

    // The action returns the items in the new order as OpportunityItemData.
    expect($result)->toHaveCount(3)
        ->and($result[0])->toBeInstanceOf(OpportunityItemData::class)
        ->and(array_map(fn (OpportunityItemData $d): int => $d->id, $result))
        ->toBe([$c->id, $a->id, $b->id]);
});

it('preserves the new order across a Verbs replay (not reset to ItemAdded)', function () {
    $opportunity = reorderOpportunity();
    $a = reorderItem($opportunity, 'A');
    $b = reorderItem($opportunity, 'B');
    $c = reorderItem($opportunity, 'C');

    // Reorder to C,A,B — sort_order is event-sourced, so this must survive replay.
    (new ReorderOpportunityItems)(
        $opportunity->refresh(),
        ReorderOpportunityItemsData::from(['item_ids' => [$c->id, $a->id, $b->id]]),
    );

    expect($c->refresh()->sort_order)->toBe(0)
        ->and($a->refresh()->sort_order)->toBe(1)
        ->and($b->refresh()->sort_order)->toBe(2);

    Verbs::replay();

    // The crux: replay re-applies ItemAdded (original 0,1,2) THEN
    // ItemSortOrderChanged, so the projection reflects the reordered values, NOT
    // ItemAdded's originals. A plain update() would have been reverted here.
    expect($c->refresh()->sort_order)->toBe(0)
        ->and($a->refresh()->sort_order)->toBe(1)
        ->and($b->refresh()->sort_order)->toBe(2);
});

it('rejects an item id that does not belong to the opportunity', function () {
    $opportunity = reorderOpportunity();
    $a = reorderItem($opportunity, 'A');
    $b = reorderItem($opportunity, 'B');

    // A line on a different opportunity (foreign id).
    $other = reorderOpportunity();
    $foreign = reorderItem($other, 'Foreign');

    expect(fn () => (new ReorderOpportunityItems)(
        $opportunity->refresh(),
        ReorderOpportunityItemsData::from(['item_ids' => [$a->id, $foreign->id, $b->id]]),
    ))->toThrow(ValidationException::class);

    // An unknown (non-existent) id is likewise rejected, and nothing is mutated.
    expect(fn () => (new ReorderOpportunityItems)(
        $opportunity->refresh(),
        ReorderOpportunityItemsData::from(['item_ids' => [$a->id, 999999, $b->id]]),
    ))->toThrow(ValidationException::class);

    // Original order untouched after the rejected calls.
    expect($a->refresh()->sort_order)->toBe(0)
        ->and($b->refresh()->sort_order)->toBe(1);
});

it('forbids reordering without the opportunities.edit permission', function () {
    $opportunity = reorderOpportunity();
    $a = reorderItem($opportunity, 'A');
    $b = reorderItem($opportunity, 'B');

    // A fresh user with no roles/permissions cannot pass the action gate.
    $this->actingAs(User::factory()->create());

    expect(fn () => (new ReorderOpportunityItems)(
        $opportunity->refresh(),
        ReorderOpportunityItemsData::from(['item_ids' => [$b->id, $a->id]]),
    ))->toThrow(AuthorizationException::class);
});

it('records a single opportunity.items_reordered audit row carrying the ordered ids', function () {
    $opportunity = reorderOpportunity();
    $a = reorderItem($opportunity, 'A');
    $b = reorderItem($opportunity, 'B');
    $c = reorderItem($opportunity, 'C');

    (new ReorderOpportunityItems)(
        $opportunity->refresh(),
        ReorderOpportunityItemsData::from(['item_ids' => [$c->id, $a->id, $b->id]]),
    );

    $rows = ActionLog::query()
        ->where('auditable_type', Opportunity::class)
        ->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.items_reordered')
        ->get();

    // Exactly ONE audit row for the whole reorder (the anchor event), not one per item.
    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    /** @var array<string, mixed>|null $newValues */
    $newValues = $row->new_values;

    expect($row->verb_event_id)->not->toBeNull()
        ->and($row->user_id)->toBe($this->actor->id)
        ->and($newValues['item_ids'] ?? null)->toBe([$c->id, $a->id, $b->id]);
});

it('does not duplicate the reorder audit row on replay', function () {
    $opportunity = reorderOpportunity();
    $a = reorderItem($opportunity, 'A');
    $b = reorderItem($opportunity, 'B');

    (new ReorderOpportunityItems)(
        $opportunity->refresh(),
        ReorderOpportunityItemsData::from(['item_ids' => [$b->id, $a->id]]),
    );

    $countBefore = ActionLog::query()->where('auditable_id', $opportunity->id)->count();

    Verbs::replay();

    expect(ActionLog::query()->where('auditable_id', $opportunity->id)->count())->toBe($countBefore);
});
