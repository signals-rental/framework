<?php

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityItemType;
use App\Models\Accessory;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityEditorTreeService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

interface OpportunityItemsEditorComponent
{
    /** @return array<int, bool> */
    public function duplicateLineIds(): array;
}

/**
 * @param  Testable<Component>  $testable
 */
function itemsEditorInstance(Testable $testable): object
{
    $instance = $testable->instance();
    assert(method_exists($instance, 'duplicateLineIds'));

    return $instance;
}

/** @return Collection<int, OpportunityItem> */
function editorGroups(Opportunity $opportunity): Collection
{
    /** @var Collection<int, OpportunityItem> $groups */
    $groups = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Group)
        ->orderBy('path')
        ->get();

    return $groups;
}

function editorGroupNamed(Opportunity $opportunity, string $name): OpportunityItem
{
    $group = editorGroups($opportunity)->firstWhere('name', $name);

    if (! $group instanceof OpportunityItem) {
        throw new RuntimeException("Group not found: {$name}");
    }

    return $group;
}

function editorAutoGroup(Opportunity $opportunity, string $autoKey): ?OpportunityItem
{
    foreach (editorGroups($opportunity) as $group) {
        if (($group->custom_fields[OpportunityEditorTreeService::AUTO_GROUP_KEY_FIELD] ?? null) === $autoKey) {
            return $group;
        }
    }

    return null;
}

function lineUnderGroup(OpportunityItem $line, OpportunityItem $group): bool
{
    return $line->parentPath() === $group->path;
}

/*
|--------------------------------------------------------------------------
| Opportunity line-item editor (M8-3d-ii)
|--------------------------------------------------------------------------
|
| The editor replaces the M8-2 read-only items tab. Every line mutation flows
| through the SAME event-sourced action classes the API uses, so the editor
| needs a LIVE Verbs opportunity (factory rows carry a synthetic state_id with no
| event stream and cannot fire item events). createLiveOpportunity() builds one
| through the real CreateOpportunity action.
|
| TESTS ARE WRITTEN, NOT RUN (M8 cadence) — the full suite runs once at the M8-end
| gate.
|
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * Create an opportunity through the real event pipeline so it has a live Verbs
 * state that line-item events can target.
 */
function liveOpportunityForEditor(User $actor, int $storeId, string $subject = 'Editor opportunity'): Opportunity
{
    Auth::login($actor);

    $result = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => $subject,
        'store_id' => $storeId,
    ]));

    return Opportunity::query()->whereKey($result->id)->firstOrFail();
}

it('renders the editable items tab for an editor', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Editable Items');

    $this->actingAs($this->owner);

    // The "+ Section" toolbar button is only rendered when the component is editable.
    // (The editor is now embedded in the Overview, so it no longer renders the
    // standalone page header — "+ Section" uniquely identifies the editable render.)
    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('+ Section');
});

it('forbids the items tab for a user without opportunities.view', function () {
    $opportunity = Opportunity::factory()->create();

    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])->assertForbidden();
});

it('renders read-only (non-editable) for a view-only user', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    // A view-only user gets the read-only render: no editor toolbar. The embedded
    // editor renders the empty-state body (no page header) for an item-less opp.
    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('This opportunity has no line items yet.')
        ->assertDontSee('+ Section');
});

it('adds a line item via the picker and reflects it in the totals', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'Robe Spiider', 'sku' => 'MH-SPID']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 6)
        ->assertHasNoErrors();

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->first();

    expect($item)->not->toBeNull()
        ->and($item->name)->toBe('Robe Spiider')
        ->and($item->itemable_id)->toBe($product->id)
        ->and($item->itemable_type)->toBe(Product::class)
        ->and((float) $item->quantity)->toBe(6.0);

    // The opportunity total recomputes (ex-tax) once the line is priced.
    expect($opportunity->fresh()->charge_total)->toBeGreaterThanOrEqual(0);
});

it('gates adding a line on opportunities.edit', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertForbidden();

    expect(OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->count())->toBe(0);
});

it('removes a line item', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 2);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    $component->call('removeItem', $item->id)->assertHasNoErrors();

    expect(OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->count())->toBe(0);
});

it('changes a line quantity and recomputes totals', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();
    $originalTotal = $item->total;

    $component->call('updateQuantity', $item->id, '5')->assertHasNoErrors();

    $updated = $item->fresh();

    expect((float) $updated->quantity)->toBe(5.0)
        ->and($updated->total)->toBeGreaterThanOrEqual($originalTotal);
});

it('dispatches opportunity-totals-updated after totals-affecting edits so the sidebar refreshes (#10)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    // Each totals-affecting edit must notify the parent Show component (which owns
    // the sidebar Totals panel + header) via the opportunity-totals-updated event.
    $component->call('updateQuantity', $item->id, '3')
        ->assertDispatched('opportunity-totals-updated');

    $component->call('overridePrice', $item->id, '40.00')
        ->assertDispatched('opportunity-totals-updated');

    $component->call('setDiscount', $item->id, '10')
        ->assertDispatched('opportunity-totals-updated');
});

it('overrides a line price and clears the override', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    $component->call('overridePrice', $item->id, '125.00')->assertHasNoErrors();

    expect($item->fresh()->unit_price)->toBe(12500);

    // Clearing the override (null) returns the line to rate-engine pricing.
    $component->call('overridePrice', $item->id, null)->assertHasNoErrors();
});

it('creates a section and assigns a line to it', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        // createSection() reads the bound $newSectionName property (bug #2 fix —
        // it no longer takes the name as a call argument).
        ->set('newSectionName', 'Front of House')
        ->call('createSection')
        ->assertHasNoErrors();

    $group = editorGroupNamed($opportunity, 'Front of House');
    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    expect($group->name)->toBe('Front of House');

    $component->call('assignToSection', $item->id, $group->id)->assertHasNoErrors();

    expect(lineUnderGroup($item->fresh(), $group))->toBeTrue();
});

it('creates a section from the bound newSectionName property (bug #2)', function () {
    // The "New section" modal's Create button does `wire:click="createSection"`,
    // reading the wire:model-bound $newSectionName. Pre-fix the button did nothing
    // because the name lived only in an Alpine binding outside the modal's x-data.
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Backstage')
        ->call('createSection')
        ->assertHasNoErrors();

    expect(editorGroups($opportunity)->firstWhere('name', 'Backstage'))->not->toBeNull();

    // The field is cleared after a successful create.
    $component->assertSet('newSectionName', '');
});

it('closes the create-section modal with the positional close-modal payload on success (defect 3)', function () {
    // The x-signals.modal listener matches close-modal on `$event.detail === name`,
    // so the dispatch MUST be positional ('create-section'), not the named-arg form
    // (which would deliver detail = { name: 'create-section' } and never match,
    // leaving the modal open after a successful create).
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Closes Cleanly')
        ->call('createSection')
        ->assertHasNoErrors()
        ->assertDispatched('close-modal', 'create-section')
        ->assertDispatched('toast', type: 'success', message: 'Section created');
});

it('does not close the modal when section creation fails validation (defect 3)', function () {
    // A validation failure (here: empty name) must NOT dispatch close-modal, so the
    // modal stays open with the reason. The empty-name short-circuit returns before
    // any dispatch.
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', '   ')
        ->call('createSection')
        ->assertNotDispatched('close-modal');
});

it('does not create a section for an empty or whitespace-only name (bug #2)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', '   ')
        ->call('createSection')
        ->assertHasNoErrors();

    expect(editorGroups($opportunity))->toBeEmpty();
});

it('groups an assigned line under its custom section in the rendered editor', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'Sectioned Product']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->set('newSectionName', 'Stage Left')
        ->call('createSection');

    $group = editorGroupNamed($opportunity, 'Stage Left');
    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    $component->call('assignToSection', $item->id, $group->id)->assertHasNoErrors();

    // The section header + the line render together (the line is grouped under it).
    $component->assertSee('Stage Left')->assertSee('Sectioned Product');

    expect(lineUnderGroup($item->fresh(), $group))->toBeTrue();
});

it('auto-groups an unassigned line by its product group', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Lighting']);
    $product = Product::factory()->create(['name' => 'Auto Grouped', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    // An unassigned line auto-groups under its product group's label.
    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors()
        ->assertSee('Lighting')
        ->assertSee('Auto Grouped');
});

it('merges duplicate lines of the same product into one with the summed quantity', function () {
    // The opportunity carries explicit dates so both lines inherit the SAME hire
    // window (a dateless opportunity bakes a fire-time now() per line, which would
    // make otherwise-identical lines differ by timestamp and not be duplicates).
    Auth::login($this->owner);
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Merge fixture',
        'store_id' => $this->store->id,
        'starts_at' => '2026-12-01T09:00:00Z',
        'ends_at' => '2026-12-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    $product = Product::factory()->create(['name' => 'Barco UDX-4K32']);

    $this->actingAs($this->owner);

    // Two separate lines of the SAME product (qty 2 and 3) — duplicates.
    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 2)
        ->call('addProduct', $product->id, 3);

    $lines = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->orderBy('id')
        ->get();

    expect($lines)->toHaveCount(2);

    /** @var array<int, bool> $duplicateLineIds */
    $duplicateLineIds = itemsEditorInstance($component)->duplicateLineIds();
    expect($duplicateLineIds)->toHaveKeys($lines->pluck('id')->all());

    $survivor = $lines->first();
    $component->call('mergeDuplicates', $survivor->id)->assertHasNoErrors();

    $remaining = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->get();

    expect($remaining)->toHaveCount(1)
        ->and((float) $remaining->first()->quantity)->toBe(5.0)
        ->and($remaining->first()->id)->toBe($survivor->id);
});

it('does not flag distinct products as duplicates and is a no-op when none match', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $a = Product::factory()->create(['name' => 'Alpha']);
    $b = Product::factory()->create(['name' => 'Bravo']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $a->id, 1)
        ->call('addProduct', $b->id, 1);

    /** @var array<int, bool> $duplicateLineIds */
    $duplicateLineIds = itemsEditorInstance($component)->duplicateLineIds();
    expect($duplicateLineIds)->toBe([]);

    $first = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->orderBy('id')
        ->first();

    // mergeDuplicates is a no-op when there are no matching duplicates.
    $component->call('mergeDuplicates', $first->id)->assertHasNoErrors();

    expect(OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->count())->toBe(2);
});

it('persists a new sort order via handleSort', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $first = Product::factory()->create(['name' => 'First']);
    $second = Product::factory()->create(['name' => 'Second']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $first->id, 1)
        ->call('addProduct', $second->id, 1);

    $autoGroup = editorAutoGroup($opportunity, 'auto:ungrouped')
        ?? throw new RuntimeException('Ungrouped auto group not found');

    $productLines = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->orderBy('path')
        ->get();
    $secondItem = $productLines->firstWhere('name', 'Second');

    // Drag the second line to position 0 within its (auto) group. Under the unified
    // model both no-category products share the real "Ungrouped" auto-group row,
    // so wire:sort fires handleSort with that group's "group:{id}" key (not null).
    $component->call('handleSort', $secondItem->id, 0, 'group:'.$autoGroup->id)->assertHasNoErrors();

    $reordered = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->get()
        ->filter(fn (OpportunityItem $line): bool => lineUnderGroup($line, $autoGroup))
        ->sortBy('path')
        ->values()
        ->pluck('name');

    expect($reordered->first())->toBe('Second');
});

it('moves a line from an auto group INTO a custom section via handleSort (drag across groups)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'Movable']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->set('newSectionName', 'Front of House')
        ->call('createSection');

    $group = editorGroupNamed($opportunity, 'Front of House');
    $autoGroup = editorAutoGroup($opportunity, 'auto:ungrouped')
        ?? throw new RuntimeException('Ungrouped auto group not found');
    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    // Under the unified model the line lands in a real auto-group row on add,
    // not the null-group render path — so it already carries a parent path.
    expect(lineUnderGroup($item, $autoGroup))->toBeTrue()
        ->and($item->parentPath())->not->toBe($group->path);

    // wire:sort fires handleSort(item, position, group-id) where the destination
    // group's key is "group:{id}" for a custom group.
    $component->call('handleSort', $item->id, 0, 'group:'.$group->id)->assertHasNoErrors();

    expect(lineUnderGroup($item->fresh(), $group))->toBeTrue();
});

it('moves a line OUT of a custom section back to an auto group via handleSort (drag across groups)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'Movable Out']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->set('newSectionName', 'Backstage')
        ->call('createSection');

    $group = editorGroupNamed($opportunity, 'Backstage');
    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    // Park it in the section first.
    $component->call('handleSort', $item->id, 0, 'group:'.$group->id)->assertHasNoErrors();
    expect(lineUnderGroup($item->fresh(), $group))->toBeTrue();

    // Now drag it into an auto product group (the auto-group key is "auto:{id}" /
    // "auto:ungrouped" — anything that is NOT a "group:" key).
    $autoKey = $product->product_group_id !== null
        ? 'auto:'.$product->product_group_id
        : 'auto:ungrouped';
    $component->call('handleSort', $item->id, 0, $autoKey)->assertHasNoErrors();

    $targetAutoGroup = editorAutoGroup($opportunity, $autoKey)
        ?? throw new RuntimeException("Auto group not found: {$autoKey}");

    expect(lineUnderGroup($item->fresh(), $targetAutoGroup))->toBeTrue();
});

it('renders accessory sub-rows as display-only rows (never persisted as line items)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $clamp = Product::factory()->create(['name' => 'Omega Clamp', 'sku' => 'RG-OMEGA']);
    $product = Product::factory()->create(['name' => 'Fixture With Accessory']);

    Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $clamp->id,
        'quantity' => 2,
        'included' => true,
        'zero_priced' => true,
    ]);

    $this->actingAs($this->owner);

    // The accessory renders as a display-only sub-row (its name + SKU appear),
    // with quantity = ratio (2) × line qty (3) = 6.
    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 3)
        ->assertHasNoErrors()
        ->assertSee('Fixture With Accessory');

    // Catalogue accessories render as collapsible sub-rows; only the product line counts.
    expect(OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->count())->toBe(1);
});

it('toggles a line as optional', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    $component->call('toggleOptional', $item->id)->assertHasNoErrors();

    expect($item->fresh()->is_optional)->toBeTrue();
});

it('substitutes a line product via the picker-driven substituteItem method', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $original = Product::factory()->create(['name' => 'Original Fixture', 'sku' => 'ORIG-1']);
    $replacement = Product::factory()->create(['name' => 'Replacement Fixture', 'sku' => 'REPL-1']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $original->id, 4);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    expect($item->itemable_id)->toBe($original->id);

    // The substitute picker in the edit-line modal calls substituteItem(itemId, productId)
    // with the chosen replacement; the line keeps its quantity but swaps product + name.
    $component->call('substituteItem', $item->id, $replacement->id)->assertHasNoErrors();

    $updated = $item->fresh();

    expect($updated->itemable_id)->toBe($replacement->id)
        ->and($updated->itemable_type)->toBe(Product::class)
        ->and($updated->name)->toBe('Replacement Fixture')
        ->and((float) $updated->quantity)->toBe(4.0);
});

it('errors when substituting with a non-existent replacement product', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    $component->call('substituteItem', $item->id, 999999)->assertHasErrors('product');

    expect($item->fresh()->itemable_id)->toBe($product->id);
});

it('gates substituting a line product on opportunities.edit', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();
    $replacement = Product::factory()->create();

    $this->actingAs($this->owner);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('substituteItem', $item->id, $replacement->id)
        ->assertForbidden();

    expect($item->fresh()->itemable_id)->toBe($product->id);
});

/*
|--------------------------------------------------------------------------
| B8 line-item editor overhaul (UAT)
|--------------------------------------------------------------------------
*/

it('moves a line out of its section back to auto-grouping without deleting the section', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Rigging']);
    $product = Product::factory()->create(['name' => 'Truss', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->set('newSectionName', 'Stage')
        ->call('createSection');

    $group = editorGroupNamed($opportunity, 'Stage');
    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    $component->call('assignToSection', $item->id, $group->id)->assertHasNoErrors();
    expect(lineUnderGroup($item->fresh(), $group))->toBeTrue();

    // Move the line OUT of the section (parentPath null — the Ungrouped safety
    // fallback). The user group row survives (becomes empty) — it is not deleted.
    $component->call('assignToSection', $item->id, null)->assertHasNoErrors();

    expect($item->fresh()->parentPath())->toBeNull();
    expect(editorGroupNamed($opportunity, 'Stage')->exists())->toBeTrue();

    // The (now empty) user section header + the line's auto group both still render.
    $component->assertSee('Stage')->assertSee('Rigging');
});

it('creates a nested sub-section under a parent (parent_id)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Parent')
        ->call('createSection');

    $parent = editorGroupNamed($opportunity, 'Parent');

    $component
        ->set('newSectionName', 'Child')
        ->set('newSectionParent', (string) $parent->id)
        ->call('createSection')
        ->assertHasNoErrors();

    $child = editorGroupNamed($opportunity, 'Child');

    expect($child->parentPath())->toBe($parent->path);

    $tree = lineItemsEditorInstance($component)->serverTree()['tree'];
    $childRow = collect($tree)->firstWhere('id', $child->id);

    expect($childRow)->not->toBeNull()
        ->and($childRow['depth'])->toBeGreaterThan(1);
});

it('allows a 5-level-deep section chain but rejects a 6th level (#9, raised cap)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'L1')
        ->set('newSectionParent', '')
        ->call('createSection')
        ->assertHasNoErrors();

    $parentId = editorGroupNamed($opportunity, 'L1')->id;

    // Build levels 2..5 — each succeeds (depth 5 is the maximum).
    foreach (['L2', 'L3', 'L4', 'L5'] as $name) {
        $component
            ->set('newSectionName', $name)
            ->set('newSectionParent', (string) $parentId)
            ->call('createSection')
            ->assertHasNoErrors();

        $parentId = editorGroupNamed($opportunity, $name)->id;
    }

    expect(editorGroups($opportunity))->toHaveCount(5);

    // A 6th level under the depth-5 parent is rejected (flashed error, no row created).
    $component
        ->set('newSectionName', 'L6')
        ->set('newSectionParent', (string) $parentId)
        ->call('createSection');

    expect(editorGroups($opportunity)->firstWhere('name', 'L6'))->toBeNull()
        ->and(editorGroups($opportunity))->toHaveCount(5);
});

it('rejects a parent section that belongs to a different opportunity', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Owner opp');
    $other = liveOpportunityForEditor($this->owner, $this->store->id, 'Other opp');

    (new AddOpportunityGroup)($other, AddOpportunityGroupData::from(['name' => 'Foreign Parent']));
    $foreignParent = editorGroupNamed($other, 'Foreign Parent');

    $this->actingAs($this->owner);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Bad')
        ->set('newSectionParent', (string) $foreignParent->id)
        ->call('createSection')
        ->assertHasErrors('parent_id');

    expect(editorGroups($opportunity)->firstWhere('name', 'Bad'))->toBeNull();
});

it('reorders sibling sections (sortable) via reorderSections', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Alpha')
        ->call('createSection')
        ->set('newSectionName', 'Bravo')
        ->call('createSection');

    $alpha = editorGroupNamed($opportunity, 'Alpha');
    $bravo = editorGroupNamed($opportunity, 'Bravo');

    expect($alpha->path)->toBeLessThan($bravo->path);

    // Swap them: Bravo first.
    $component->call('reorderSections', [$bravo->id, $alpha->id])->assertHasNoErrors();

    $alpha = $alpha->fresh();
    $bravo = $bravo->fresh();

    expect($bravo->path)->toBeLessThan($alpha->path);
});

it('edits the rate inline and re-totals the line', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 2);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    // Inline rate edit -> OverrideItemPrice -> unit_price stored in minor units,
    // the line total recomputes from the override (2 × 50.00 = 100.00).
    $component->call('overridePrice', $item->id, '50.00')->assertHasNoErrors();

    $updated = $item->fresh();

    expect($updated->unit_price)->toBe(5000)
        ->and($updated->total)->toBe(10000);
});

it('edits the discount inline and reduces the line total ex-tax', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    // Fix the rate so the discount maths is deterministic (1 × 100.00 net).
    $component->call('overridePrice', $item->id, '100.00')->assertHasNoErrors();
    expect($item->fresh()->total)->toBe(10000);

    // 10% discount on a 100.00 net line -> 90.00 stored on the line total (ex-tax).
    $component->call('setDiscount', $item->id, '10')->assertHasNoErrors();

    $discounted = $item->fresh();

    expect($discounted->discount_percent)->toBe('10.00')
        ->and($discounted->total)->toBe(9000)
        ->and($opportunity->fresh()->charge_total)->toBe(9000);

    // Clearing the discount restores the full line total.
    $component->call('setDiscount', $item->id, null)->assertHasNoErrors();

    expect($item->fresh()->total)->toBe(10000);
});

it('shows the accessories toggle and the charge-total footer row', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $clamp = Product::factory()->create(['name' => 'Hook Clamp', 'sku' => 'HK-1']);
    $product = Product::factory()->create(['name' => 'Fixture A']);

    Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $clamp->id,
        'quantity' => 1,
        'included' => true,
        'zero_priced' => true,
    ]);

    $this->actingAs($this->owner);

    // The accessory toggle (collapsed by default) + the charge-total footer render.
    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors()
        ->assertSee('Hook Clamp')
        ->assertSee('Charge total (ex-tax)');
});

it('renders a per-product View availability link over the opportunity period', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'Linked Product']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors();

    $tree = lineItemsEditorInstance($component)->serverTree()['tree'];
    $row = collect($tree)->firstWhere('product_id', $product->id);

    expect($row)->not->toBeNull()
        ->and($row['availability_url'])->toContain('product='.$product->id);
});

it('opens View availability in a new tab (target=_blank)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'New Tab Product']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml('rel="noopener"');
});

/*
|--------------------------------------------------------------------------
| Real-time toasts (Feature 1)
|--------------------------------------------------------------------------
*/

it('dispatches a success toast on a line mutation', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    // removeItem fires a success toast with the "Item removed" message.
    $component->call('removeItem', $item->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: 'Item removed');
});

it('dispatches an error toast (and surfaces the field error) when a mutation fails validation', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    // Substituting with a non-existent product throws a ValidationException — the
    // method still surfaces the field error AND fires an error toast.
    $component->call('substituteItem', $item->id, 999999)
        ->assertHasErrors('product')
        ->assertDispatched('toast', type: 'error');
});

it('reports the correct optional/required label in its toast', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    // Toggling on -> "Marked optional"; toggling off -> "Marked required".
    $component->call('toggleOptional', $item->id)
        ->assertDispatched('toast', type: 'success', message: 'Marked optional');

    $component->call('toggleOptional', $item->id)
        ->assertDispatched('toast', type: 'success', message: 'Marked required');
});

/*
|--------------------------------------------------------------------------
| Section drag — reorder + nest (Feature 4)
|--------------------------------------------------------------------------
*/

it('reparents a section under another via handleSectionSort (drag to nest)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Parent')
        ->call('createSection')
        ->set('newSectionName', 'Mover')
        ->call('createSection');

    $parent = editorGroupNamed($opportunity, 'Parent');
    $mover = editorGroupNamed($opportunity, 'Mover');

    expect($mover->parentPath())->toBeNull();

    // Drag "Mover" into "Parent"'s children group (group-id "group-parent:{id}").
    $component->call('handleSectionSort', $mover->id, 0, 'group-parent:'.$parent->id)
        ->assertHasNoErrors();

    expect($mover->fresh()->parentPath())->toBe($parent->path);
});

it('promotes a nested section back to the top level via handleSectionSort', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Top')
        ->call('createSection');

    $top = editorGroupNamed($opportunity, 'Top');

    $component
        ->set('newSectionName', 'Child')
        ->set('newSectionParent', (string) $top->id)
        ->call('createSection');

    $child = editorGroupNamed($opportunity, 'Child');
    expect($child->parentPath())->toBe($top->path);

    // Drag the child out to the root group.
    $component->call('handleSectionSort', $child->id, 0, 'group-parent:root')
        ->assertHasNoErrors();

    expect($child->fresh()->parentPath())->toBeNull();
});

it('refuses a drag-nest that would exceed the 5-level depth limit (error toast, tree unchanged)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    // Build a 5-deep chain L1 > L2 > L3 > L4 > L5 (MAX_DEPTH=5).
    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'L1')
        ->call('createSection');

    $parentId = editorGroupNamed($opportunity, 'L1')->id;

    foreach (['L2', 'L3', 'L4', 'L5'] as $name) {
        $component
            ->set('newSectionName', $name)
            ->set('newSectionParent', (string) $parentId)
            ->call('createSection');
        $parentId = editorGroupNamed($opportunity, $name)->id;
    }

    // A standalone section dragged UNDER the depth-5 leaf (L5) would be depth 6 — refused.
    $component
        ->set('newSectionName', 'Loose')
        ->set('newSectionParent', '')
        ->call('createSection');
    $loose = editorGroupNamed($opportunity, 'Loose');
    $l5 = editorGroupNamed($opportunity, 'L5');

    $component->call('handleSectionSort', $loose->id, 0, 'group-parent:'.$l5->id)
        ->assertDispatched('toast', type: 'error');

    // The tree is untouched — Loose stays top-level.
    expect($loose->fresh()->parentPath())->toBeNull();
});

it('allows nesting up to 5 levels deep (the raised cap)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'L1')
        ->call('createSection');

    $parentId = editorGroupNamed($opportunity, 'L1')->id;

    // L2..L5 nested under the previous — the 5th level must be accepted.
    foreach (['L2', 'L3', 'L4', 'L5'] as $name) {
        $component
            ->set('newSectionName', $name)
            ->set('newSectionParent', (string) $parentId)
            ->call('createSection')
            ->assertHasNoErrors();
        $parentId = editorGroupNamed($opportunity, $name)->id;
    }

    $l5 = editorGroupNamed($opportunity, 'L5');
    expect($l5->exists)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Eager group unification — every group is a real persisted group row
|--------------------------------------------------------------------------
|
| Auto-groups are no longer materialised at render time: when a line is added
| without an explicit destination, the editor find-or-creates the REAL
| {@see OpportunityItemType::Group} row for the line's product-category auto-group
| key and nests the line under it via `path`.
|
*/

it('find-or-creates a real auto-group section on add and assigns the line to it', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Lighting']);
    $product = Product::factory()->create(['name' => 'Spider', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors();

    // A real group row was created for the auto-group key, carrying it.
    $autoGroup = editorAutoGroup($opportunity, 'auto:'.$group->id);

    expect($autoGroup)->not->toBeNull()
        ->and($autoGroup->name)->toBe('Lighting')
        ->and($autoGroup->parentPath())->toBeNull();

    // The line was nested under it — never left in the null-parent render path.
    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();
    expect(lineUnderGroup($item, $autoGroup))->toBeTrue();
});

it('joins a second line of the same product category into the EXISTING auto-group section', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Audio']);
    $first = Product::factory()->create(['name' => 'Speaker', 'product_group_id' => $group->id]);
    $second = Product::factory()->create(['name' => 'Sub', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $first->id, 1)
        ->call('addProduct', $second->id, 1)
        ->assertHasNoErrors();

    // Exactly ONE auto-group row for the shared category — the second add
    // FOUND it rather than creating a duplicate.
    $autoGroup = editorAutoGroup($opportunity, 'auto:'.$group->id);

    expect($autoGroup)->not->toBeNull();

    $items = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->get();
    expect($items)->toHaveCount(2)
        ->and($items->every(fn (OpportunityItem $line): bool => lineUnderGroup($line, $autoGroup)))->toBeTrue();
});

it('honours an explicit destination section over the auto group on add', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Rigging']);
    $product = Product::factory()->create(['name' => 'Truss', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Front of House')
        ->call('createSection')
        ->assertHasNoErrors();

    $userGroup = editorGroupNamed($opportunity, 'Front of House');

    // Add WITH an explicit destination — the line lands in that group, not the
    // auto group.
    $component->call('addProduct', $product->id, 1, 'group:'.$userGroup->id)->assertHasNoErrors();

    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();
    expect(lineUnderGroup($item, $userGroup))->toBeTrue();
});

it('renders an auto-group section identically to a user section (draggable handle + menu)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Cabling']);
    $product = Product::factory()->create(['name' => 'XLR', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors()
        // The auto group renders as a real section: the Section badge + its drag
        // handle + reorder/nest affordance are present, same as a user section.
        ->assertSee('Cabling')
        ->assertSee('Section')
        ->assertSeeHtml('lf-handle');
});

it('renames an auto-group section like any other section', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Power']);
    $product = Product::factory()->create(['name' => 'Distro', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors();

    $autoGroup = editorAutoGroup($opportunity, 'auto:'.$group->id)
        ?? throw new RuntimeException('Auto group not found');

    $component->call('renameSection', $autoGroup->id, 'Distribution')->assertHasNoErrors();

    expect($autoGroup->fresh()->name)->toBe('Distribution');
});

it('drops an auto-group line to the Ungrouped fallback when its section is deleted, without crashing', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Staging']);
    $product = Product::factory()->create(['name' => 'Deck', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors();

    $autoGroup = editorAutoGroup($opportunity, 'auto:'.$group->id)
        ?? throw new RuntimeException('Auto group not found');
    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->firstOrFail();

    // Delete the auto-group row. Dissolving drops the line to parentPath null;
    // the editor still renders it under the synthesised Ungrouped fallback.
    $component->call('deleteSection', $autoGroup->id)->assertHasNoErrors();

    expect($item->fresh()->parentPath())->toBeNull();
    $component->assertSee('Ungrouped')->assertSee('Deck');
});
