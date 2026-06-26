<?php

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\LockOpportunity;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\UpdateOpportunityItemDetails;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\UpdateOpportunityItemDetailsData;
use App\Models\Activity;
use App\Services\Opportunities\OpportunityLineItemsTreeBuilder;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require_once __DIR__.'/../../Support/LineItemsEditorTestHelpers.php';
use App\Enums\OpportunityItemType;
use App\Models\Accessory;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityEditorTreeService;
use App\Verbs\Events\Opportunities\ItemPriceOverridden;
use App\Verbs\Events\Opportunities\ItemRemoved;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;
use Thunk\Verbs\Models\VerbEvent;

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

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function runLineItemJs(string $script, array $payload): array
{
    $process = new Process(
        ['node', base_path($script)],
        base_path(),
        null,
        json_encode($payload, JSON_THROW_ON_ERROR),
    );

    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
    }

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
}

/**
 * @return array{group: OpportunityItem, first: OpportunityItem, second: OpportunityItem}
 */
function seedEditorGroupWithTwoItems(Opportunity $opportunity): array
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

describe('access and permissions', function () {
    it('renders the editable items tab for an editor', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Editable Items');

        $this->actingAs($this->owner);

        // Quick-add toolbar is only rendered when the component is editable.
        Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
            ->assertOk()
            ->assertSee('Quick add');
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
            ->assertDontSee('Quick add');
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

});

describe('adding product lines', function () {
    it('adds a line item via the picker and reflects it in the totals', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
        $product = Product::factory()->create(['name' => 'Robe Spiider', 'sku' => 'MH-SPID']);

        $this->actingAs($this->owner);

        $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
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

        // Fix the rate so the opportunity total is deterministic (6 × £10.00 net).
        $component->call('overridePrice', $item->id, '10.00')->assertHasNoErrors();

        expect($item->fresh()->total)->toBe(6000)
            ->and($opportunity->fresh()->charge_total)->toBe(6000);
    });

    it('auto-groups an unassigned line by its product group', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
        $group = ProductGroup::factory()->create(['name' => 'Lighting']);
        $product = Product::factory()->create(['name' => 'Auto Grouped', 'product_group_id' => $group->id]);

        $this->actingAs($this->owner);

        // An unassigned line auto-groups under its product group's label.
        $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
            ->call('addProduct', $product->id, 1)
            ->assertHasNoErrors();

        $tree = lineItemsEditorInstance($component)->serverTree()['tree'];
        $names = collect($tree)->pluck('name')->all();

        expect($names)->toContain('Lighting', 'Auto Grouped');
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

});

describe('removing lines via Livewire', function () {
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

});

describe('inline field edits and totals', function () {
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

    it('rejects an oversized unit price override with validation instead of overflowing totals (#378)', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
        $product = Product::factory()->create();

        $this->actingAs($this->owner);

        $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
            ->call('addProduct', $product->id, 1);

        $item = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('item_type', OpportunityItemType::Product)
            ->firstOrFail();

        $component->call('updateQuantity', $item->id, '507')
            ->call('updateField', $item->id, 'days', 3)
            ->call('overridePrice', $item->id, '20000000.00')
            ->assertHasErrors(['unit_price']);

        expect($item->fresh()->unit_price)->not->toBe(2_000_000_000);
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

});

describe('sections (modal create)', function () {
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
        // Alpine closes x-signals.modal via onMutationDone → $dispatch('close-modal', name).
        // The server dispatches line-items-mutation-done with modal: create-section.
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
            ->set('newSectionName', 'Closes Cleanly')
            ->call('createSection')
            ->assertHasNoErrors()
            ->assertDispatched('line-items-mutation-done', modal: 'create-section')
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
            ->assertNotDispatched('line-items-mutation-done');
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

});

describe('sections (assignment and grouping)', function () {
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

});

describe('merge duplicates', function () {
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

    it('blocks mergeDuplicates while pricing is frozen without mutating lines or pricing events', function () {
        Auth::login($this->owner);
        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Frozen merge fixture',
            'store_id' => $this->store->id,
            'starts_at' => '2026-12-01T09:00:00Z',
            'ends_at' => '2026-12-05T17:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
        $product = Product::factory()->create(['name' => 'Frozen merge product']);

        $this->actingAs($this->owner);

        $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
            ->call('addProduct', $product->id, 2)
            ->call('addProduct', $product->id, 3);

        $lines = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('item_type', OpportunityItemType::Product)
            ->orderBy('id')
            ->get();

        expect($lines)->toHaveCount(2);

        (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
            'currency' => 'GBP',
            'deal_total' => 50000,
        ]));

        expect($opportunity->fresh()->pricingFrozen())->toBeTrue();

        $survivor = $lines->first();
        $survivorPrice = (int) $survivor->unit_price;
        $eventCount = fn (): int => VerbEvent::query()
            ->whereIn('type', [
                ItemRemoved::class,
                ItemPriceOverridden::class,
            ])
            ->count();

        $beforeEvents = $eventCount();

        $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
            ->call('mergeDuplicates', $survivor->id)
            ->assertHasErrors(['opportunity']);

        expect($eventCount())->toBe($beforeEvents)
            ->and(OpportunityItem::query()
                ->where('opportunity_id', $opportunity->id)
                ->where('item_type', OpportunityItemType::Product)
                ->count())->toBe(2)
            ->and((int) $survivor->fresh()->unit_price)->toBe($survivorPrice);
    });

});

describe('drag and sort (lines)', function () {
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

});

describe('accessories and charge footer', function () {
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

});

describe('optional lines', function () {
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

});

describe('product substitution', function () {
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

});

describe('sections (nested depth and drag)', function () {
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

});

describe('availability links', function () {
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

});

describe('toasts', function () {
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

});

describe('auto-group sections', function () {
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

    it('renders an auto-group section identically to a user section (draggable handle + menu)', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id);
        $group = ProductGroup::factory()->create(['name' => 'Cabling']);
        $product = Product::factory()->create(['name' => 'XLR', 'product_group_id' => $group->id]);

        $this->actingAs($this->owner);

        $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
            ->call('addProduct', $product->id, 1)
            ->assertHasNoErrors();

        $autoGroup = editorAutoGroup($opportunity, 'auto:'.$group->id)
            ?? throw new RuntimeException('Auto group not found');

        $tree = lineItemsEditorInstance($component)->serverTree()['tree'];
        $groupRow = collect($tree)->firstWhere('id', $autoGroup->id);

        expect($groupRow)->not->toBeNull()
            ->and($groupRow['name'])->toBe('Cabling')
            ->and($groupRow['item_type'])->toBe(OpportunityItemType::Group->value);
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

    it('cascade-deletes auto-group contents when its section is deleted', function () {
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

        $component->call('deleteSection', $autoGroup->id)->assertHasNoErrors();

        expect(OpportunityItem::query()->find($autoGroup->id))->toBeNull()
            ->and(OpportunityItem::query()->find($item->id))->toBeNull();
    });

});

describe('deal price and lock price', function () {
    it('shows the charge-total padlock only for lock price not deal price', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 8 UAT');

        (new ConvertToQuotation)($opportunity->fresh());
        (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
            'currency' => 'GBP',
            'deal_total' => 5000,
        ]));

        expect($opportunity->fresh()->pricingFrozen())->toBeTrue()
            ->and($opportunity->fresh()->hasLocks())->toBeFalse();

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
            ->assertOk()
            ->assertSeeHtml('x-show="priceLocked && canManagePriceLock"')
            ->assertDontSeeHtml('x-show="pricingFrozen && canManagePriceLock"');
    });

    it('rejects setting deal price while price is locked', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 8 UAT');

        (new ConvertToQuotation)($opportunity->fresh());
        (new LockOpportunity)($opportunity->fresh());

        (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
            'currency' => 'GBP',
            'deal_total' => 5000,
        ]));
    })->throws(ValidationException::class);

    it('rejects locking price while a deal price is active', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 8 UAT');

        (new ConvertToQuotation)($opportunity->fresh());
        (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
            'currency' => 'GBP',
            'deal_total' => 5000,
        ]));

        (new LockOpportunity)($opportunity->fresh());
    })->throws(ValidationException::class);

    it('denies the lock price action while a deal price is active', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 8 UAT');

        (new ConvertToQuotation)($opportunity->fresh());
        (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
            'currency' => 'GBP',
            'deal_total' => 5000,
        ]));

        $this->actingAs($this->owner);

        Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
            ->assertOk()
            ->assertSee('Clear the deal price before locking price.');
    });

    it('dispatches priceLocked separately from pricingFrozen when locking price', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 8 UAT');

        (new ConvertToQuotation)($opportunity->fresh());

        $this->actingAs($this->owner);

        Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
            ->call('openConfirmModal', 'unlock_locks')
            ->call('confirmTransition')
            ->assertHasNoErrors()
            ->assertDispatched('opportunity-lifecycle-changed', pricingFrozen: true, priceLocked: true, fieldsEditable: false);

        expect($opportunity->fresh()->hasLocks())->toBeTrue()
            ->and($opportunity->fresh()->deal_total)->toBeNull();
    });

    it('opens the lock price confirm modal when the editor dispatches the unlock event', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 6 UAT');

        (new ConvertToQuotation)($opportunity->fresh());

        $this->actingAs($this->owner);

        Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
            ->dispatch('open-opportunity-lock-price-modal')
            ->assertSet('pendingConfirmKey', 'unlock_locks');
    });
});

describe('pricing freeze', function () {
    it('treats FX/tax locks as a full pricing freeze in the editor', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 5 UAT');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Locked line',
            'quantity' => '1',
            'unit_price' => 2000,
        ]));

        $item = $opportunity->fresh(['items'])->items->first();

        (new ConvertToQuotation)($opportunity->fresh());
        (new LockOpportunity)($opportunity->fresh());

        expect($opportunity->fresh()->pricingFrozen())->toBeTrue();

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
            ->assertSet('fieldsEditable', false)
            ->call('updateField', $item->id, 'quantity', '3')
            ->assertHasErrors(['opportunity']);
    });

    it('zero-costs newly added products when rates are locked', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 5 UAT');
        $product = Product::factory()->create();

        (new ConvertToQuotation)($opportunity->fresh());
        (new LockOpportunity)($opportunity->fresh());

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
            ->call('addProduct', $product->id, 1);

        $line = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('itemable_id', $product->id)
            ->first();

        expect($line)->not->toBeNull()
            ->and((int) $line->unit_price)->toBe(0);
    });

    it('blocks removing lines while pricing is frozen', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 5 UAT');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Do not remove',
            'quantity' => '1',
            'unit_price' => 1000,
        ]));

        $item = $opportunity->fresh(['items'])->items->first();

        (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
            'currency' => 'GBP',
            'deal_total' => 5000,
        ]));

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
            ->call('removeItem', $item->id)
            ->assertHasErrors(['opportunity']);

        expect(OpportunityItem::query()->whereKey($item->id)->exists())->toBeTrue();
    });

    it('still blocks item removal while pricing is frozen on orders', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 6 UAT');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Frozen order line',
            'quantity' => '1',
            'unit_price' => 1200,
        ]));

        $item = $opportunity->fresh(['items'])->items->first();

        (new ConvertToQuotation)($opportunity->fresh());
        (new ConvertToOrder)($opportunity->fresh());

        expect($opportunity->fresh()->pricingFrozen())->toBeTrue();

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
            ->call('removeItem', $item->id)
            ->assertHasErrors(['opportunity']);

        expect(OpportunityItem::query()->whereKey($item->id)->exists())->toBeTrue();
    });
});

describe('inline add (text and section)', function () {
    it('creates inline text and section rows with empty names for immediate editing', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 6 UAT');

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
            ->call('addInlineTextLine')
            ->call('addInlineSection')
            ->assertHasNoErrors();

        $text = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('item_type', OpportunityItemType::Text)
            ->first();

        $group = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('item_type', OpportunityItemType::Group)
            ->first();

        expect($text)->not->toBeNull()
            ->and($text->name)->toBe('')
            ->and($group)->not->toBeNull()
            ->and($group->name)->toBe('');
    });

    it('removes blank inline text rows when the name field is saved empty', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 8 UAT');

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
            ->call('addInlineTextLine')
            ->assertHasNoErrors();

        $text = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('item_type', OpportunityItemType::Text)
            ->firstOrFail();

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
            ->call('updateField', $text->id, 'name', '')
            ->assertHasNoErrors();

        expect(OpportunityItem::query()->whereKey($text->id)->exists())->toBeFalse();
    });

    it('removes blank inline section rows when the name field is saved empty', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 8 UAT');

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
            ->call('addInlineSection')
            ->assertHasNoErrors();

        $group = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('item_type', OpportunityItemType::Group)
            ->firstOrFail();

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
            ->call('updateField', $group->id, 'name', '   ')
            ->assertHasNoErrors();

        expect(OpportunityItem::query()->whereKey($group->id)->exists())->toBeFalse();
    });

    it('exposes freshly added inline text rows in the editor tree for menu actions', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 14 menu + quick-add keyboard');

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
            ->call('addInlineTextLine')
            ->assertHasNoErrors();

        $item = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('item_type', OpportunityItemType::Text)
            ->firstOrFail();

        $editor = lineItemsEditorInstance(
            Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])])
        );

        $treeIds = collect($editor->serverTree()['tree'])->pluck('id')->all();

        expect($treeIds)->toContain($item->id);
    });
});

describe('description and warehouse notes', function () {
    it('persists description and warehouse notes via saveLineEdits', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 5 UAT');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Mic package',
            'quantity' => '1',
            'unit_price' => 1500,
        ]));

        $item = $opportunity->fresh(['items'])->items->first();

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
            ->call(
                'saveLineEdits',
                $item->id,
                null,
                null,
                null,
                null,
                'Client-facing blurb',
                'Pack spare batteries',
            )
            ->assertHasNoErrors()
            ->assertDispatched('line-items-mutation-done', modalId: 'edit-line');

        $fresh = $item->fresh();

        expect($fresh->description)->toBe('Client-facing blurb')
            ->and($fresh->notes)->toBe('Pack spare batteries');
    });

    it('preserves warehouse notes when only the description is updated via the action', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 5 UAT');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Mic package',
            'quantity' => '1',
            'unit_price' => 1500,
            'description' => 'Original blurb',
            'notes' => 'Keep this note',
        ]));

        $item = $opportunity->fresh(['items'])->items->first();

        (new UpdateOpportunityItemDetails)($item, UpdateOpportunityItemDetailsData::from([
            'description' => 'Updated blurb',
        ]));

        $fresh = $item->fresh();

        expect($fresh->description)->toBe('Updated blurb')
            ->and($fresh->notes)->toBe('Keep this note');
    });

    it('preserves warehouse notes when saveLineEdits receives only a description argument', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 5 UAT');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Mic package',
            'quantity' => '1',
            'unit_price' => 1500,
            'description' => 'Original blurb',
            'notes' => 'Keep this note',
        ]));

        $item = $opportunity->fresh(['items'])->items->first();

        $this->actingAs($this->owner);

        $editor = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()]);

        // Direct invocation with six args simulates a crafted partial wire call (Livewire
        // `->call()` always pads trailing defaults, which would clobber omitted fields).
        $instance = $editor->instance();
        assert(method_exists($instance, 'saveLineEdits'));
        $instance->saveLineEdits($item->id, null, null, null, null, 'Updated via modal');

        $fresh = $item->fresh();

        expect($fresh->description)->toBe('Updated via modal')
            ->and($fresh->notes)->toBe('Keep this note');
    });

    it('includes description and notes in the editor tree payload', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 5 UAT');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Line with notes',
            'quantity' => '1',
            'unit_price' => 1000,
            'description' => 'Shown under name',
            'notes' => 'Warehouse only',
        ]));

        $row = collect(
            app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh(['items']))
        )->first();

        expect($row['description'])->toBe('Shown under name')
            ->and($row['notes'])->toBe('Warehouse only');
    });

    it('still saves description and notes while pricing is frozen', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 5 UAT');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Frozen line',
            'quantity' => '1',
            'unit_price' => 1500,
        ]));

        $item = $opportunity->fresh(['items'])->items->first();

        (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
            'currency' => 'GBP',
            'deal_total' => 5000,
        ]));

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
            ->call(
                'saveLineEdits',
                $item->id,
                '99.00',
                '50',
                '2026-08-01',
                '2026-08-05',
                'Notes still save',
                'Warehouse note',
            )
            ->assertHasNoErrors();

        $fresh = $item->fresh();

        expect($fresh->description)->toBe('Notes still save')
            ->and($fresh->notes)->toBe('Warehouse note')
            ->and((int) $fresh->unit_price)->toBe(1500);
    });
});

describe('removing lines via Livewire (quotation and concurrency)', function () {
    it('persists item removal on an unlocked quotation', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 6 UAT');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Removable line',
            'quantity' => '1',
            'unit_price' => 2500,
        ]));

        $item = $opportunity->fresh(['items'])->items->first();

        (new ConvertToQuotation)($opportunity->fresh());

        expect($opportunity->fresh()->pricingFrozen())->toBeFalse();

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
            ->call('removeItem', $item->id)
            ->assertHasNoErrors();

        expect(OpportunityItem::query()->whereKey($item->id)->exists())->toBeFalse();
    });

    it('persists delete for a just-added inline text row via removeItem', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 9 delete persist');

        (new ConvertToQuotation)($opportunity->fresh());

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
            ->call('addInlineTextLine')
            ->assertHasNoErrors();

        $text = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->latest('id')
            ->firstOrFail();

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
            ->call('removeItem', $text->id)
            ->assertHasNoErrors();

        expect(OpportunityItem::query()->whereKey($text->id)->exists())->toBeFalse();
    });

    it('persists delete immediately after adding a priced line while another field update is in flight', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 9 delete persist');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Concurrent delete target',
            'quantity' => '2',
            'unit_price' => 1500,
        ]));

        $item = $opportunity->fresh(['items'])->items->first();

        (new ConvertToQuotation)($opportunity->fresh());

        $this->actingAs($this->owner);

        $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()]);

        $component->call('updateField', $item->id, 'quantity', '3');
        $component->call('removeItem', $item->id)->assertHasNoErrors();

        expect(OpportunityItem::query()->whereKey($item->id)->exists())->toBeFalse();
    });
});

describe('delete persistence (mutation flush and persistTree)', function () {
    it('defers delete flush for unresolved temp ids', function () {
        $result = runLineItemJs('tests/js/line-item-mutation-flush-runner.mjs', [
            'action' => 'resolveServerItemId',
            'id' => -9001,
            'rows' => [
                ['id' => -9001, 'name' => 'Pending section'],
            ],
        ]);

        expect($result['id'])->toBeNull();
    });

    it('resolves positive server ids for delete flush', function () {
        $result = runLineItemJs('tests/js/line-item-mutation-flush-runner.mjs', [
            'action' => 'resolveServerItemId',
            'id' => 42,
            'rows' => [
                ['id' => 42, 'name' => 'Existing line'],
            ],
        ]);

        expect($result['id'])->toBe(42);
    });

    it('excludes pending deletes from persistTree node sets', function () {
        $result = runLineItemJs('tests/js/line-item-mutation-flush-runner.mjs', [
            'action' => 'rowsEligibleForPersistTree',
            'rows' => [
                ['id' => 1, 'depth' => 1],
                ['id' => 2, 'depth' => 1],
                ['id' => 3, 'depth' => 1],
            ],
            'pendingDeleteIds' => [2],
        ]);

        expect($result['ids'])->toBe([1, 3]);
    });

    it('schedules a flush retry when a flush was blocked mid-flight', function () {
        $result = runLineItemJs('tests/js/line-item-mutation-flush-runner.mjs', [
            'action' => 'shouldScheduleFlushRetry',
            'wasBlocked' => false,
            'queueLength' => 0,
            'pendingFlushFlag' => true,
        ]);

        expect($result['schedule'])->toBeTrue();
    });

    it('orders persistTree after other queued mutations in a flush batch', function () {
        $result = runLineItemJs('tests/js/line-item-mutation-flush-runner.mjs', [
            'action' => 'orderFlushBatch',
            'batch' => [
                ['kind' => 'persistTree'],
                ['kind' => 'field', 'id' => 1, 'field' => 'name', 'value' => 'x'],
            ],
        ]);

        expect($result['kinds'])->toBe(['field', 'persistTree']);
    });

    it('persistTree via the editor prunes omitted nodes like the real menu delete flush path', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 10 menu delete persist');
        $seed = seedEditorGroupWithTwoItems($opportunity);

        (new ConvertToQuotation)($opportunity->fresh());

        $this->actingAs($this->owner);

        $editor = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()]);
        $instance = lineItemsEditorInstance($editor);

        $remaining = $seed['first'];
        $deleted = $seed['second'];

        $nodes = [
            ['id' => $seed['group']->id, 'depth' => 1],
            ['id' => $remaining->id, 'depth' => 2],
        ];

        $editor->call('persistTree', $nodes, $instance->treeRevision())->assertHasNoErrors();

        expect(OpportunityItem::query()->whereKey($deleted->id)->exists())->toBeFalse();

        $freshEditor = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()]);
        $serverIds = collect(lineItemsEditorInstance($freshEditor)->serverTree()['tree'])->pluck('id')->all();

        expect($serverIds)->toContain($remaining->id)
            ->and($serverIds)->not->toContain($deleted->id);
    });

    it('persistTree prune path removes a ghost row even when removeItem is never called', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 10 menu delete persist');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Keep',
            'quantity' => '1',
            'unit_price' => 1000,
        ]));

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Ghost target',
            'quantity' => '1',
            'unit_price' => 500,
        ]));

        $items = $opportunity->fresh(['items'])->items->sortBy('path')->values();
        $keep = $items->first();
        $ghost = $items->last();

        (new ConvertToQuotation)($opportunity->fresh());

        $this->actingAs($this->owner);

        $editor = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()]);
        $instance = lineItemsEditorInstance($editor);

        $editor->call('persistTree', [
            ['id' => $keep->id, 'depth' => 1],
        ], $instance->treeRevision())->assertHasNoErrors();

        expect(OpportunityItem::query()->whereKey($ghost->id)->exists())->toBeFalse();

        $serverIds = collect(lineItemsEditorInstance(
            Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
        )->serverTree()['tree'])->pluck('id')->all();

        expect($serverIds)->toBe([$keep->id]);
    });

    it('blocks persistTree orphan pruning while pricing is frozen', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Frozen persistTree prune');
        $seed = seedEditorGroupWithTwoItems($opportunity);

        (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
            'currency' => 'GBP',
            'deal_total' => 7500,
        ]));

        expect($opportunity->fresh()->pricingFrozen())->toBeTrue();

        $this->actingAs($this->owner);

        $editor = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()]);
        $instance = lineItemsEditorInstance($editor);

        $beforeEvents = VerbEvent::query()
            ->where('type', ItemRemoved::class)
            ->count();

        $nodes = [
            ['id' => $seed['group']->id, 'depth' => 1],
            ['id' => $seed['first']->id, 'depth' => 2],
        ];

        $editor->call('persistTree', $nodes, $instance->treeRevision())
            ->assertHasErrors(['opportunity']);

        expect(OpportunityItem::query()->whereKey($seed['second']->id)->exists())->toBeTrue()
            ->and(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(3)
            ->and(VerbEvent::query()
                ->where('type', ItemRemoved::class)
                ->count())->toBe($beforeEvents);
    });

    it('allows persistTree reorder-only while pricing is frozen', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Frozen persistTree reorder');
        $seed = seedEditorGroupWithTwoItems($opportunity);

        (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
            'currency' => 'GBP',
            'deal_total' => 7500,
        ]));

        expect($opportunity->fresh()->pricingFrozen())->toBeTrue();

        $this->actingAs($this->owner);

        $editor = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()]);
        $instance = lineItemsEditorInstance($editor);

        $nodes = [
            ['id' => $seed['group']->id, 'depth' => 1],
            ['id' => $seed['second']->id, 'depth' => 2],
            ['id' => $seed['first']->id, 'depth' => 2],
        ];

        $editor->call('persistTree', $nodes, $instance->treeRevision())->assertHasNoErrors();

        expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(3);

        $serverIds = collect(lineItemsEditorInstance(
            Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
        )->serverTree()['tree'])->pluck('id')->all();

        expect($serverIds)->toBe([
            $seed['group']->id,
            $seed['second']->id,
            $seed['first']->id,
        ]);
    });
});

describe('delete persistence (keepalive web endpoint)', function () {
    it('optimistically removes rows and uses keepalive fetch instead of awaited Livewire delete', function () {
        $result = runLineItemJs('tests/js/line-item-sync-delete-runner.mjs', [
            'action' => 'simulateOptimisticKeepaliveDeleteDuringFlush',
            'deleteId' => 73,
            'iterations' => 1,
        ]);

        expect($result['markedRemoving'])->toBeTrue()
            ->and($result['stillPresentAfterDeleteCall'])->toBeTrue()
            ->and($result['instantRemoval'])->toBeTrue()
            ->and($result['keepaliveDeleteCalled'])->toBeTrue()
            ->and($result['keepaliveUsed'])->toBeTrue()
            ->and($result['removeItemCalled'])->toBeFalse()
            ->and($result['serverHasDeletedId'])->toBeFalse()
            ->and($result['rowsHaveDeletedId'])->toBeFalse();
    });

    it('keeps optimistic keepalive delete reliable while a drag flush is in-flight', function () {
        $result = runLineItemJs('tests/js/line-item-sync-delete-runner.mjs', [
            'action' => 'simulateOptimisticKeepaliveDeleteDuringFlush',
            'deleteId' => 73,
            'iterations' => 10,
        ]);

        expect($result['passes'])->toBe(10)
            ->and($result['iterations'])->toBe(10);
    });

    it('persists a free-text line delete via the keepalive web endpoint immediately', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 13 keepalive delete');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Free text delete target',
            'quantity' => '1',
            'unit_price' => 2500,
        ]));

        $item = $opportunity->fresh(['items'])->items->first();

        $this->actingAs($this->owner);

        $this->deleteJson(route('opportunities.items.destroy', [$opportunity, $item]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        expect(OpportunityItem::query()->whereKey($item->id)->exists())->toBeFalse();
    });

    it('persists a product line delete via the keepalive web endpoint immediately', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 13 keepalive delete');
        $product = Product::factory()->create();

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
        ]));

        $item = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('itemable_id', $product->id)
            ->firstOrFail();

        $this->actingAs($this->owner);

        $this->deleteJson(route('opportunities.items.destroy', [$opportunity, $item]))
            ->assertOk();

        expect(OpportunityItem::query()->whereKey($item->id)->exists())->toBeFalse();
    });

    it('cascade-deletes a section via scope=section including nested products, text, and subsections', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 13 keepalive delete');

        (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Lighting']));
        $parentGroup = $opportunity->fresh(['items'])->items
            ->first(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group);

        (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
            'name' => 'Nested fixture',
            'quantity' => '1',
            'unit_price' => 1000,
            'parent_path' => $parentGroup->path,
        ]));

        (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
            'name' => 'Section note',
            'item_type' => OpportunityItemType::Text->value,
            'quantity' => '1',
            'parent_path' => $parentGroup->path,
        ]));

        (new AddOpportunityGroup)($opportunity->fresh(), AddOpportunityGroupData::from([
            'name' => 'Nested section',
            'parent_path' => $parentGroup->path,
        ]));

        $nestedGroup = $opportunity->fresh(['items'])->items
            ->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group
                && $item->id !== $parentGroup->id)
            ->firstOrFail();

        (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
            'name' => 'Deep lamp',
            'quantity' => '1',
            'unit_price' => 500,
            'parent_path' => $nestedGroup->path,
        ]));

        (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
            'name' => 'Root spare',
            'quantity' => '1',
            'unit_price' => 500,
        ]));

        $items = $opportunity->fresh(['items'])->items;
        $nestedProduct = $items->firstOrFail(fn (OpportunityItem $item): bool => $item->name === 'Nested fixture');
        $textLine = $items->firstOrFail(fn (OpportunityItem $item): bool => $item->name === 'Section note');
        $deepProduct = $items->firstOrFail(fn (OpportunityItem $item): bool => $item->name === 'Deep lamp');
        $rootSpare = $items->firstOrFail(fn (OpportunityItem $item): bool => $item->name === 'Root spare');

        $this->actingAs($this->owner);

        $this->deleteJson(route('opportunities.items.destroy', [
            'opportunity' => $opportunity,
            'item' => $parentGroup,
            'scope' => 'section',
        ]))->assertOk();

        expect(OpportunityItem::query()->find($parentGroup->id))->toBeNull()
            ->and(OpportunityItem::query()->find($nestedGroup->id))->toBeNull()
            ->and(OpportunityItem::query()->find($nestedProduct->id))->toBeNull()
            ->and(OpportunityItem::query()->find($textLine->id))->toBeNull()
            ->and(OpportunityItem::query()->find($deepProduct->id))->toBeNull()
            ->and(OpportunityItem::query()->whereKey($rootSpare->id)->exists())->toBeTrue();
    });

    it('returns 404 for keepalive delete when the item belongs to a different opportunity', function () {
        $targetOpportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 13 IDOR target');
        $otherOpportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 13 IDOR other');

        (new AddOpportunityItem)($otherOpportunity, AddOpportunityItemData::from([
            'name' => 'Foreign line',
            'quantity' => '1',
            'unit_price' => 1000,
        ]));

        $foreignItem = $otherOpportunity->fresh(['items'])->items->first();

        (new SetDealPrice)($targetOpportunity->fresh(), SetDealPriceData::from([
            'currency' => 'GBP',
            'deal_total' => '50.00',
        ]));

        $this->actingAs($this->owner);

        $this->deleteJson(route('opportunities.items.destroy', [$targetOpportunity->fresh(), $foreignItem]))
            ->assertNotFound();

        expect(OpportunityItem::query()->whereKey($foreignItem->id)->exists())->toBeTrue();
    });

    it('rejects keepalive delete when pricing is frozen', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 13 keepalive delete');

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Frozen delete target',
            'quantity' => '1',
            'unit_price' => 1000,
        ]));

        $item = $opportunity->fresh(['items'])->items->first();

        (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
            'currency' => 'GBP',
            'deal_total' => '50.00',
        ]));

        $this->actingAs($this->owner);

        $this->deleteJson(route('opportunities.items.destroy', [$opportunity->fresh(), $item]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['opportunity']);

        expect(OpportunityItem::query()->whereKey($item->id)->exists())->toBeTrue();
    });
});

describe('delete client totals recalc (JS)', function () {
    it('recomputes footer, group subtotal, and sidebar dispatch totals immediately after delete splice (#371)', function () {
        $result = runLineItemJs('tests/js/line-item-sync-delete-runner.mjs', [
            'action' => 'simulateDeleteTotalsRecalc',
            'deleteId' => 72,
        ]);

        expect($result['footerBefore'])->toBe(7500)
            ->and($result['footerAfter'])->toBe(2500)
            ->and($result['groupSubtotalBefore'])->toBe(5000)
            ->and($result['groupSubtotalAfter'])->toBe(0)
            ->and($result['serverChargeTotalMinor'])->toBe(2500)
            ->and($result['totalsDispatchCount'])->toBe(1)
            ->and($result['lastTotalsDispatch']['chargeTotalMinor'])->toBe(2500);
    });

    it('splices an entire section block locally and recalculates totals (#372)', function () {
        $result = runLineItemJs('tests/js/line-item-sync-delete-runner.mjs', [
            'action' => 'simulateDeleteSectionCascadeTotals',
            'sectionId' => 10,
        ]);

        expect($result['footerBefore'])->toBe(9000)
            ->and($result['footerAfter'])->toBe(2500)
            ->and($result['rowsRemaining'])->toBe(1)
            ->and($result['stillPresent'])->toBe([])
            ->and($result['lastTotalsDispatch']['chargeTotalMinor'])->toBe(2500);
    });
});

describe('row menu and quick-add keyboard (JS)', function () {
    it('opens the row menu immediately for a temp-id optimistic row', function () {
        $result = runLineItemJs('tests/js/line-item-row-menu-runner.mjs', [
            'action' => 'simulateNewRowMenuOpen',
            'itemType' => 'text',
        ]);

        expect($result['menuOpen'])->toBeTrue()
            ->and($result['menuRowResolved'])->toBeTrue()
            ->and($result['hasRemoveAction'])->toBeTrue()
            ->and($result['sameRowIdWorks'])->toBeTrue();
    });

    it('removes a temp-id row from the menu without waiting for refresh', function () {
        $result = runLineItemJs('tests/js/line-item-row-menu-runner.mjs', [
            'action' => 'simulateMenuDeleteOnTempRow',
        ]);

        expect($result['markedRemoving'])->toBeTrue()
            ->and($result['rowRemoved'])->toBeTrue()
            ->and($result['menuClosed'])->toBeTrue();
    });

    it('normalizes inline-added server rows so menu drag and edit lookups resolve immediately', function () {
        $result = runLineItemJs('tests/js/line-item-row-menu-runner.mjs', [
            'action' => 'simulateInlineRowInteractivity',
        ]);

        expect($result['rowIdNormalized'])->toBeTrue()
            ->and($result['menuOpen'])->toBeTrue()
            ->and($result['menuRowResolved'])->toBeTrue()
            ->and($result['dragIndex'])->toBe(0)
            ->and($result['editRowResolved'])->toBeTrue();
    });

    it('refocuses and clears the quick-add search box after qty Enter commits an add', function () {
        $result = runLineItemJs('tests/js/line-item-quick-add-runner.mjs', [
            'action' => 'simulateQuickAddKeyboardRefocus',
        ]);

        expect($result['wireQuickAddCalled'])->toBeTrue()
            ->and($result['quickAddQueryCleared'])->toBeTrue()
            ->and($result['qtyReset'])->toBeTrue()
            ->and($result['refocusCalled'])->toBeTrue();
    });
});

describe('quick-add picker', function () {
    it('exposes freshly quick-added server rows in the editor tree for menu actions', function () {
        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 14 menu + quick-add keyboard');
        $product = Product::factory()->create();

        $this->actingAs($this->owner);

        Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
            ->call('quickAdd', $product->id, 1)
            ->assertHasNoErrors();

        $item = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('itemable_id', $product->id)
            ->firstOrFail();

        $editor = lineItemsEditorInstance(
            Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])])
        );

        $treeIds = collect($editor->serverTree()['tree'])->pluck('id')->all();

        expect($treeIds)->toContain($item->id);
    });

    it('treats an empty quick-add qty as one and resets to empty after commit (#373)', function () {
        $result = runLineItemJs('tests/js/line-item-quick-add-runner.mjs', [
            'action' => 'simulateQuickAddEmptyQtyDefault',
        ]);

        expect($result['qtyUsed'])->toBe(1)
            ->and($result['qtyAfterReset'])->toBe('');
    });

    it('dedupes picker hits when local and server ids differ only by type (#374)', function () {
        $result = runLineItemJs('tests/js/opportunity-product-search-merge-runner.mjs', [
            'action' => 'simulateMergeDedupesMixedIdTypes',
        ]);

        expect($result['mergedCount'])->toBe(1)
            ->and($result['mergedIds'])->toBe([42])
            ->and($result['productHitIdLocal'])->toBe(42)
            ->and($result['productHitIdServer'])->toBe(42)
            ->and($result['availabilityMerged'])->toBe('available')
            ->and($result['imageMerged'])->toBe('https://example.test/thumb.jpg');
    });
});

describe('comma and money formatting (JS)', function () {
    it('formats section subtotals and footer totals with thousands separators (#375)', function () {
        $result = runLineItemJs('tests/js/line-item-money-breakdown-runner.mjs', [
            'action' => 'simulateMoneyThousandsSeparator',
        ]);

        expect($result['formatted'])->toBe('£1,234.56')
            ->and($result['groupSubtotal'])->toBe('£1,234.56')
            ->and($result['grandTotal'])->toBe('£1,234.56')
            ->and($result['dealSubline'])->toBe('Deal price applied — £98,765.43');
    });

    it('keeps comma formatting on per-row charge totals after inline qty or price edits (#375)', function () {
        $result = runLineItemJs('tests/js/line-item-money-breakdown-runner.mjs', [
            'action' => 'simulateRowChargeTotalKeepsCommasOnInlineEdit',
        ]);

        expect($result['afterQty'])->toBe('£1,851.84')
            ->and($result['afterPrice'])->toBe('£3,000.00')
            ->and($result['unitPriceDisplay'])->toBe('£1,000.00');
    });
});

describe('charge breakdown refresh (JS)', function () {
    it('refreshes charge_breakdown when inline price, days, or discount edits (#376)', function () {
        $result = runLineItemJs('tests/js/line-item-money-breakdown-runner.mjs', [
            'action' => 'simulateChargeBreakdownRefreshOnInlineEdit',
        ]);

        expect($result['chargeTotalDisplay'])->toBe('£225.00')
            ->and($result['afterPrice']['days_line'])->toBe('Days: £25.00 × 3')
            ->and($result['afterPrice']['rental_charge_display'])->toBe('£150.00')
            ->and($result['afterDays']['days_line'])->toBe('Days: £25.00 × 5')
            ->and($result['afterDays']['rental_charge_display'])->toBe('£250.00')
            ->and($result['afterDiscount']['rental_charge_display'])->toBe('£250.00');
    });
});

describe('activities tab', function () {
    it('registers and lists CRM activities regarding the opportunity', function () {
        expect(Route::has('opportunities.activities'))->toBeTrue();

        $opportunity = liveOpportunityForEditor($this->owner, $this->store->id, 'Round 6 UAT');

        Activity::factory()->forOpportunity($opportunity)->create([
            'subject' => 'Follow-up call with client',
        ]);

        Activity::factory()->forProduct(Product::factory()->create())->create();

        expect(Activity::forOpportunity($opportunity->id)->count())->toBe(1)
            ->and($opportunity->fresh()->loadCount('activities')->activities_count)->toBe(1);

        $this->actingAs($this->owner);

        Volt::test('opportunities.activities', ['opportunity' => $opportunity])
            ->assertOk()
            ->assertSee('New Activity')
            ->assertSeeHtml('regarding_type=Opportunity')
            ->assertSeeHtml('regarding_id='.$opportunity->id);
    });
});
