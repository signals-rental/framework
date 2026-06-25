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

function mutationRefreshOpportunity(): Opportunity
{
    Auth::login(test()->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Mutation refresh',
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('renameItem persists to the database and dispatches mutation refresh for the Alpine table', function () {
    $opportunity = mutationRefreshOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Audio']));

    $group = $opportunity->fresh(['items'])->items
        ->first(fn ($item): bool => $item->item_type === OpportunityItemType::Group);

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])])
        ->call('renameItem', $group->id, 'Renamed Audio')
        ->assertHasNoErrors()
        ->assertDispatched('line-items-mutation-done', modalId: 'rename-section');

    expect(OpportunityItem::query()->findOrFail($group->id)->name)->toBe('Renamed Audio');

    $tree = lineItemsEditorInstance(
        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])])
    )->serverTree()['tree'];

    expect(collect($tree)->firstWhere('id', $group->id)['name'])->toBe('Renamed Audio');
});

it('createSection dispatches mutation refresh with modal name for Alpine close', function () {
    $opportunity = mutationRefreshOpportunity();

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newSectionName', 'Lighting')
        ->call('createSection')
        ->assertHasNoErrors()
        ->assertDispatched('line-items-mutation-done', modal: 'create-section')
        ->assertDispatched('toast', type: 'success', message: 'Section created');
});

it('saveLineEdits persists field changes and dispatches mutation refresh', function () {
    $opportunity = mutationRefreshOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Mic',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $item = $opportunity->fresh(['items'])->items->first();

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])])
        ->call('saveLineEdits', $item->id, '12.50', '10', '2026-07-02', '2026-07-08')
        ->assertHasNoErrors()
        ->assertDispatched('line-items-mutation-done', modalId: 'edit-line');

    $fresh = OpportunityItem::query()->findOrFail($item->id);

    expect($fresh->formatMoneyCost('unit_price'))->toBe('12.50')
        ->and((string) $fresh->discount_percent)->toBe('10.00')
        ->and($fresh->starts_at?->toDateString())->toBe('2026-07-02')
        ->and($fresh->ends_at?->toDateString())->toBe('2026-07-08');
});

it('updateField accepts days and shifts the line hire window', function () {
    $opportunity = mutationRefreshOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Speaker',
        'quantity' => '1',
        'unit_price' => 2000,
    ]));

    $item = $opportunity->fresh(['items'])->items->first();

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])])
        ->call('updateField', $item->id, 'days', 4)
        ->assertHasNoErrors();

    $fresh = OpportunityItem::query()->findOrFail($item->id);

    expect($fresh->starts_at?->toDateString())->not->toBeNull()
        ->and($fresh->ends_at?->toDateString())->not->toBeNull()
        ->and(max(1, (int) $fresh->starts_at->diffInDays($fresh->ends_at)))->toBe(4);
});

it('exposes product_url and charge_breakdown on product-backed tree rows', function () {
    $opportunity = mutationRefreshOpportunity();
    $product = Product::factory()->rental()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => 'product',
        'quantity' => '2',
        'unit_price' => 1500,
    ]));

    $item = $opportunity->fresh(['items'])->items->first();

    $row = collect(
        lineItemsEditorInstance(
            Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])])
        )->serverTree()['tree']
    )->firstWhere('id', $item->id);

    expect($row['product_url'])->toContain('/products/'.$product->id)
        ->and($row['charge_breakdown'])->toBeArray()
        ->and($row['charge_breakdown']['days_line'])->toContain('Days:')
        ->and($row['charge_breakdown']['rental_charge_display'])->not->toBeEmpty();
});

it('exposes product_url on product-backed accessory rows', function () {
    $opportunity = mutationRefreshOpportunity();
    $product = Product::factory()->rental()->create();
    $accessoryProduct = Product::factory()->rental()->create();

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

    $row = collect(
        lineItemsEditorInstance(
            Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])])
        )->serverTree()['tree']
    )->firstWhere('id', $accessory->id);

    expect($row['product_url'])->toContain('/products/'.$accessoryProduct->id);
});

it('mergeDuplicates dispatches mutation refresh and serverTree reflects the collapsed row', function () {
    $opportunity = mutationRefreshOpportunity();
    $product = Product::factory()->create(['name' => 'Duplicate Mic']);

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => 'product',
        'quantity' => '2',
        'unit_price' => 1000,
    ]));

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => 'product',
        'quantity' => '3',
        'unit_price' => 1000,
    ]));

    $lines = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Product)
        ->orderBy('id')
        ->get();

    expect($lines)->toHaveCount(2);

    $survivor = $lines->first();

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])])
        ->call('mergeDuplicates', $survivor->id)
        ->assertHasNoErrors()
        ->assertDispatched('line-items-mutation-done');

    $tree = lineItemsEditorInstance(
        Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])])
    )->serverTree()['tree'];

    $productRows = collect($tree)->where('item_type', 'product')->values();

    expect($productRows)->toHaveCount(1)
        ->and((float) $productRows->first()['quantity'])->toBe(5.0)
        ->and($productRows->first()['id'])->toBe($survivor->id);
});
