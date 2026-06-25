<?php

use App\Actions\Opportunities\AddOpportunityAccessory;
use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityAccessoryData;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->actingAs($this->owner);
});

function persistTreeOpportunity(): Opportunity
{
    Auth::login(test()->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Persist tree repro',
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

/**
 * @return array{group: OpportunityItem, first: OpportunityItem, second: OpportunityItem}
 */
function seedGroupWithTwoItems(Opportunity $opportunity): array
{
    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Audio']));
    $group = $opportunity->fresh(['items'])->items
        ->first(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group);

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Mic A',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Mic B',
        'quantity' => '1',
        'unit_price' => 500,
    ]));

    $items = $opportunity->fresh(['items'])->items
        ->filter(fn (OpportunityItem $item): bool => $item->item_type !== OpportunityItemType::Group)
        ->sortBy('path')
        ->values();

    return [
        'group' => $group,
        'first' => $items->first(),
        'second' => $items->last(),
    ];
}

it('persistTree via Volt reorders root items and updates DB paths', function () {
    $opportunity = persistTreeOpportunity();
    $seed = seedGroupWithTwoItems($opportunity);

    $beforePaths = [
        $seed['first']->id => $seed['first']->path,
        $seed['second']->id => $seed['second']->path,
    ];

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);
    $revision = $editor->treeRevision();

    $nodes = [
        ['id' => $seed['second']->id, 'depth' => 1],
        ['id' => $seed['group']->id, 'depth' => 1],
        ['id' => $seed['first']->id, 'depth' => 1],
    ];

    $persistResult = $editor->persistTree($nodes, $revision);

    expect($persistResult['stale'])->toBeFalse();

    $afterFirst = OpportunityItem::query()->findOrFail($seed['first']->id)->path;
    $afterSecond = OpportunityItem::query()->findOrFail($seed['second']->id)->path;

    expect($afterFirst)->not->toBe($beforePaths[$seed['first']->id])
        ->and($afterSecond)->not->toBe($beforePaths[$seed['second']->id])
        ->and($afterSecond)->toBe('0001')
        ->and($afterFirst)->toBe('0003');

    $serverIds = collect($editor->serverTree()['tree'])->pluck('id')->all();

    expect($serverIds)->toBe([
        $seed['second']->id,
        $seed['group']->id,
        $seed['first']->id,
    ]);
});

it('persistTree via Volt nests a product under a group', function () {
    $opportunity = persistTreeOpportunity();
    $seed = seedGroupWithTwoItems($opportunity);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);
    $revision = $editor->treeRevision();

    $nodes = [
        ['id' => $seed['group']->id, 'depth' => 1],
        ['id' => $seed['first']->id, 'depth' => 2],
        ['id' => $seed['second']->id, 'depth' => 1],
    ];

    $persistResult = $editor->persistTree($nodes, $revision);

    expect($persistResult['stale'])->toBeFalse();

    $nestedPath = OpportunityItem::query()->findOrFail($seed['first']->id)->path;

    expect($nestedPath)->toBe($seed['group']->path.'0001');

    $tree = $editor->serverTree()['tree'];
    $nestedRow = collect($tree)->firstWhere('id', $seed['first']->id);

    expect($nestedRow)->not->toBeNull()
        ->and($nestedRow['depth'])->toBe(2)
        ->and($nestedRow['parent_group_id'])->toBe($seed['group']->id);
});

it('persistTree via Volt includes collapsed accessories in the full node set', function () {
    $opportunity = persistTreeOpportunity();
    $product = Product::factory()->rental()->bulk()->create();
    $accessoryProduct = Product::factory()->rental()->bulk()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => 'product',
        'quantity' => '1',
        'unit_price' => 5000,
    ]));

    $principal = $opportunity->fresh(['items'])->items->firstWhere('item_type', OpportunityItemType::Product);

    (new AddOpportunityAccessory)($opportunity->fresh(), AddOpportunityAccessoryData::from([
        'name' => $accessoryProduct->name,
        'principal_item_id' => $principal->id,
        'itemable_id' => $accessoryProduct->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ]));

    $accessory = $opportunity->fresh(['items'])->items->firstWhere('item_type', OpportunityItemType::Accessory);

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Second line',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $second = $opportunity->fresh(['items'])->items
        ->filter(fn (OpportunityItem $item): bool => $item->item_type !== OpportunityItemType::Accessory
            && $item->id !== $principal->id)
        ->first();

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);
    $revision = $editor->treeRevision();

    $nodes = [
        ['id' => $second->id, 'depth' => 1],
        ['id' => $principal->id, 'depth' => 1],
        ['id' => $accessory->id, 'depth' => 2],
    ];

    $persistResult = $editor->persistTree($nodes, $revision);

    expect($persistResult['stale'])->toBeFalse();

    expect(OpportunityItem::query()->findOrFail($second->id)->path)->toBe('0001');

    $serverIds = collect($editor->serverTree()['tree'])->pluck('id')->all();

    expect($serverIds)->toBe([$second->id, $principal->id, $accessory->id]);
});

it('createSection persists immediately dispatches tree sync and closes the modal', function () {
    $opportunity = persistTreeOpportunity();

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Lighting')
        ->set('newSectionParent', '')
        ->call('createSection')
        ->assertHasNoErrors()
        ->assertDispatched('line-items-mutation-done', modal: 'create-section')
        ->assertDispatched('toast', type: 'success', message: 'Section created');

    $group = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Group)
        ->where('name', 'Lighting')
        ->first();

    expect($group)->not->toBeNull();

    $tree = lineItemsEditorInstance(
        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])])
    )->serverTree()['tree'];

    expect(collect($tree)->pluck('name'))->toContain('Lighting');
});

it('keeps persistTree reorder after a mutation refresh and a fresh editor mount', function () {
    $opportunity = persistTreeOpportunity();
    $seed = seedGroupWithTwoItems($opportunity);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);
    $revision = $editor->treeRevision();

    $nodes = [
        ['id' => $seed['second']->id, 'depth' => 1],
        ['id' => $seed['group']->id, 'depth' => 1],
        ['id' => $seed['first']->id, 'depth' => 1],
    ];

    $persistResult = $editor->persistTree($nodes, $revision);

    expect($persistResult['stale'])->toBeFalse();

    $component->call('renameSection', $seed['group']->id, 'Renamed Audio');

    $afterFirst = OpportunityItem::query()->findOrFail($seed['first']->id)->path;
    $afterSecond = OpportunityItem::query()->findOrFail($seed['second']->id)->path;

    expect($afterSecond)->toBe('0001')
        ->and($afterFirst)->toBe('0003');

    $freshEditor = lineItemsEditorInstance(
        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])])
    );

    $serverIds = collect($freshEditor->serverTree()['tree'])->pluck('id')->all();

    expect($serverIds)->toBe([
        $seed['second']->id,
        $seed['group']->id,
        $seed['first']->id,
    ]);
});

it('deleteSection promotes nested products to root instead of removing them', function () {
    $opportunity = persistTreeOpportunity();
    $seed = seedGroupWithTwoItems($opportunity);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);
    $revision = $editor->treeRevision();

    $persistResult = $editor->persistTree([
        ['id' => $seed['group']->id, 'depth' => 1],
        ['id' => $seed['first']->id, 'depth' => 2],
        ['id' => $seed['second']->id, 'depth' => 1],
    ], $revision);

    expect($persistResult['stale'])->toBeFalse()
        ->and($seed['first']->fresh()->path)->toBe($seed['group']->path.'0001');

    $component->call('deleteSection', $seed['group']->id);

    expect(OpportunityItem::query()->find($seed['group']->id))->toBeNull()
        ->and($seed['first']->fresh()->parentPath())->toBeNull()
        ->and($seed['second']->fresh()->parentPath())->toBeNull();

    $serverIds = collect($editor->serverTree()['tree'])->pluck('id')->all();

    expect($serverIds)->toContain($seed['first']->id, $seed['second']->id);
    expect($serverIds)->not->toContain($seed['group']->id);
});
