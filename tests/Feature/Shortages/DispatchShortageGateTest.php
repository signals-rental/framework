<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchBulkQuantity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\DemandPhase;
use App\Enums\ShortageDispatchPolicy;
use App\Enums\ShortagePolicy;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\DispatchShortageGate;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * Build an ORDER whose single bulk line is short under the given dispatch policy:
 * 5 units held, 4 committed elsewhere over the window, this line wants 3 — so
 * only 1 is free and the line is short by 2 at dispatch time. The confirmation
 * gate is set to Allow so the order can be created short.
 *
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function shortOrderForDispatch(Store $store, ShortageDispatchPolicy $dispatchPolicy): array
{
    $store->update([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_dispatch_policy' => $dispatchPolicy->value,
    ]);

    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-09-01T09:00:00Z'), Carbon::parse('2026-09-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => 980001,
            'metadata' => [],
        ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Dispatch gate slice',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '3',
    ]));
    (new ConvertToQuotation)($opportunity->fresh());
    (new ConvertToOrder)($opportunity->fresh());

    return [$opportunity->fresh(), $opportunity->items()->firstOrFail()];
}

it('blocks a bulk dispatch under a Block store policy and rolls back', function () {
    [, $item] = shortOrderForDispatch($this->store, ShortageDispatchPolicy::Block);

    expect(fn () => (new DispatchBulkQuantity)($item->fresh(), BulkDispatchData::from(['quantity' => '1'])))
        ->toThrow(ValidationException::class);

    // Atomic: nothing dispatched.
    expect((float) $item->fresh()->dispatched_quantity)->toBe(0.0);
});

it('exposes the dispatch_block code on a Block denial', function () {
    [, $item] = shortOrderForDispatch($this->store, ShortageDispatchPolicy::Block);

    try {
        (new DispatchBulkQuantity)($item->fresh(), BulkDispatchData::from(['quantity' => '1']));
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('code')
            ->and($e->errors()['code'])->toContain(DispatchShortageGate::CODE);
    }
});

it('proceeds under a WarnPartial policy and surfaces held-item metadata', function () {
    [, $item] = shortOrderForDispatch($this->store, ShortageDispatchPolicy::WarnPartial);

    $action = new DispatchBulkQuantity;
    $action($item->fresh(), BulkDispatchData::from(['quantity' => '1']));

    expect((float) $item->fresh()->dispatched_quantity)->toBe(1.0)
        ->and($action->gateResult)->not->toBeNull()
        ->and($action->gateResult->warned())->toBeTrue();

    $meta = $action->gateResult->toHeldItemsMeta();
    expect($meta['dispatch_policy'])->toBe(ShortageDispatchPolicy::WarnPartial->value)
        ->and($meta['held_items'])->not->toBeEmpty();
});

it('proceeds silently under an AllowPartial policy with no held-item metadata', function () {
    [, $item] = shortOrderForDispatch($this->store, ShortageDispatchPolicy::AllowPartial);

    $action = new DispatchBulkQuantity;
    $action($item->fresh(), BulkDispatchData::from(['quantity' => '1']));

    expect((float) $item->fresh()->dispatched_quantity)->toBe(1.0)
        ->and($action->gateResult->warned())->toBeFalse()
        ->and($action->gateResult->toHeldItemsMeta())->toBe([]);
});

it('allows a non-short dispatch through any policy', function () {
    $this->store->update([
        'shortage_dispatch_policy' => ShortageDispatchPolicy::Block->value,
    ]);
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 50,
    ]);
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Plenty of stock',
        'store_id' => $this->store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '3',
    ]));
    (new ConvertToQuotation)($opportunity->fresh());
    (new ConvertToOrder)($opportunity->fresh());
    $item = $opportunity->items()->firstOrFail();

    $action = new DispatchBulkQuantity;
    $action($item->fresh(), BulkDispatchData::from(['quantity' => '2']));

    expect((float) $item->fresh()->dispatched_quantity)->toBe(2.0)
        ->and($action->gateResult->isShort())->toBeFalse();
});
