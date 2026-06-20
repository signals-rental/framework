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

    $section = OpportunitySection::query()->where('opportunity_id', $opportunity->id)->firstOrFail();
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

    $section = OpportunitySection::query()->where('opportunity_id', $opportunity->id)->firstOrFail();
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

    // Drag the second line to position 0 within its (auto) group.
    $component->call('handleSort', $secondItem->id, 0, null)->assertHasNoErrors();

    $reordered = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->orderBy('sort_order')
        ->pluck('name');

    expect($reordered->first())->toBe('Second');
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

    $section = OpportunitySection::query()->where('opportunity_id', $opportunity->id)->firstOrFail();
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    $component->call('assignToSection', $item->id, $section->id)->assertHasNoErrors();
    expect($item->fresh()->section_id)->toBe($section->id);

    // Move the line OUT of the section (back to auto-grouping). The section row
    // survives (becomes empty) — it is not deleted.
    $component->call('assignToSection', $item->id, null)->assertHasNoErrors();

    expect($item->fresh()->section_id)->toBeNull();
    expect(OpportunitySection::query()->whereKey($section->id)->exists())->toBeTrue();

    // The (now empty) section header + the line's auto group both still render.
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
