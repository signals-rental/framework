<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RestructureOpportunityItems;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityItemData;
use App\Data\Opportunities\RestructureOpportunityItemsData;
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

    return OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->latest('id')
        ->firstOrFail();
}

/**
 * @param  list<int>  $orderedIds
 * @return list<array{id: int, depth: int}>
 */
function reorderFlatNodes(array $orderedIds): array
{
    return array_map(
        static fn (int $id): array => ['id' => $id, 'depth' => 1],
        $orderedIds,
    );
}

it('persists the new path order across the reordered items', function () {
    $opportunity = reorderOpportunity();
    $a = reorderItem($opportunity, 'A');
    $b = reorderItem($opportunity, 'B');
    $c = reorderItem($opportunity, 'C');

    expect($a->refresh()->path)->toBe('0001')
        ->and($b->refresh()->path)->toBe('0002')
        ->and($c->refresh()->path)->toBe('0003');

    $result = (new RestructureOpportunityItems)(
        $opportunity->refresh(),
        RestructureOpportunityItemsData::from(['nodes' => reorderFlatNodes([$c->id, $a->id, $b->id])]),
    );

    expect($c->refresh()->path)->toBe('0001')
        ->and($a->refresh()->path)->toBe('0002')
        ->and($b->refresh()->path)->toBe('0003');

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

    (new RestructureOpportunityItems)(
        $opportunity->refresh(),
        RestructureOpportunityItemsData::from(['nodes' => reorderFlatNodes([$c->id, $a->id, $b->id])]),
    );

    expect($c->refresh()->path)->toBe('0001')
        ->and($a->refresh()->path)->toBe('0002')
        ->and($b->refresh()->path)->toBe('0003');

    Verbs::replay();

    expect($c->refresh()->path)->toBe('0001')
        ->and($a->refresh()->path)->toBe('0002')
        ->and($b->refresh()->path)->toBe('0003');
});

it('rejects an item id that does not belong to the opportunity', function () {
    $opportunity = reorderOpportunity();
    $a = reorderItem($opportunity, 'A');
    $b = reorderItem($opportunity, 'B');

    $other = reorderOpportunity();
    $foreign = reorderItem($other, 'Foreign');

    expect(fn () => (new RestructureOpportunityItems)(
        $opportunity->refresh(),
        RestructureOpportunityItemsData::from(['nodes' => reorderFlatNodes([$a->id, $foreign->id, $b->id])]),
    ))->toThrow(ValidationException::class);

    expect(fn () => (new RestructureOpportunityItems)(
        $opportunity->refresh(),
        RestructureOpportunityItemsData::from(['nodes' => reorderFlatNodes([$a->id, 999999, $b->id])]),
    ))->toThrow(ValidationException::class);

    expect($a->refresh()->path)->toBe('0001')
        ->and($b->refresh()->path)->toBe('0002');
});

it('forbids reordering without the opportunities.edit permission', function () {
    $opportunity = reorderOpportunity();
    $a = reorderItem($opportunity, 'A');
    $b = reorderItem($opportunity, 'B');

    $this->actingAs(User::factory()->create());

    expect(fn () => (new RestructureOpportunityItems)(
        $opportunity->refresh(),
        RestructureOpportunityItemsData::from(['nodes' => reorderFlatNodes([$b->id, $a->id])]),
    ))->toThrow(AuthorizationException::class);
});

it('records a single opportunity.items_restructured audit row carrying the ordered paths', function () {
    $opportunity = reorderOpportunity();
    $a = reorderItem($opportunity, 'A');
    $b = reorderItem($opportunity, 'B');
    $c = reorderItem($opportunity, 'C');

    (new RestructureOpportunityItems)(
        $opportunity->refresh(),
        RestructureOpportunityItemsData::from(['nodes' => reorderFlatNodes([$c->id, $a->id, $b->id])]),
    );

    $rows = ActionLog::query()
        ->where('auditable_type', Opportunity::class)
        ->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.items_restructured')
        ->get();

    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    /** @var array<string, mixed>|null $newValues */
    $newValues = $row->new_values;

    expect($row->verb_event_id)->not->toBeNull()
        ->and($row->user_id)->toBe($this->actor->id)
        ->and($newValues['paths'] ?? null)->toBe([
            ['id' => $c->id, 'path' => '0001'],
            ['id' => $a->id, 'path' => '0002'],
            ['id' => $b->id, 'path' => '0003'],
        ]);
});

it('does not duplicate the reorder audit row on replay', function () {
    $opportunity = reorderOpportunity();
    $a = reorderItem($opportunity, 'A');
    $b = reorderItem($opportunity, 'B');

    (new RestructureOpportunityItems)(
        $opportunity->refresh(),
        RestructureOpportunityItemsData::from(['nodes' => reorderFlatNodes([$b->id, $a->id])]),
    );

    $countBefore = ActionLog::query()->where('auditable_id', $opportunity->id)->count();

    Verbs::replay();

    expect(ActionLog::query()->where('auditable_id', $opportunity->id)->count())->toBe($countBefore);
});
