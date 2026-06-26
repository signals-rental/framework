<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RestructureOpportunityItems;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\RestructureOpportunityItemsData;
use App\Enums\LineItemTransactionType;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Store;
use App\Models\User;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\ItemAdded;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

/**
 * Create an event-sourced opportunity with a live Verbs state so item events can target it.
 */
function restructureTestOpportunity(Store $store): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Restructure',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

/**
 * Fire an ItemAdded event and return the allocated projection PK.
 *
 * @param  array<string, mixed>  $payload
 */
function fireRestructureTestItem(Opportunity $opportunity, array $payload = []): int
{
    $itemId = 0;

    DB::transaction(function () use ($opportunity, $payload, &$itemId): void {
        $itemId = app(SequenceAllocator::class)->next('opportunity_items');

        ItemAdded::fire(array_merge([
            'opportunity_item_id' => $itemId,
            'opportunity_id' => $opportunity->id,
            'starts_at' => '2026-07-01T09:00:00Z',
            'ends_at' => '2026-07-05T17:00:00Z',
            'item_type' => 'group',
            'name' => 'Test Item',
            'quantity' => '1',
            'path' => '0001',
            'transaction_type' => LineItemTransactionType::Rental->value,
        ], $payload));

        Verbs::commit();
    });

    return $itemId;
}

it('reorders flat items: paths recompute in new order and items return in path order', function () {
    $opportunity = restructureTestOpportunity($this->store);
    $a = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'A', 'path' => '0001']);
    $b = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'B', 'path' => '0002']);
    $c = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'C', 'path' => '0003']);

    // New order: C, A, B (all root depth 1).
    $result = (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $c, 'depth' => 1],
            ['id' => $a, 'depth' => 1],
            ['id' => $b, 'depth' => 1],
        ],
    ]));

    expect(OpportunityItem::query()->whereKey($c)->value('path'))->toBe('0001')
        ->and(OpportunityItem::query()->whereKey($a)->value('path'))->toBe('0002')
        ->and(OpportunityItem::query()->whereKey($b)->value('path'))->toBe('0003');

    // Returned in new (path) order.
    expect(collect($result)->pluck('id')->all())->toBe([$c, $a, $b]);
});

it('nests a product under a group: path becomes a child and sibling counters are correct', function () {
    $opportunity = restructureTestOpportunity($this->store);
    $group = fireRestructureTestItem($opportunity, ['item_type' => 'group', 'name' => 'Group', 'path' => '0001']);
    $prod = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'Prod', 'path' => '0002']);

    (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $group, 'depth' => 1],
            ['id' => $prod, 'depth' => 2],
        ],
    ]));

    expect(OpportunityItem::query()->whereKey($group)->value('path'))->toBe('0001')
        ->and(OpportunityItem::query()->whereKey($prod)->value('path'))->toBe('00010001');
});

it('preserves the new paths across a Verbs replay', function () {
    $opportunity = restructureTestOpportunity($this->store);
    $group = fireRestructureTestItem($opportunity, ['item_type' => 'group', 'name' => 'Group', 'path' => '0001']);
    $prod = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'Prod', 'path' => '0002']);

    (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $group, 'depth' => 1],
            ['id' => $prod, 'depth' => 2],
        ],
    ]));

    $originalProdPath = '0002';
    expect(OpportunityItem::query()->whereKey($prod)->value('path'))->toBe('00010001')
        ->and('00010001')->not->toBe($originalProdPath);

    Verbs::replay();

    // After replay the nested path must survive — proving paths flow through the event stream.
    expect(OpportunityItem::query()->whereKey($group)->value('path'))->toBe('0001')
        ->and(OpportunityItem::query()->whereKey($prod)->value('path'))->toBe('00010001')
        ->and(OpportunityItem::query()->whereKey($prod)->value('path'))->not->toBe($originalProdPath);
});

it('rejects placing an accessory at root depth, firing nothing', function () {
    $opportunity = restructureTestOpportunity($this->store);
    $acc = fireRestructureTestItem($opportunity, ['item_type' => 'accessory', 'name' => 'Acc', 'path' => '0001']);

    $before = OpportunityItem::query()->whereKey($acc)->value('path');

    expect(fn () => (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $acc, 'depth' => 1],
        ],
    ])))->toThrow(ValidationException::class);

    expect(OpportunityItem::query()->whereKey($acc)->value('path'))->toBe($before);
});

it('rejects placing an accessory under a non-product, firing nothing', function () {
    $opportunity = restructureTestOpportunity($this->store);
    $group = fireRestructureTestItem($opportunity, ['item_type' => 'group', 'name' => 'Group', 'path' => '0001']);
    $acc = fireRestructureTestItem($opportunity, ['item_type' => 'accessory', 'name' => 'Acc', 'path' => '0002']);

    $before = OpportunityItem::query()->whereIn('id', [$group, $acc])->pluck('path', 'id')->all();

    // Accessory nested under a GROUP (illegal — must be under a product).
    expect(fn () => (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $group, 'depth' => 1],
            ['id' => $acc, 'depth' => 2],
        ],
    ])))->toThrow(ValidationException::class);

    $after = OpportunityItem::query()->whereIn('id', [$group, $acc])->pluck('path', 'id')->all();
    expect($after)->toBe($before);
});

it('rejects placing a product under a product', function () {
    $opportunity = restructureTestOpportunity($this->store);
    $p1 = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'P1', 'path' => '0001']);
    $p2 = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'P2', 'path' => '0002']);

    $before = OpportunityItem::query()->whereIn('id', [$p1, $p2])->pluck('path', 'id')->all();

    expect(fn () => (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $p1, 'depth' => 1],
            ['id' => $p2, 'depth' => 2],
        ],
    ])))->toThrow(ValidationException::class);

    expect(OpportunityItem::query()->whereIn('id', [$p1, $p2])->pluck('path', 'id')->all())->toBe($before);
});

it('rejects a node id that does not belong to the opportunity', function () {
    $opportunity = restructureTestOpportunity($this->store);
    $a = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'A', 'path' => '0001']);

    $other = restructureTestOpportunity($this->store);
    $foreign = fireRestructureTestItem($other, ['item_type' => 'product', 'name' => 'Foreign', 'path' => '0001']);

    $beforeLocal = OpportunityItem::query()->whereKey($a)->value('path');

    expect(fn () => (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $a, 'depth' => 1],
            ['id' => $foreign, 'depth' => 1],
        ],
    ])))->toThrow(ValidationException::class);

    expect(OpportunityItem::query()->whereKey($a)->value('path'))->toBe($beforeLocal)
        ->and(OpportunityItem::query()->whereKey($foreign)->value('path'))->toBe('0001');
});

it('rejects an incomplete node list that omits one of the items when prune_orphans is false', function () {
    $opportunity = restructureTestOpportunity($this->store);
    $a = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'A', 'path' => '0001']);
    $b = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'B', 'path' => '0002']);

    $before = OpportunityItem::query()->whereIn('id', [$a, $b])->pluck('path', 'id')->all();

    expect(fn () => (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $a, 'depth' => 1],
        ],
    ])))->toThrow(ValidationException::class);

    expect(OpportunityItem::query()->whereIn('id', [$a, $b])->pluck('path', 'id')->all())->toBe($before);
});

it('prunes items omitted from the node list when prune_orphans is enabled', function () {
    $opportunity = restructureTestOpportunity($this->store);
    $a = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'A', 'path' => '0001']);
    $b = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'B', 'path' => '0002']);

    (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $a, 'depth' => 1],
        ],
        'prune_orphans' => true,
    ]));

    expect(OpportunityItem::query()->whereKey($a)->exists())->toBeTrue()
        ->and(OpportunityItem::query()->whereKey($b)->exists())->toBeFalse();
});

it('rejects duplicate ids in the node list', function () {
    $opportunity = restructureTestOpportunity($this->store);
    $a = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'A', 'path' => '0001']);
    $b = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'B', 'path' => '0002']);

    $before = OpportunityItem::query()->whereIn('id', [$a, $b])->pluck('path', 'id')->all();

    expect(fn () => (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $a, 'depth' => 1],
            ['id' => $a, 'depth' => 1],
        ],
    ])))->toThrow(ValidationException::class);

    expect(OpportunityItem::query()->whereIn('id', [$a, $b])->pluck('path', 'id')->all())->toBe($before);
});

it('records exactly one items_restructured audit row carrying the ordered paths', function () {
    $opportunity = restructureTestOpportunity($this->store);
    $a = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'A', 'path' => '0001']);
    $b = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'B', 'path' => '0002']);
    $c = fireRestructureTestItem($opportunity, ['item_type' => 'product', 'name' => 'C', 'path' => '0003']);

    (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $c, 'depth' => 1],
            ['id' => $a, 'depth' => 1],
            ['id' => $b, 'depth' => 1],
        ],
    ]));

    $rows = ActionLog::query()
        ->where('action', 'opportunity.items_restructured')
        ->get();

    expect($rows)->toHaveCount(1);

    /** @var array<string, mixed>|null $newValues */
    $newValues = $rows->first()->new_values;
    expect($newValues['paths'] ?? null)->toBe([
        ['id' => $c, 'path' => '0001'],
        ['id' => $a, 'path' => '0002'],
        ['id' => $b, 'path' => '0003'],
    ]);
});
