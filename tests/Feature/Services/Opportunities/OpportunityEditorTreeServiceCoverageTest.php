<?php

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Accessory;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityEditorTreeService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function editorTreeOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Editor tree',
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-04T09:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('returns null auto-group key for a non-group item', function () {
    $tree = app(OpportunityEditorTreeService::class);
    $line = new OpportunityItem(['name' => 'Line']);

    expect($tree->autoGroupKey($line))->toBeNull();
});

it('resolves null parent path for an unrecognised group key prefix', function () {
    $opportunity = editorTreeOpportunity();
    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'G']));

    $tree = app(OpportunityEditorTreeService::class);

    expect($tree->parentPathForGroupKey($opportunity->fresh()->items, 'mystery:1'))->toBeNull()
        ->and($tree->parentPathForGroupKey($opportunity->fresh()->items, ''))->toBeNull();
});

it('returns null parent path for a null group id', function () {
    $opportunity = editorTreeOpportunity();

    expect(app(OpportunityEditorTreeService::class)
        ->parentPathForGroupId($opportunity->fresh()->items, null))->toBeNull();
});

it('moves a line into another group while keeping siblings in their own groups', function () {
    $opportunity = editorTreeOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'From']));
    $from = $opportunity->refresh()->items()->where('path', '0001')->firstOrFail();
    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from(['name' => 'To']));
    $to = $opportunity->refresh()->items()->where('path', '0002')->firstOrFail();
    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from(['name' => 'Other']));
    $other = $opportunity->refresh()->items()->where('path', '0003')->firstOrFail();

    // Mover under "From" (moved to "To"); a Stayer under a THIRD group so the
    // movableLines loop populates a fresh linesByParent bucket (lines 212-215).
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Mover', 'quantity' => '1', 'unit_price' => 1000, 'parent_path' => $from->path,
    ]));
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Stayer', 'quantity' => '1', 'unit_price' => 1000, 'parent_path' => $other->path,
    ]));

    $mover = $opportunity->refresh()->items()->where('name', 'Mover')->firstOrFail();
    $stayer = $opportunity->refresh()->items()->where('name', 'Stayer')->firstOrFail();

    $tree = app(OpportunityEditorTreeService::class);
    $tree->restructure(
        $opportunity,
        $tree->nodesAfterMovingLine($opportunity->fresh()->items, $mover->id, 0, 'group:'.$to->id),
    );

    expect($mover->refresh()->parentPath())->toBe($to->path)
        ->and($stayer->refresh()->parentPath())->toBe($other->path);
});

it('restructure is a no-op for an empty node list', function () {
    $opportunity = editorTreeOpportunity();
    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Untouched']));

    $before = $opportunity->fresh()->items()->where('name', 'Untouched')->firstOrFail()->path;

    app(OpportunityEditorTreeService::class)->restructure($opportunity->fresh(), []);

    expect($opportunity->fresh()->items()->where('name', 'Untouched')->firstOrFail()->path)
        ->toBe($before);
});

it('moves a group under another via a group-parent key', function () {
    $opportunity = editorTreeOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Parent']));
    $parent = $opportunity->refresh()->items()->where('path', '0001')->firstOrFail();
    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from(['name' => 'Child']));
    $child = $opportunity->refresh()->items()->where('path', '0002')->firstOrFail();

    // Two loose lines (same null parent) so the movableLines loop hits its
    // already-seen `continue` (lines 295-296); the first carries an accessory child
    // so flattenTree's appendAccessories body executes (line 500).
    $product = Product::factory()->create(['name' => 'Speaker']);
    $included = Product::factory()->create(['name' => 'Cable']);
    Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $included->id,
        'quantity' => '1',
        'included' => true,
    ]);
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Speaker', 'itemable_id' => $product->id, 'itemable_type' => Product::class,
        'quantity' => '1', 'unit_price' => 1000,
    ]));
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Loose two', 'quantity' => '1', 'unit_price' => 1000,
    ]));

    $tree = app(OpportunityEditorTreeService::class);
    $nodes = $tree->nodesAfterMovingGroup($opportunity->fresh()->items, $child->id, 0, 'group-parent:'.$parent->id);
    $tree->restructure($opportunity->fresh(), $nodes);

    expect($child->refresh()->parentPath())->toBe($parent->path);
});

it('moving a group to root via group-parent:root keeps it at the top level', function () {
    $opportunity = editorTreeOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Parent']));
    $parent = $opportunity->refresh()->items()->where('path', '0001')->firstOrFail();
    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from([
        'name' => 'Nested', 'parent_path' => $parent->path,
    ]));
    $nested = $opportunity->refresh()->items()->where('name', 'Nested')->firstOrFail();

    $tree = app(OpportunityEditorTreeService::class);
    $nodes = $tree->nodesAfterMovingGroup($opportunity->fresh()->items, $nested->id, 0, 'group-parent:root');
    $tree->restructure($opportunity->fresh(), $nodes);

    expect($nested->refresh()->parentPath())->toBeNull();
});

it('rejects moving a non-group id as a group', function () {
    $opportunity = editorTreeOpportunity();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'PlainLine', 'quantity' => '1', 'unit_price' => 1000,
    ]));
    $line = $opportunity->refresh()->items()->where('name', 'PlainLine')->firstOrFail();

    $tree = app(OpportunityEditorTreeService::class);

    expect(fn () => $tree->nodesAfterMovingGroup($opportunity->fresh()->items, $line->id, 0, null))
        ->toThrow(ValidationException::class, 'The group could not be found.');
});

it('rejects nesting a group under its own subtree', function () {
    $opportunity = editorTreeOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Outer']));
    $outer = $opportunity->refresh()->items()->where('path', '0001')->firstOrFail();
    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from([
        'name' => 'Inner', 'parent_path' => $outer->path,
    ]));

    $tree = app(OpportunityEditorTreeService::class);

    expect(fn () => $tree->nodesAfterMovingGroup(
        $opportunity->fresh()->items,
        $outer->id,
        0,
        'group-parent:'.$opportunity->fresh()->items()->where('name', 'Inner')->firstOrFail()->id,
    ))->toThrow(ValidationException::class, 'A group cannot be nested under its own subtree.');
});

it('rejects a group-parent key pointing at a missing group', function () {
    $opportunity = editorTreeOpportunity();
    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Mover']));
    $mover = $opportunity->refresh()->items()->where('name', 'Mover')->firstOrFail();

    $tree = app(OpportunityEditorTreeService::class);

    expect(fn () => $tree->nodesAfterMovingGroup($opportunity->fresh()->items, $mover->id, 0, 'group-parent:999999'))
        ->toThrow(ValidationException::class, 'The destination parent group could not be found.');
});

it('treats an unrecognised parent-group key prefix as top level', function () {
    $opportunity = editorTreeOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Parent']));
    $parent = $opportunity->refresh()->items()->where('path', '0001')->firstOrFail();
    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from([
        'name' => 'Nested', 'parent_path' => $parent->path,
    ]));
    $nested = $opportunity->refresh()->items()->where('name', 'Nested')->firstOrFail();

    $tree = app(OpportunityEditorTreeService::class);
    // Unknown prefix -> parentPathFromParentGroupKey returns null (line 553) -> top level.
    $nodes = $tree->nodesAfterMovingGroup($opportunity->fresh()->items, $nested->id, 0, 'mystery:1');
    $tree->restructure($opportunity->fresh(), $nodes);

    expect($nested->refresh()->parentPath())->toBeNull();
});
