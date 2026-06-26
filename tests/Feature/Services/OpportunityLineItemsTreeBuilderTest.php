<?php

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityEditorTreeService;
use App\Services\Opportunities\OpportunityLineItemsTreeBuilder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function treeBuilderOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Tree builder',
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-04T09:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('builds a pre-order tree with nested groups, text items, and child flags', function () {
    $opportunity = treeBuilderOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Audio']));
    $audio = $opportunity->refresh()->items()->where('item_type', OpportunityItemType::Group)->firstOrFail();

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Mixer',
        'quantity' => '1',
        'unit_price' => 5000,
        'parent_path' => $audio->path,
    ]));
    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from([
        'name' => 'Nested',
        'parent_path' => $audio->path,
    ]));
    $nested = $opportunity->refresh()->items()->where('name', 'Nested')->firstOrFail();

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Notes line',
        'item_type' => OpportunityItemType::Text->value,
        'quantity' => '1',
        'unit_price' => 1500,
        'parent_path' => $nested->path,
    ]));

    $tree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh(['items']));
    $paths = collect($tree)->pluck('path')->all();

    expect($paths)->toBe(['0001', '00010001', '00010002', '000100020001'])
        ->and(collect($tree)->firstWhere('name', 'Audio')['has_children'])->toBeTrue()
        ->and(collect($tree)->firstWhere('name', 'Nested')['has_children'])->toBeTrue()
        ->and(collect($tree)->firstWhere('name', 'Mixer')['parent_group_id'])->toBe($audio->id)
        ->and(collect($tree)->firstWhere('name', 'Notes line')['type_label'])->toBe('Free text item')
        ->and(collect($tree)->firstWhere('name', 'Notes line')['charge_total'])->toBe(4500);
});

it('omits the days multiplier from the charge breakdown for Sale lines', function () {
    $opportunity = treeBuilderOpportunity(); // 3-day window

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Rental kit',
        'quantity' => '2',
        'unit_price' => 5000,
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Cable for sale',
        'quantity' => '2',
        'unit_price' => 5000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));

    $tree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh(['items']));

    $rental = collect($tree)->firstWhere('name', 'Rental kit');
    $sale = collect($tree)->firstWhere('name', 'Cable for sale');

    // Rental: 2 × 50.00 × 3 days = 300.00. Sale: 2 × 50.00 × 1 = 100.00.
    expect($rental['charge_breakdown']['rental_charge_display'])->toContain('300.00')
        ->and($rental['charge_breakdown']['days_line'])->toContain('× 3')
        ->and($sale['charge_breakdown']['rental_charge_display'])->toContain('100.00')
        ->and($sale['charge_breakdown']['days_line'])->toContain('× 1');
});

it('lists quick-add destinations and parent group options from the display tree', function () {
    $opportunity = treeBuilderOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Rig']));
    $rig = $opportunity->refresh()->items()->where('name', 'Rig')->firstOrFail();

    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from([
        'name' => 'Child rig',
        'parent_path' => $rig->path,
    ]));

    $builder = app(OpportunityLineItemsTreeBuilder::class);
    $fresh = $opportunity->fresh(['items']);

    $destinations = $builder->destinations($fresh);
    $parents = $builder->parentGroupOptions($fresh);

    expect(collect($destinations)->pluck('label')->all())->toContain('— Auto group —', 'Section · Rig', 'Section · Child rig')
        ->and(collect($parents)->pluck('label')->all())->toContain('— Top level —', 'Rig', '— Child rig');
});

it('builds display groups with subtotals and an ungrouped fallback bucket', function () {
    $opportunity = treeBuilderOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Packed']));
    $group = $opportunity->refresh()->items()->where('name', 'Packed')->firstOrFail();

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Inside',
        'quantity' => '1',
        'unit_price' => 2000,
        'parent_path' => $group->path,
    ]));
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Loose',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $groups = app(OpportunityEditorTreeService::class)->buildDisplayGroups(
        $opportunity->fresh()->items,
        fn ($item): array => ['id' => $item->id, 'total' => (int) $item->total],
    );

    $packed = collect($groups)->firstWhere('label', 'Packed');
    $ungrouped = collect($groups)->firstWhere('label', 'Ungrouped');

    expect($packed['subtotal'])->toBe(6000)
        ->and($packed['lines'])->toHaveCount(1)
        ->and($ungrouped['subtotal'])->toBe(3000)
        ->and($ungrouped['lines'])->toHaveCount(1);
});

it('resolves auto-group keys and parent paths for editor destination keys', function () {
    $opportunity = treeBuilderOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from([
        'name' => 'Auto',
        'custom_fields' => [OpportunityEditorTreeService::AUTO_GROUP_KEY_FIELD => 'auto:rig'],
    ]));
    $auto = $opportunity->refresh()->items()->where('name', 'Auto')->firstOrFail();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Manual']));
    $manual = $opportunity->refresh()->items()->where('name', 'Manual')->firstOrFail();

    $tree = app(OpportunityEditorTreeService::class);
    $items = $opportunity->fresh()->items;

    expect($tree->autoGroupKey($auto))->toBe('auto:rig')
        ->and($tree->autoGroupKey($manual))->toBeNull()
        ->and($tree->parentPathForGroupKey($items, 'group:'.$manual->id))->toBe($manual->path)
        ->and($tree->parentPathForGroupKey($items, 'auto:rig'))->toBe($auto->path);
});

it('rejects nesting beyond the configured maximum depth', function () {
    $tree = app(OpportunityEditorTreeService::class);
    $tooDeepParent = str_repeat('0', OpportunityEditorTreeService::MAX_GROUP_DEPTH * 4);

    expect(fn () => $tree->assertCanNestUnder($tooDeepParent))
        ->toThrow(ValidationException::class);
});

it('orders parent group options in pre-order depth', function () {
    $opportunity = treeBuilderOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Root']));
    $root = $opportunity->refresh()->items()->where('name', 'Root')->firstOrFail();
    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from([
        'name' => 'Child',
        'parent_path' => $root->path,
    ]));

    $options = app(OpportunityEditorTreeService::class)
        ->parentGroupOptions($opportunity->fresh()->items);

    expect(collect($options)->pluck('label')->all())->toBe([
        '— Top level —',
        'Root',
        '— Child',
    ]);
});
