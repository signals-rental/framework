<?php

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Actions\Opportunities\RenameOpportunityItem;
use App\Actions\Opportunities\RestructureOpportunityItems;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\RenameOpportunityItemData;
use App\Data\Opportunities\RestructureOpportunityItemsData;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityEditorTreeService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| Unified group rows (replaces M8-3 opportunity_sections backend tests)
|--------------------------------------------------------------------------
|
| Groupings are structural Group rows in opportunity_items with materialised
| `path` nesting. The retired OpportunitySectionTest covered plain section CRUD,
| section_id replay decoupling, AssignItemToSection, opportunity_section.* audit
| actions, and opportunity.item_section_assigned — all obsolete under the unified
| model. Equivalent coverage lives here and in RestructureOpportunityItemsTest,
| RemoveOpportunityItemTest, and RenameOpportunityItemTest.
|
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

function groupTestOpportunity(Store $store): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Grouped',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('creates a group row against an opportunity', function () {
    $opportunity = groupTestOpportunity($this->store);

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Audio']));

    $group = $opportunity->refresh()->items()->where('item_type', OpportunityItemType::Group)->firstOrFail();

    expect($group->name)->toBe('Audio')
        ->and($group->path)->toBe('0001')
        ->and($group->parentPath())->toBeNull();
});

it('rejects a group with a blank or missing name', function () {
    expect(fn () => AddOpportunityGroupData::validateAndCreate(['name' => '']))
        ->toThrow(ValidationException::class);

    expect(fn () => AddOpportunityGroupData::validateAndCreate(['parent_path' => null]))
        ->toThrow(ValidationException::class);
});

it('renames a group row via RenameOpportunityItem', function () {
    $opportunity = groupTestOpportunity($this->store);

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Old']));
    $group = $opportunity->refresh()->items()->where('item_type', OpportunityItemType::Group)->firstOrFail();

    $data = (new RenameOpportunityItem)($group, RenameOpportunityItemData::from(['name' => 'New']));

    expect($data->name)->toBe('New');
    expect($group->refresh()->name)->toBe('New');
});

it('removes an empty group row without affecting sibling lines', function () {
    $opportunity = groupTestOpportunity($this->store);

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Empty']));
    $group = $opportunity->refresh()->items()->where('item_type', OpportunityItemType::Group)->firstOrFail();

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Sibling',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    (new RemoveOpportunityItem)($group->refresh());

    expect(OpportunityItem::query()->whereKey($group->id)->exists())->toBeFalse()
        ->and(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(1);
});

it('reorders group rows via RestructureOpportunityItems', function () {
    $opportunity = groupTestOpportunity($this->store);

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'A']));
    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from(['name' => 'B']));
    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from(['name' => 'C']));

    $groups = $opportunity->refresh()->items()
        ->where('item_type', OpportunityItemType::Group)
        ->orderBy('path')
        ->get();

    [$a, $b, $c] = [$groups[0], $groups[1], $groups[2]];

    (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $c->id, 'depth' => 1],
            ['id' => $a->id, 'depth' => 1],
            ['id' => $b->id, 'depth' => 1],
        ],
    ]));

    expect(OpportunityItem::query()->whereKey($c->id)->value('path'))->toBe('0001')
        ->and(OpportunityItem::query()->whereKey($a->id)->value('path'))->toBe('0002')
        ->and(OpportunityItem::query()->whereKey($b->id)->value('path'))->toBe('0003');
});

it('nests a group under another via RestructureOpportunityItems', function () {
    $opportunity = groupTestOpportunity($this->store);

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Parent']));
    $parent = $opportunity->refresh()->items()->where('item_type', OpportunityItemType::Group)->firstOrFail();

    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from(['name' => 'Child']));
    $child = $opportunity->refresh()->items()->where('path', '0002')->firstOrFail();

    (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $parent->id, 'depth' => 1],
            ['id' => $child->id, 'depth' => 2],
        ],
    ]));

    expect($child->refresh()->path)->toBe('00010001')
        ->and($child->parentPath())->toBe($parent->path);
});

it('promotes a nested group to the top level via RestructureOpportunityItems', function () {
    $opportunity = groupTestOpportunity($this->store);

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Parent']));
    $parent = $opportunity->refresh()->items()->where('path', '0001')->firstOrFail();

    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from([
        'name' => 'Child',
        'parent_path' => $parent->path,
    ]));
    $child = $opportunity->refresh()->items()->where('path', '00010001')->firstOrFail();

    (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $parent->id, 'depth' => 1],
            ['id' => $child->id, 'depth' => 1],
        ],
    ]));

    expect($child->refresh()->parentPath())->toBeNull()
        ->and($child->path)->toBe('0002');
});

it('refuses nesting a new group beyond the depth limit', function () {
    $opportunity = groupTestOpportunity($this->store);
    $parentPath = null;

    for ($level = 1; $level <= OpportunityEditorTreeService::MAX_GROUP_DEPTH; $level++) {
        (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from([
            'name' => "L{$level}",
            'parent_path' => $parentPath,
        ]));
        $parentPath = $opportunity->refresh()->items()
            ->where('item_type', OpportunityItemType::Group)
            ->where('name', "L{$level}")
            ->value('path');
    }

    expect(strlen((string) $parentPath))->toBe(OpportunityEditorTreeService::MAX_GROUP_DEPTH * 4);

    expect(fn () => (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from([
        'name' => 'Too deep',
        'parent_path' => $parentPath,
    ])))->toThrow(ValidationException::class);
});

it('assigns a line under a group via parent_path on AddOpportunityItem', function () {
    $opportunity = groupTestOpportunity($this->store);

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Rig']));
    $group = $opportunity->refresh()->items()->where('item_type', OpportunityItemType::Group)->firstOrFail();

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Truss',
        'quantity' => '1',
        'unit_price' => 1000,
        'parent_path' => $group->path,
    ]));

    $line = $opportunity->refresh()->items()->where('path', '00010001')->firstOrFail();

    expect($line->parentPath())->toBe($group->path);
});

it('moves a line between groups via the editor tree service restructure', function () {
    $opportunity = groupTestOpportunity($this->store);

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'From']));
    $from = $opportunity->refresh()->items()->where('path', '0001')->firstOrFail();

    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from(['name' => 'To']));
    $to = $opportunity->refresh()->items()->where('path', '0002')->firstOrFail();

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 1000,
        'parent_path' => $from->path,
    ]));
    $line = $opportunity->refresh()->items()->where('path', '00010001')->firstOrFail();

    $tree = app(OpportunityEditorTreeService::class);
    $items = $opportunity->refresh()->items;
    $tree->restructure(
        $opportunity,
        $tree->nodesAfterMovingLine($items, $line->id, 0, 'group:'.$to->id),
    );

    expect($line->refresh()->parentPath())->toBe($to->path)
        ->and($line->path)->toBe('00020001');
});

it('denies creating a group without permission', function () {
    $opportunity = groupTestOpportunity($this->store);

    $this->actingAs(User::factory()->create());

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Nope']));
})->throws(AuthorizationException::class);

it('denies restructuring without permission', function () {
    $opportunity = groupTestOpportunity($this->store);

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Only']));

    $group = $opportunity->refresh()->items()->where('item_type', OpportunityItemType::Group)->firstOrFail();

    $this->actingAs(User::factory()->create());

    (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [['id' => $group->id, 'depth' => 1]],
    ]));
})->throws(AuthorizationException::class);
