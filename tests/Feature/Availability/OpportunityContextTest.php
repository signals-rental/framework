<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Availability\OpportunityItemAvailabilityData;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\OpportunityStatus;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->service = app(AvailabilityService::class);
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function contextOpportunity(Store $store): Opportunity
{
    return Opportunity::factory()->create([
        'state' => OpportunityStatus::OrderActive->state()->value,
        'status' => OpportunityStatus::OrderActive->statusValue(),
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-08-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-08-03T17:00:00Z'),
    ]);
}

it('returns an empty collection for an unknown opportunity', function () {
    expect($this->service->getOpportunityContext(999999))->toBeEmpty();
});

it('reports per-line availability and flags a shortage', function () {
    $product = Product::factory()->bulk()->create([
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);

    // A competing demand consumes 4 units over the line's window, leaving 1 free.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-08-01T09:00:00Z'), Carbon::parse('2026-08-03T17:00:00Z'))
        ->create(['product_id' => $product->id, 'store_id' => $this->store->id, 'quantity' => 4]);

    $opportunity = contextOpportunity($this->store);
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'item_type' => Product::class,
        'item_id' => $product->id,
        'quantity' => 3,
    ]);

    $context = $this->service->getOpportunityContext($opportunity->id);

    expect($context)->toHaveCount(1);

    /** @var OpportunityItemAvailabilityData $line */
    $line = $context->first();

    expect($line->opportunity_item_id)->toBe((int) $item->id)
        ->and($line->store_id)->toBe($this->store->id)
        ->and($line->requested_quantity)->toBe(3)
        ->and($line->available_for_item)->toBe(1)
        ->and($line->shortage_quantity)->toBe(2)
        ->and($line->has_shortage)->toBeTrue();
});

it('skips line items that reference no product', function () {
    $opportunity = contextOpportunity($this->store);
    OpportunityItem::factory()->for($opportunity)->create([
        'item_type' => null,
        'item_id' => null,
        'quantity' => 2,
    ]);

    expect($this->service->getOpportunityContext($opportunity->id))->toBeEmpty();
});

it('honours a line dispatch_store_id override over the opportunity store', function () {
    $override = Store::factory()->create(['timezone' => 'UTC']);

    $product = Product::factory()->bulk()->create([
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);
    // Stock only at the override store; none at the opportunity store.
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $override->id,
        'quantity_held' => 8,
    ]);

    $opportunity = contextOpportunity($this->store);
    OpportunityItem::factory()->for($opportunity)->create([
        'item_type' => Product::class,
        'item_id' => $product->id,
        'quantity' => 2,
        'dispatch_store_id' => $override->id,
    ]);

    $line = $this->service->getOpportunityContext($opportunity->id)->first();

    expect($line->store_id)->toBe($override->id)
        ->and($line->available_for_item)->toBe(8)
        ->and($line->has_shortage)->toBeFalse();
});
