<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityLineItemsTreeBuilder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function textLineOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Text line test',
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('adds a text line via AddOpportunityItem with zero charge and no demand side effects', function () {
    $opportunity = textLineOpportunity();

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Mic A',
        'quantity' => '1',
        'unit_price' => 2500,
    ]));

    $beforeCharge = (int) $opportunity->fresh()->charge_total;

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Client to supply power',
        'item_type' => OpportunityItemType::Text->value,
        'quantity' => '0',
    ]));

    $textLine = $opportunity->fresh(['items'])->items
        ->firstWhere('item_type', OpportunityItemType::Text);

    expect($textLine)->not->toBeNull()
        ->and($textLine->name)->toBe('Client to supply power')
        ->and((int) $textLine->total)->toBe(0)
        ->and($textLine->itemable_id)->toBeNull()
        ->and((int) $opportunity->fresh()->charge_total)->toBe($beforeCharge);
});

it('exposes text lines in the editor tree without pricing or availability metadata', function () {
    $opportunity = textLineOpportunity();

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Setup note',
        'item_type' => OpportunityItemType::Text->value,
        'quantity' => '0',
    ]));

    $tree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh(['items']));

    $row = collect($tree)->first(fn (array $node): bool => ($node['item_type'] ?? null) === 'text');

    expect($row)->not->toBeNull()
        ->and($row['name'])->toBe('Setup note')
        ->and($row['type_label'])->toBeNull()
        ->and($row['status_label'])->toBeNull()
        ->and($row['charge_breakdown'])->toBeNull()
        ->and($row['charge_total'])->toBe(0);
});

it('adds a text line through the line-items editor component', function () {
    $opportunity = textLineOpportunity();

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
        ->set('newTextLineName', 'Delivery instructions')
        ->call('addTextLine')
        ->assertHasNoErrors();

    $line = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Text)
        ->first();

    expect($line)->not->toBeNull()
        ->and($line->name)->toBe('Delivery instructions')
        ->and((int) $line->total)->toBe(0);
});
