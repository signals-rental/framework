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
    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('Line Items')
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

    // A view-only user gets the read-only render: no editor toolbar.
    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('Line Items')
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
        ->call('createSection', 'Front of House')
        ->assertHasNoErrors();

    $section = OpportunitySection::query()->where('opportunity_id', $opportunity->id)->firstOrFail();
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();

    expect($section->name)->toBe('Front of House');

    $component->call('assignToSection', $item->id, $section->id)->assertHasNoErrors();

    expect($item->fresh()->section_id)->toBe($section->id);
});

it('groups an assigned line under its custom section in the rendered editor', function () {
    $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
    $product = Product::factory()->create(['name' => 'Sectioned Product']);

    $this->actingAs($this->owner);

    $component = Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->call('addProduct', $product->id, 1)
        ->call('createSection', 'Stage Left');

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
