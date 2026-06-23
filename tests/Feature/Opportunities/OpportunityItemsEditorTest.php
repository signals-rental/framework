<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Accessory;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunitySection;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

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
    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('+ Section');
});

it('forbids the items tab for a user without opportunities.view', function () {
    $opportunity = Opportunity::factory()->create();

    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    Volt::test('opportunities.items', ['opportunity' => $opportunity])->assertForbidden();
});

it('renders read-only (non-editable) for a view-only user', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    // A view-only user gets the read-only render: no editor toolbar. The embedded
    // editor renders the empty-state body (no page header) for an item-less opp.
    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('No line items')
        ->assertDontSee('+ Section');
});

it('adds a line item via the picker and reflects it in the totals', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'Robe Spiider', 'sku' => 'MH-SPID']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 6)
        ->assertHasNoErrors();

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->first();

    expect($item)->not->toBeNull()
        ->and($item->name)->toBe('Robe Spiider')
        ->and($item->item_id)->toBe($product->id)
        ->and($item->item_type)->toBe(Product::class)
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

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertForbidden();

    expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(0);
});

it('removes a line item', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 2);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    $component->call('removeItem', $item->id)->assertHasNoErrors();

    expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(0);
});

it('changes a line quantity and recomputes totals', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();
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

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

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

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    $component->call('overridePrice', $item->id, '125.00')->assertHasNoErrors();

    expect($item->fresh()->unit_price)->toBe(12500);

    // Clearing the override (null) returns the line to rate-engine pricing.
    $component->call('overridePrice', $item->id, null)->assertHasNoErrors();
});

it('creates a section and assigns a line to it', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        // createSection() reads the bound $newSectionName property (bug #2 fix —
        // it no longer takes the name as a call argument).
        ->set('newSectionName', 'Front of House')
        ->call('createSection')
        ->assertHasNoErrors();

    $section = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('name', 'Front of House')
        ->firstOrFail();
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    expect($section->name)->toBe('Front of House');

    $component->call('assignToSection', $item->id, $section->id)->assertHasNoErrors();

    expect($item->fresh()->section_id)->toBe($section->id);
});

it('creates a section from the bound newSectionName property (bug #2)', function () {
    // The "New section" modal's Create button does `wire:click="createSection"`,
    // reading the wire:model-bound $newSectionName. Pre-fix the button did nothing
    // because the name lived only in an Alpine binding outside the modal's x-data.
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Backstage')
        ->call('createSection')
        ->assertHasNoErrors();

    expect(OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('name', 'Backstage')
        ->exists())->toBeTrue();

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

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
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

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->set('newSectionName', '   ')
        ->call('createSection')
        ->assertNotDispatched('close-modal');
});

it('does not create a section for an empty or whitespace-only name (bug #2)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->set('newSectionName', '   ')
        ->call('createSection')
        ->assertHasNoErrors();

    expect(OpportunitySection::query()->where('opportunity_id', $opportunity->id)->exists())
        ->toBeFalse();
});

it('groups an assigned line under its custom section in the rendered editor', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'Sectioned Product']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->set('newSectionName', 'Stage Left')
        ->call('createSection');

    $section = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('name', 'Stage Left')
        ->firstOrFail();
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    $component->call('assignToSection', $item->id, $section->id)->assertHasNoErrors();

    // The section header + the line render together (the line is grouped under it).
    $component->assertSee('Stage Left')->assertSee('Sectioned Product');

    expect($item->fresh()->section_id)->toBe($section->id);
});

it('auto-groups an unassigned line by its product group', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Lighting']);
    $product = Product::factory()->create(['name' => 'Auto Grouped', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    // An unassigned line auto-groups under its product group's label.
    Volt::test('opportunities.items', ['opportunity' => $opportunity])
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
    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 2)
        ->call('addProduct', $product->id, 3);

    $lines = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->orderBy('id')
        ->get();

    expect($lines)->toHaveCount(2)
        // Both lines are flagged as duplicates of each other.
        ->and($component->instance()->duplicateLineIds())->toHaveKeys($lines->pluck('id')->all());

    $survivor = $lines->first();
    $component->call('mergeDuplicates', $survivor->id)->assertHasNoErrors();

    $remaining = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->get();

    expect($remaining)->toHaveCount(1)
        ->and((float) $remaining->first()->quantity)->toBe(5.0)
        ->and($remaining->first()->id)->toBe($survivor->id);
});

it('does not flag distinct products as duplicates and is a no-op when none match', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $a = Product::factory()->create(['name' => 'Alpha']);
    $b = Product::factory()->create(['name' => 'Bravo']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $a->id, 1)
        ->call('addProduct', $b->id, 1);

    expect($component->instance()->duplicateLineIds())->toBe([]);

    $first = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->orderBy('id')->first();

    // mergeDuplicates is a no-op when there are no matching duplicates.
    $component->call('mergeDuplicates', $first->id)->assertHasNoErrors();

    expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(2);
});

it('persists a new sort order via handleSort', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $first = Product::factory()->create(['name' => 'First']);
    $second = Product::factory()->create(['name' => 'Second']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $first->id, 1)
        ->call('addProduct', $second->id, 1);

    $items = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->orderBy('sort_order')->get();
    $secondItem = $items->firstWhere('name', 'Second');

    // Drag the second line to position 0 within its (auto) group. Under the unified
    // model both no-category products share the real "Ungrouped" auto-group section,
    // so wire:sort fires handleSort with that section's "section:{id}" key (not null).
    $component->call('handleSort', $secondItem->id, 0, 'section:'.$secondItem->section_id)->assertHasNoErrors();

    $reordered = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->orderBy('sort_order')
        ->pluck('name');

    expect($reordered->first())->toBe('Second');
});

it('moves a line from an auto group INTO a custom section via handleSort (drag across groups)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'Movable']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->set('newSectionName', 'Front of House')
        ->call('createSection');

    // The user section (an auto-group section was also created on add).
    $section = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('name', 'Front of House')
        ->firstOrFail();
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    // Under the unified model the line lands in a real auto-group section on add,
    // not the null-section render path — so it already carries a (non-user) section.
    expect($item->section_id)->not->toBeNull()
        ->and($item->section_id)->not->toBe($section->id);

    // wire:sort fires handleSort(item, position, group-id) where the destination
    // group's key is "section:{id}" for a custom section.
    $component->call('handleSort', $item->id, 0, 'section:'.$section->id)->assertHasNoErrors();

    expect($item->fresh()->section_id)->toBe($section->id);
});

it('moves a line OUT of a custom section back to an auto group via handleSort (drag across groups)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'Movable Out']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->set('newSectionName', 'Backstage')
        ->call('createSection');

    $section = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('name', 'Backstage')
        ->firstOrFail();
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    // Park it in the section first.
    $component->call('handleSort', $item->id, 0, 'section:'.$section->id)->assertHasNoErrors();
    expect($item->fresh()->section_id)->toBe($section->id);

    // Now drag it into an auto product group (the auto-group key is "auto:{id}" /
    // "auto:ungrouped" — anything that is NOT a "section:" key), which clears the
    // section assignment.
    $autoKey = $product->product_group_id !== null
        ? 'auto:'.$product->product_group_id
        : 'auto:ungrouped';
    $component->call('handleSort', $item->id, 0, $autoKey)->assertHasNoErrors();

    expect($item->fresh()->section_id)->toBeNull();
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
    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 3)
        ->assertHasNoErrors()
        ->assertSee('Fixture With Accessory')
        ->assertSee('Omega Clamp')
        ->assertSee('incl.');

    // The accessory is display-only — only the one product line is persisted.
    expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(1);
});

it('toggles a line as optional', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    $component->call('toggleOptional', $item->id)->assertHasNoErrors();

    expect($item->fresh()->is_optional)->toBeTrue();
});

it('substitutes a line product via the picker-driven substituteItem method', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $original = Product::factory()->create(['name' => 'Original Fixture', 'sku' => 'ORIG-1']);
    $replacement = Product::factory()->create(['name' => 'Replacement Fixture', 'sku' => 'REPL-1']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $original->id, 4);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    expect($item->item_id)->toBe($original->id);

    // The substitute picker in the edit-line modal calls substituteItem(itemId, productId)
    // with the chosen replacement; the line keeps its quantity but swaps product + name.
    $component->call('substituteItem', $item->id, $replacement->id)->assertHasNoErrors();

    $updated = $item->fresh();

    expect($updated->item_id)->toBe($replacement->id)
        ->and($updated->item_type)->toBe(Product::class)
        ->and($updated->name)->toBe('Replacement Fixture')
        ->and((float) $updated->quantity)->toBe(4.0);
});

it('errors when substituting with a non-existent replacement product', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    $component->call('substituteItem', $item->id, 999999)->assertHasErrors('product');

    expect($item->fresh()->item_id)->toBe($product->id);
});

it('gates substituting a line product on opportunities.edit', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();
    $replacement = Product::factory()->create();

    $this->actingAs($this->owner);

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('substituteItem', $item->id, $replacement->id)
        ->assertForbidden();

    expect($item->fresh()->item_id)->toBe($product->id);
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

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->set('newSectionName', 'Stage')
        ->call('createSection');

    $section = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('name', 'Stage')
        ->firstOrFail();
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    $component->call('assignToSection', $item->id, $section->id)->assertHasNoErrors();
    expect($item->fresh()->section_id)->toBe($section->id);

    // Move the line OUT of the section (section_id null — the Ungrouped safety
    // fallback). The user section row survives (becomes empty) — it is not deleted.
    $component->call('assignToSection', $item->id, null)->assertHasNoErrors();

    expect($item->fresh()->section_id)->toBeNull();
    expect(OpportunitySection::query()->whereKey($section->id)->exists())->toBeTrue();

    // The (now empty) user section header + the line's auto group both still render.
    $component->assertSee('Stage')->assertSee('Rigging');
});

it('creates a nested sub-section under a parent (parent_id)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Parent')
        ->call('createSection');

    $parent = OpportunitySection::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    $component
        ->set('newSectionName', 'Child')
        ->set('newSectionParent', (string) $parent->id)
        ->call('createSection')
        ->assertHasNoErrors();

    $child = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('name', 'Child')
        ->firstOrFail();

    expect($child->parent_id)->toBe($parent->id);

    // The nested section renders with a Sub-section badge under the editor.
    $component->assertSee('Sub-section')->assertSee('Child');
});

it('allows a 5-level-deep section chain but rejects a 6th level (#9, raised cap)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'L1')
        ->set('newSectionParent', '')
        ->call('createSection')
        ->assertHasNoErrors();

    $parentId = OpportunitySection::query()->where('opportunity_id', $opportunity->id)->where('name', 'L1')->firstOrFail()->id;

    // Build levels 2..5 — each succeeds (depth 5 is the maximum).
    foreach (['L2', 'L3', 'L4', 'L5'] as $name) {
        $component
            ->set('newSectionName', $name)
            ->set('newSectionParent', (string) $parentId)
            ->call('createSection')
            ->assertHasNoErrors();

        $parentId = OpportunitySection::query()->where('opportunity_id', $opportunity->id)->where('name', $name)->firstOrFail()->id;
    }

    expect(OpportunitySection::query()->where('opportunity_id', $opportunity->id)->count())->toBe(5);

    // A 6th level under the depth-5 parent is rejected (flashed error, no row created).
    $component
        ->set('newSectionName', 'L6')
        ->set('newSectionParent', (string) $parentId)
        ->call('createSection');

    expect(OpportunitySection::query()->where('opportunity_id', $opportunity->id)->where('name', 'L6')->exists())->toBeFalse()
        ->and(OpportunitySection::query()->where('opportunity_id', $opportunity->id)->count())->toBe(5);
});

it('rejects a parent section that belongs to a different opportunity', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Owner opp');
    $other = liveOpportunityForEditor($this->owner, $this->store->id, 'Other opp');

    $foreignParent = OpportunitySection::factory()->create(['opportunity_id' => $other->id]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Bad')
        ->set('newSectionParent', (string) $foreignParent->id)
        ->call('createSection')
        ->assertHasErrors('parent_id');

    expect(OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('name', 'Bad')
        ->exists())->toBeFalse();
});

it('reorders sibling sections (sortable) via reorderSections', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Alpha')
        ->call('createSection')
        ->set('newSectionName', 'Bravo')
        ->call('createSection');

    $alpha = OpportunitySection::query()->where('name', 'Alpha')->firstOrFail();
    $bravo = OpportunitySection::query()->where('name', 'Bravo')->firstOrFail();

    expect($alpha->sort_order)->toBeLessThan($bravo->sort_order);

    // Swap them: Bravo first.
    $component->call('reorderSections', [$bravo->id, $alpha->id])->assertHasNoErrors();

    expect($bravo->fresh()->sort_order)->toBe(0)
        ->and($alpha->fresh()->sort_order)->toBe(1);
});

it('edits the rate inline and re-totals the line', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 2);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

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

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

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
    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors()
        ->assertSee('Hook Clamp')
        ->assertSee('Charge total (ex-tax)');
});

it('renders a per-product View availability link over the opportunity period', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'Linked Product']);

    $this->actingAs($this->owner);

    // The row-actions menu carries a View availability deep link to the gantt view
    // filtered to this product + store.
    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors()
        ->assertSee('View availability')
        ->assertSee('product='.$product->id);
});

it('opens View availability in a new tab (target=_blank)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'New Tab Product']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
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

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    // removeItem fires a success toast with the "Item removed" message.
    $component->call('removeItem', $item->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: 'Item removed');
});

it('dispatches an error toast (and surfaces the field error) when a mutation fails validation', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create();

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

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

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1);

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

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

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Parent')
        ->call('createSection')
        ->set('newSectionName', 'Mover')
        ->call('createSection');

    $parent = OpportunitySection::query()->where('name', 'Parent')->firstOrFail();
    $mover = OpportunitySection::query()->where('name', 'Mover')->firstOrFail();

    expect($mover->parent_id)->toBeNull();

    // Drag "Mover" into "Parent"'s children group (group-id "section-parent:{id}").
    $component->call('handleSectionSort', $mover->id, 0, 'section-parent:'.$parent->id)
        ->assertHasNoErrors();

    expect($mover->fresh()->parent_id)->toBe($parent->id);
});

it('promotes a nested section back to the top level via handleSectionSort', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Top')
        ->call('createSection');

    $top = OpportunitySection::query()->where('name', 'Top')->firstOrFail();

    $component
        ->set('newSectionName', 'Child')
        ->set('newSectionParent', (string) $top->id)
        ->call('createSection');

    $child = OpportunitySection::query()->where('name', 'Child')->firstOrFail();
    expect($child->parent_id)->toBe($top->id);

    // Drag the child out to the root group.
    $component->call('handleSectionSort', $child->id, 0, 'section-parent:root')
        ->assertHasNoErrors();

    expect($child->fresh()->parent_id)->toBeNull();
});

it('refuses a drag-nest that would exceed the 5-level depth limit (error toast, tree unchanged)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    // Build a 5-deep chain L1 > L2 > L3 > L4 > L5 (MAX_DEPTH=5).
    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'L1')
        ->call('createSection');

    $parentId = OpportunitySection::query()->where('name', 'L1')->firstOrFail()->id;

    foreach (['L2', 'L3', 'L4', 'L5'] as $name) {
        $component
            ->set('newSectionName', $name)
            ->set('newSectionParent', (string) $parentId)
            ->call('createSection');
        $parentId = OpportunitySection::query()->where('name', $name)->firstOrFail()->id;
    }

    // A standalone section dragged UNDER the depth-5 leaf (L5) would be depth 6 — refused.
    $component
        ->set('newSectionName', 'Loose')
        ->set('newSectionParent', '')
        ->call('createSection');
    $loose = OpportunitySection::query()->where('name', 'Loose')->firstOrFail();
    $l5 = OpportunitySection::query()->where('name', 'L5')->firstOrFail();

    $component->call('handleSectionSort', $loose->id, 0, 'section-parent:'.$l5->id)
        ->assertDispatched('toast', type: 'error');

    // The tree is untouched — Loose stays top-level.
    expect($loose->fresh()->parent_id)->toBeNull();
});

it('allows nesting up to 5 levels deep (the raised cap)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'L1')
        ->call('createSection');

    $parentId = OpportunitySection::query()->where('name', 'L1')->firstOrFail()->id;

    // L2..L5 nested under the previous — the 5th level must be accepted.
    foreach (['L2', 'L3', 'L4', 'L5'] as $name) {
        $component
            ->set('newSectionName', $name)
            ->set('newSectionParent', (string) $parentId)
            ->call('createSection')
            ->assertHasNoErrors();
        $parentId = OpportunitySection::query()->where('name', $name)->firstOrFail()->id;
    }

    $l5 = OpportunitySection::query()->where('name', 'L5')->firstOrFail();
    expect($l5->exists)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Eager group unification — every group is a real persisted section
|--------------------------------------------------------------------------
|
| Auto-groups are no longer materialised at render time: when a line is added
| without an explicit destination, the editor find-or-creates the REAL
| `opportunity_sections` row for the line's product-category auto-group key and
| assigns the line to it. The line therefore always carries a section_id.
|
*/

it('find-or-creates a real auto-group section on add and assigns the line to it', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Lighting']);
    $product = Product::factory()->create(['name' => 'Spider', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors();

    // A real section was created for the auto-group key, carrying it.
    $section = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('auto_group_key', 'auto:'.$group->id)
        ->first();

    expect($section)->not->toBeNull()
        ->and($section->name)->toBe('Lighting')
        ->and($section->parent_id)->toBeNull();

    // The line was assigned to it — never left in the null-section render path.
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();
    expect($item->section_id)->toBe($section->id);
});

it('joins a second line of the same product category into the EXISTING auto-group section', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Audio']);
    $first = Product::factory()->create(['name' => 'Speaker', 'product_group_id' => $group->id]);
    $second = Product::factory()->create(['name' => 'Sub', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $first->id, 1)
        ->call('addProduct', $second->id, 1)
        ->assertHasNoErrors();

    // Exactly ONE auto-group section for the shared category — the second add
    // FOUND it rather than creating a duplicate.
    $sections = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('auto_group_key', 'auto:'.$group->id)
        ->get();

    expect($sections)->toHaveCount(1);

    $items = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->get();
    expect($items)->toHaveCount(2)
        ->and($items->pluck('section_id')->unique()->all())->toBe([$sections->first()->id]);
});

it('honours an explicit destination section over the auto group on add', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Rigging']);
    $product = Product::factory()->create(['name' => 'Truss', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Front of House')
        ->call('createSection')
        ->assertHasNoErrors();

    $section = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('name', 'Front of House')
        ->firstOrFail();

    // Add WITH an explicit destination — the line lands in that section, not the
    // auto group.
    $component->call('addProduct', $product->id, 1, 'section:'.$section->id)->assertHasNoErrors();

    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();
    expect($item->section_id)->toBe($section->id);
});

it('renders an auto-group section identically to a user section (draggable handle + menu)', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Cabling']);
    $product = Product::factory()->create(['name' => 'XLR', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors()
        // The auto group renders as a real section: the Section badge + its drag
        // handle + reorder/nest affordance are present, same as a user section.
        ->assertSee('Cabling')
        ->assertSee('Section')
        ->assertSeeHtml('wire:sort:handle');
});

it('renames an auto-group section like any other section', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Power']);
    $product = Product::factory()->create(['name' => 'Distro', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors();

    $section = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('auto_group_key', 'auto:'.$group->id)
        ->firstOrFail();

    $component->call('renameSection', $section->id, 'Distribution')->assertHasNoErrors();

    expect($section->fresh()->name)->toBe('Distribution');
});

it('drops an auto-group line to the Ungrouped fallback when its section is deleted, without crashing', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $group = ProductGroup::factory()->create(['name' => 'Staging']);
    $product = Product::factory()->create(['name' => 'Deck', 'product_group_id' => $group->id]);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->assertHasNoErrors();

    $section = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('auto_group_key', 'auto:'.$group->id)
        ->firstOrFail();
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    // Delete the auto-group section. nullOnDelete drops the line to section_id null;
    // the editor still renders it under the synthesised Ungrouped fallback.
    $component->call('deleteSection', $section->id)->assertHasNoErrors();

    expect($item->fresh()->section_id)->toBeNull();
    $component->assertSee('Ungrouped')->assertSee('Deck');
});
