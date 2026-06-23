<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Store;
use App\Models\User;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\ItemAdded;
use App\Verbs\Events\Opportunities\ItemsRestructured;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

/**
 * Create an event-sourced opportunity with a Verbs state_id so item events can target it.
 */
function restructureOpportunity(Store $store): Opportunity
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
 * Fire an ItemAdded event and return the allocated item id.
 * Mirrors the pattern from ItemAddedUnifiedTest.
 *
 * @param  array<string, mixed>  $payload
 */
function fireRestructureItem(Opportunity $opportunity, array $payload = []): int
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

it('updates opportunity_items.path and state when ItemsRestructured is fired', function () {
    $opportunity = restructureOpportunity($this->store);
    $itemId = fireRestructureItem($opportunity, ['path' => '0001']);

    // Confirm the initial path was set by ItemAdded.
    $item = OpportunityItem::query()->whereKey($itemId)->firstOrFail();
    expect($item->path)->toBe('0001');

    // Fire ItemsRestructured using the Verbs state_id (snowflake), not the small projection PK.
    $stateId = $item->state_id;

    DB::transaction(function () use ($stateId): void {
        ItemsRestructured::fire(
            opportunity_item_id: $stateId,
            path: '0002',
            emit_audit: false,
        );
        Verbs::commit();
    });

    $item->refresh();
    expect($item->path)->toBe('0002');
});

it('writes one audit row when emit_audit is true and none when false', function () {
    $opportunity = restructureOpportunity($this->store);
    $itemId = fireRestructureItem($opportunity, ['path' => '0001']);

    $item = OpportunityItem::query()->whereKey($itemId)->firstOrFail();
    $stateId = $item->state_id;

    // emit_audit: false → no audit log.
    DB::transaction(function () use ($stateId): void {
        ItemsRestructured::fire(
            opportunity_item_id: $stateId,
            path: '0002',
            emit_audit: false,
        );
        Verbs::commit();
    });

    expect(
        ActionLog::query()
            ->where('action', 'opportunity.items_restructured')
            ->count()
    )->toBe(0);

    // emit_audit: true → exactly one audit row with the supplied ordered_paths.
    $orderedPaths = [
        ['id' => $itemId, 'path' => '0003'],
    ];

    DB::transaction(function () use ($stateId, $orderedPaths): void {
        ItemsRestructured::fire(
            opportunity_item_id: $stateId,
            path: '0003',
            emit_audit: true,
            ordered_paths: $orderedPaths,
        );
        Verbs::commit();
    });

    $rows = ActionLog::query()
        ->where('action', 'opportunity.items_restructured')
        ->get();

    expect($rows)->toHaveCount(1);

    /** @var array<string, mixed>|null $newValues */
    $newValues = $rows->first()->new_values;
    expect($newValues['paths'] ?? null)->toBe($orderedPaths);
});

it('preserves the new path across a Verbs replay (not reverted to ItemAdded value)', function () {
    $opportunity = restructureOpportunity($this->store);
    $itemId = fireRestructureItem($opportunity, ['path' => '0001']);

    $stateId = OpportunityItem::query()->whereKey($itemId)->value('state_id');

    // Restructure to a new path.
    DB::transaction(function () use ($stateId): void {
        ItemsRestructured::fire(
            opportunity_item_id: $stateId,
            path: '0099',
            emit_audit: false,
        );
        Verbs::commit();
    });

    expect(OpportunityItem::query()->whereKey($itemId)->value('path'))->toBe('0099');

    // After replay the path must remain '0099', not revert to the ItemAdded value '0001'.
    Verbs::replay();

    expect(OpportunityItem::query()->whereKey($itemId)->value('path'))->toBe('0099');
});

it('sets paths on two items in one commit and writes exactly one audit row', function () {
    $opportunity = restructureOpportunity($this->store);
    $itemIdA = fireRestructureItem($opportunity, ['path' => '0001', 'name' => 'Item A']);
    $itemIdB = fireRestructureItem($opportunity, ['path' => '0002', 'name' => 'Item B']);

    $stateIdA = OpportunityItem::query()->whereKey($itemIdA)->value('state_id');
    $stateIdB = OpportunityItem::query()->whereKey($itemIdB)->value('state_id');

    $orderedPaths = [
        ['id' => $itemIdA, 'path' => '0010'],
        ['id' => $itemIdB, 'path' => '0020'],
    ];

    // Fire both events in one transaction; only the anchor (emit_audit:true) writes the audit.
    DB::transaction(function () use ($stateIdA, $stateIdB, $orderedPaths): void {
        ItemsRestructured::fire(
            opportunity_item_id: $stateIdA,
            path: '0010',
            emit_audit: false,
        );
        ItemsRestructured::fire(
            opportunity_item_id: $stateIdB,
            path: '0020',
            emit_audit: true,
            ordered_paths: $orderedPaths,
        );
        Verbs::commit();
    });

    expect(OpportunityItem::query()->whereKey($itemIdA)->value('path'))->toBe('0010');
    expect(OpportunityItem::query()->whereKey($itemIdB)->value('path'))->toBe('0020');

    expect(
        ActionLog::query()
            ->where('action', 'opportunity.items_restructured')
            ->count()
    )->toBe(1);
});
