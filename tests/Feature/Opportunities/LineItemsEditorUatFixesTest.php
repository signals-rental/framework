<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\SetDealPrice;
use App\Data\Availability\OpportunityItemAvailabilityData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\SetDealPriceData;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\AvailabilityService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->actingAs($this->owner);
});

function uatOpportunity(): Opportunity
{
    Auth::login(test()->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'UAT fixes',
        'store_id' => test()->store->id,
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('persistTree reorders items in the database when base revision matches', function () {
    $opportunity = uatOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Line A',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Line B',
        'quantity' => '1',
        'unit_price' => 500,
    ]));

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);
    $revision = $editor->treeRevision();

    $items = $opportunity->fresh(['items'])->items->sortBy('path')->values();
    $beforePaths = $items->mapWithKeys(fn (OpportunityItem $item): array => [$item->id => $item->path])->all();
    $reordered = $items->reverse()->values();
    $nodes = $reordered->map(fn (OpportunityItem $item): array => [
        'id' => $item->id,
        'depth' => $item->depth(),
    ])->all();

    $result = $editor->persistTree($nodes, $revision);

    expect($result['stale'])->toBeFalse();

    foreach ($reordered->values() as $index => $item) {
        $path = OpportunityItem::query()->findOrFail($item->id)->path;
        expect($path)->not->toBe($beforePaths[$item->id]);
    }

    $serverIds = collect($editor->serverTree()['tree'])->pluck('id')->all();

    expect($serverIds)->toBe($reordered->pluck('id')->all());
});

it('exposes opportunity charge total via totalsSnapshot for client sync', function () {
    $opportunity = uatOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Priced line',
        'quantity' => '2',
        'unit_price' => 1500,
    ]));

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()]);
    $snap = lineItemsEditorInstance($component)->totalsSnapshot();

    expect($snap['charge_total'])->toBe((int) $opportunity->fresh()->charge_total)
        ->and($snap['has_deal_price'])->toBeFalse();
});

it('blocks line field edits while a deal price is active', function () {
    $opportunity = uatOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Locked line',
        'quantity' => '1',
        'unit_price' => 2000,
    ]));

    $item = $opportunity->fresh(['items'])->items->first();

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'currency' => 'GBP',
        'deal_total' => 5000,
    ]));

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
        ->call('updateField', $item->id, 'quantity', '3')
        ->assertHasErrors(['opportunity']);
});

it('zero-costs newly added products when a deal price is set', function () {
    $opportunity = uatOpportunity();
    $product = Product::factory()->create();

    (new SetDealPrice)($opportunity, SetDealPriceData::from([
        'currency' => 'GBP',
        'deal_total' => 9999,
    ]));

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()])
        ->call('addProduct', $product->id, 1);

    $line = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('itemable_id', $product->id)
        ->first();

    expect($line)->not->toBeNull()
        ->and((int) $line->unit_price)->toBe(0);
});

it('hides shortage tint and labels until the opportunity is order or reserved quotation', function () {
    $opportunity = uatOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Short line',
        'quantity' => '5',
        'unit_price' => 1000,
    ]));

    $item = $opportunity->fresh(['items'])->items->first();

    mock(AvailabilityService::class)
        ->shouldReceive('getOpportunityContext')
        ->andReturn(collect([
            OpportunityItemAvailabilityData::make(
                opportunityItemId: $item->id,
                productId: null,
                storeId: $opportunity->store_id,
                requestedQuantity: 5,
                availableForItem: 0,
                from: Carbon::parse('2026-07-01'),
                to: Carbon::parse('2026-07-05'),
            ),
        ]));

    $row = collect(
        lineItemsEditorInstance(Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]))
            ->serverTree()['tree']
    )->firstWhere('id', $item->id);

    expect($row['has_shortage'])->toBeFalse()
        ->and($row['status_label'])->not->toBe('Shortage');
});

it('shows shortage indicators after change-status moves quotation to reserved', function () {
    $opportunity = uatOpportunity();
    (new ConvertToQuotation)($opportunity);

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Short line',
        'quantity' => '5',
        'unit_price' => 1000,
    ]));

    $item = $opportunity->fresh(['items'])->items->first();

    mock(AvailabilityService::class)
        ->shouldReceive('getOpportunityContext')
        ->andReturn(collect([
            OpportunityItemAvailabilityData::make(
                opportunityItemId: $item->id,
                productId: null,
                storeId: $opportunity->store_id,
                requestedQuantity: 5,
                availableForItem: 0,
                from: Carbon::parse('2026-07-01'),
                to: Carbon::parse('2026-07-05'),
            ),
        ]));

    $beforeReserved = collect(
        lineItemsEditorInstance(Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]))
            ->serverTree()['tree']
    )->firstWhere('id', $item->id);

    expect($beforeReserved['has_shortage'])->toBeFalse();

    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->call('changeStatus', 1)
        ->assertHasNoErrors()
        ->assertDispatched('opportunity-lifecycle-changed');

    $afterReserved = collect(
        lineItemsEditorInstance(Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]))
            ->serverTree()['tree']
    )->firstWhere('id', $item->id);

    expect($afterReserved['has_shortage'])->toBeTrue()
        ->and($afterReserved['status_label'])->toBe('Shortage');
});

it('pullTree and serverTree agree on shortage visibility after a lifecycle change', function () {
    $opportunity = uatOpportunity();
    (new ConvertToQuotation)($opportunity);

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Short line',
        'quantity' => '5',
        'unit_price' => 1000,
    ]));

    $item = $opportunity->fresh(['items'])->items->first();

    mock(AvailabilityService::class)
        ->shouldReceive('getOpportunityContext')
        ->andReturn(collect([
            OpportunityItemAvailabilityData::make(
                opportunityItemId: $item->id,
                productId: null,
                storeId: $opportunity->store_id,
                requestedQuantity: 5,
                availableForItem: 0,
                from: Carbon::parse('2026-07-01'),
                to: Carbon::parse('2026-07-05'),
            ),
        ]));

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);

    (new ChangeOpportunityStatus)(
        $opportunity->fresh(),
        OpportunityStatus::QuotationReserved,
    );

    $pull = $editor->pullTree($editor->treeRevision(), [], []);
    $server = $editor->serverTree();

    $pullRow = collect($pull['tree'])->firstWhere('id', $item->id);
    $serverRow = collect($server['tree'])->firstWhere('id', $item->id);

    expect($pullRow['has_shortage'])->toBeTrue()
        ->and($serverRow['has_shortage'])->toBeTrue()
        ->and($pullRow['has_shortage'])->toBe($serverRow['has_shortage'])
        ->and($pull['cache_token'])->toBe(
            $opportunity->fresh()->state->value.':'.$opportunity->fresh()->status
        );
});
