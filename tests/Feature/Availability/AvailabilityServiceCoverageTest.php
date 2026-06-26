<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\AvailabilityDailySummary;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\SerialisedComponent;
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

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->service = app(AvailabilityService::class);
});

it('returns zero availability for an unknown product on point reads', function () {
    $result = $this->service->getAvailability(999999, $this->store->id, Carbon::parse('2026-04-01T00:00:00Z'));

    expect($result->available)->toBe(0)
        ->and($result->total_stock)->toBe(0);
});

it('returns false from checkAvailability when the product does not exist', function () {
    $ok = $this->service->checkAvailability(
        999999,
        $this->store->id,
        Carbon::parse('2026-04-01T00:00:00Z'),
        Carbon::parse('2026-04-03T00:00:00Z'),
        1,
    );

    expect($ok)->toBeFalse();
});

it('returns false from checkAssetAvailable when the stock level does not exist', function () {
    expect($this->service->checkAssetAvailable(
        999999,
        Carbon::parse('2026-04-01T00:00:00Z'),
        Carbon::parse('2026-04-03T00:00:00Z'),
    ))->toBeFalse();
});

it('reads availability ranges across multiple stores', function () {
    $other = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->bulk()->create();
    $slot = Carbon::parse('2026-09-01T00:00:00Z');

    AvailabilitySnapshot::factory()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'slot_start' => $slot,
        'available' => 4,
    ]);
    AvailabilitySnapshot::factory()->create([
        'product_id' => $product->id,
        'store_id' => $other->id,
        'slot_start' => $slot,
        'available' => 7,
    ]);

    $byStore = $this->service->getAvailabilityAcrossStores(
        $product->id,
        [$this->store->id, $other->id],
        $slot,
        $slot->copy()->addDay(),
    );

    expect($byStore->keys()->all())->toBe([$this->store->id, $other->id])
        ->and($byStore[$this->store->id]->slots[0]->available)->toBe(4)
        ->and($byStore[$other->id]->slots[0]->available)->toBe(7);
});

it('returns shortage rows from daily summaries for a store', function () {
    $product = Product::factory()->bulk()->create(['name' => 'Short Product']);
    $day = Carbon::parse('2026-07-03T00:00:00Z');

    AvailabilityDailySummary::factory()->day($day, -2, 0)->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    $shortages = $this->service->getShortages(
        $this->store->id,
        Carbon::parse('2026-07-01T00:00:00Z'),
        Carbon::parse('2026-07-05T00:00:00Z'),
    );

    expect($shortages)->toHaveCount(1)
        ->and($shortages->first()->product_name)->toBe('Short Product')
        ->and($shortages->first()->available)->toBe(-2);
});

it('builds a calendar grid from daily summaries', function () {
    $product = Product::factory()->bulk()->create(['name' => 'Calendar Product']);

    AvailabilityDailySummary::factory()->day(Carbon::parse('2026-07-01'), 6, 8)->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    $calendar = $this->service->getCalendar(
        $this->store->id,
        Carbon::parse('2026-07-01T00:00:00Z'),
        Carbon::parse('2026-07-02T00:00:00Z'),
        [$product->id],
    );

    expect($calendar)->toHaveCount(1)
        ->and($calendar->first()->product_name)->toBe('Calendar Product')
        ->and($calendar->first()->days[0]->available)->toBe(6);
});

it('returns per-product total stock for a store', function () {
    $a = Product::factory()->bulk()->create();
    $b = Product::factory()->serialised()->create();

    StockLevel::factory()->bulk()->create([
        'product_id' => $a->id,
        'store_id' => $this->store->id,
        'quantity_held' => 6,
    ]);
    StockLevel::factory()->serialised()->count(2)->create([
        'product_id' => $b->id,
        'store_id' => $this->store->id,
    ]);

    $totals = $this->service->productTotalStock([$a->id, $b->id], $this->store->id);

    expect($totals[$a->id])->toBe(6)
        ->and($totals[$b->id])->toBe(2);
});

it('reports fixed component satisfaction from container-held versus conflicting demands', function () {
    $component = Product::factory()->serialised()->create();
    $from = Carbon::parse('2026-08-01T00:00:00Z');
    $to = Carbon::parse('2026-08-03T00:00:00Z');

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window($from, $to)
        ->create([
            'product_id' => $component->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
            'source_type' => 'container',
            'source_id' => 12345,
        ]);

    expect($this->service->fixedComponentSatisfied($component->id, $this->store->id, $from, $to, 1))
        ->toBeTrue();

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window($from, $to)
        ->create([
            'product_id' => $component->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
            'source_type' => 'opportunity_item',
            'source_id' => 54321,
        ]);

    expect($this->service->fixedComponentSatisfied($component->id, $this->store->id, $from, $to, 1))
        ->toBeFalse();
});

it('builds gantt bars with opportunity source names and buffer-zone shortage flags', function () {
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 2,
    ]);

    $opportunity = Opportunity::factory()->create(['subject' => 'Gantt Opp']);
    $from = Carbon::parse('2026-08-10T00:00:00Z');
    $to = Carbon::parse('2026-08-12T00:00:00Z');

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-08-10T09:00:00Z'), Carbon::parse('2026-08-11T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
            'source_type' => 'opportunity_item',
            'source_id' => 111,
            'metadata' => ['opportunity_id' => $opportunity->id],
            'buffered_starts_at' => Carbon::parse('2026-08-10T07:00:00Z'),
            'buffered_ends_at' => Carbon::parse('2026-08-11T19:00:00Z'),
        ]);

    AvailabilityDailySummary::factory()->day(Carbon::parse('2026-08-10'), -1, 0)->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    $gantt = $this->service->getGantt($product->id, $this->store->id, $from, $to);

    expect($gantt->total_stock)->toBe(2)
        ->and($gantt->bars)->toHaveCount(1)
        ->and($gantt->bars[0]->source_name)->toBe('Gantt Opp')
        ->and($gantt->shortages)->toHaveCount(1)
        ->and($gantt->shortages[0]->in_buffer_zone)->toBeFalse();
});

it('routes composed kit products through the kit calculator on point reads', function () {
    $kit = Product::factory()->kit()->create();
    $component = Product::factory()->bulk()->create();
    SerialisedComponent::factory()->pool()->quantity(1)->create([
        'product_id' => $kit->id,
        'component_product_id' => $component->id,
    ]);

    $slot = Carbon::parse('2026-09-01T00:00:00Z');
    AvailabilitySnapshot::factory()->create([
        'product_id' => $component->id,
        'store_id' => $this->store->id,
        'slot_start' => $slot,
        'available' => 5,
    ]);

    $result = $this->service->getAvailability($kit->id, $this->store->id, $slot);

    expect($result->available)->toBe(5);
});

it('paginates available assets at the database layer', function () {
    $product = Product::factory()->serialised()->create();
    StockLevel::factory()->serialised()->count(3)->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    $page = $this->service->paginateAvailableAssets(
        $product->id,
        $this->store->id,
        Carbon::parse('2026-08-01T00:00:00Z'),
        Carbon::parse('2026-08-05T00:00:00Z'),
        perPage: 2,
        page: 1,
    );

    expect($page->total())->toBe(3)
        ->and($page->count())->toBe(2);
});
