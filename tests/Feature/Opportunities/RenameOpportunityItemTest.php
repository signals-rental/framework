<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RenameOpportunityItem;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\RenameOpportunityItemData;
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
use Illuminate\Validation\ValidationException as DataValidationException;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

/**
 * @param  array<string, mixed>  $payload
 */
function fireRenameTestItem(Opportunity $opportunity, array $payload = []): OpportunityItem
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
            'name' => 'Original Name',
            'quantity' => '1',
            'path' => '0001',
            'transaction_type' => LineItemTransactionType::Rental->value,
        ], $payload));

        Verbs::commit();
    });

    return OpportunityItem::query()->whereKey($itemId)->firstOrFail();
}

it('renames a group row and persists the new name', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Rename Group',
        'store_id' => $this->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    $item = fireRenameTestItem($opportunity, ['item_type' => 'group', 'name' => 'Lighting']);

    $result = (new RenameOpportunityItem)($item, RenameOpportunityItemData::from(['name' => 'Sound & Lighting']));

    expect($result->name)->toBe('Sound & Lighting')
        ->and(OpportunityItem::query()->whereKey($item->id)->value('name'))->toBe('Sound & Lighting');
});

it('renames a product line and persists the new name', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Rename Product',
        'store_id' => $this->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    $item = fireRenameTestItem($opportunity, ['item_type' => 'product', 'name' => 'Speaker A']);

    $result = (new RenameOpportunityItem)($item, RenameOpportunityItemData::from(['name' => 'Speaker B']));

    expect($result->name)->toBe('Speaker B')
        ->and(OpportunityItem::query()->whereKey($item->id)->value('name'))->toBe('Speaker B');
});

it('preserves the renamed label across a Verbs replay', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Rename Replay',
        'store_id' => $this->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    $item = fireRenameTestItem($opportunity, ['item_type' => 'group', 'name' => 'Before']);

    (new RenameOpportunityItem)($item, RenameOpportunityItemData::from(['name' => 'After']));

    expect(OpportunityItem::query()->whereKey($item->id)->value('name'))->toBe('After');

    Verbs::replay();

    expect(OpportunityItem::query()->whereKey($item->id)->value('name'))->toBe('After')
        ->and(OpportunityItem::query()->whereKey($item->id)->value('name'))->not->toBe('Before');
});

it('records exactly one item_renamed audit row with old and new names', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Rename Audit',
        'store_id' => $this->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    $item = fireRenameTestItem($opportunity, ['item_type' => 'product', 'name' => 'Old Label']);

    (new RenameOpportunityItem)($item, RenameOpportunityItemData::from(['name' => 'New Label']));

    $rows = ActionLog::query()
        ->where('action', 'opportunity.item_renamed')
        ->get();

    expect($rows)->toHaveCount(1);

    /** @var array<string, mixed>|null $oldValues */
    $oldValues = $rows->first()->old_values;
    /** @var array<string, mixed>|null $newValues */
    $newValues = $rows->first()->new_values;

    expect($oldValues)->toMatchArray(['name' => 'Old Label', 'item_id' => $item->id])
        ->and($newValues)->toMatchArray(['name' => 'New Label', 'item_id' => $item->id]);
});

it('rejects an empty name via the input DTO', function () {
    expect(fn () => RenameOpportunityItemData::validate(['name' => '']))
        ->toThrow(DataValidationException::class);
});
