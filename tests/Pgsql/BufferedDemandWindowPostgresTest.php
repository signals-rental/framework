<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\OpportunityStatus;
use App\Enums\StockMethod;
use App\Jobs\RecalculateAvailabilityJob;
use App\Models\AvailabilityDailySummary;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL availability lane — buffered demand window
|--------------------------------------------------------------------------
|
| Proves the buffer/`period` fix against real Postgres: a demand whose buffered
| (turnaround) window extends past its raw ends_at must still occupy the
| turnaround slot across point, snapshot, daily-summary, and shortage-detector
| reads — the native `period &&` fetch and the buffered per-slot attribution
| agree. Skips cleanly when Postgres is unreachable.
|
| The DemandObserver dispatches a RecalculateAvailabilityJob on every demand
| write. The pgsql harness wraps each test in a transaction, so we Queue::fake()
| to RECORD that dispatch rather than run it inline — running it inline would
| nest a recalc inside the harness transaction (and, before the
| RecalculationPipeline transaction-level guard, take a lingering advisory lock
| that hangs the lane). Snapshot materialisation is driven explicitly via
| $this->pipeline->recalculate(...) where a test needs it; with the guard that
| call runs $work() directly inside the harness transaction (no advisory lock),
| so it completes.
|
| Run the lane:
|   php artisan test --compact --group=pgsql tests/Pgsql/BufferedDemandWindowPostgresTest.php
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    // Record the observer's recalc dispatch instead of running it inline; we
    // drive snapshot materialisation explicitly below.
    Queue::fake();

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->service = app(AvailabilityService::class);
    $this->pipeline = app(RecalculationPipeline::class);
});

it('counts a buffered demand as occupied in the turnaround slot across point, snapshot, and summary reads', function () {
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 2,
    ]);

    // Raw window Mon–Wed (ends 2026-04-15 00:00); buffered window extends one day
    // into Wed (ends 2026-04-16 00:00). On Postgres the `period` tstzrange carries
    // the buffered window.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->buffered(
            Carbon::parse('2026-04-13T00:00:00Z'),
            Carbon::parse('2026-04-15T00:00:00Z'),
            Carbon::parse('2026-04-13T00:00:00Z'),
            Carbon::parse('2026-04-16T00:00:00Z'),
        )
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 3,
        ]);

    // Point read on the Wed (turnaround) slot — past raw ends_at, inside period.
    $wed = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-15T06:00:00Z'));

    expect($wed->total_stock)->toBe(2)
        ->and($wed->total_demanded)->toBe(3)
        ->and($wed->available)->toBe(-1);

    // Snapshot/summary read for the same window.
    $result = $this->pipeline->recalculate(
        $product->id,
        $this->store->id,
        Carbon::parse('2026-04-13T00:00:00Z'),
        Carbon::parse('2026-04-17T00:00:00Z'),
    );

    expect($result->hasShortage)->toBeTrue();

    $range = $this->service->getAvailabilityRange(
        $product->id,
        $this->store->id,
        Carbon::parse('2026-04-13T00:00:00Z'),
        Carbon::parse('2026-04-17T00:00:00Z'),
    );

    $byDay = collect($range->slots)->keyBy(fn ($slot) => Carbon::parse($slot->slot_start)->toDateString());

    expect($byDay->get('2026-04-15')->available)->toBe(-1)
        ->and($byDay->get('2026-04-16')->available)->toBe(2);

    $wedSummary = AvailabilityDailySummary::query()
        ->forProductStore($product->id, $this->store->id)
        ->where('date', Carbon::parse('2026-04-15')->startOfDay())
        ->first();

    expect($wedSummary)->not->toBeNull()
        ->and($wedSummary->has_shortage)->toBeTrue();
});

it('sees a turnaround-buffered competing demand in availableForItem (shortage detector)', function () {
    $product = Product::factory()->create([
        'stock_method' => StockMethod::Bulk->value,
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 1440, // 1-day turnaround
        'track_availability' => true,
    ]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 3,
    ]);

    $competingOpp = Opportunity::factory()->create([
        'state' => OpportunityStatus::OrderActive->state()->value,
        'status' => OpportunityStatus::OrderActive->statusValue(),
        'store_id' => $this->store->id,
        'starts_at' => Carbon::parse('2026-04-13T00:00:00Z'),
        'ends_at' => Carbon::parse('2026-04-15T00:00:00Z'),
    ]);
    $competingItem = OpportunityItem::factory()->for($competingOpp)->create([
        'item_type' => Product::class,
        'item_id' => $product->id,
        'quantity' => 3,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    (new OpportunityItemDemandResolver)->syncDemands($competingItem);

    // The Wed slot is the competitor's turnaround window; a new item must see 0 free.
    $available = $this->service->availableForItem(
        $product->id,
        $this->store->id,
        Carbon::parse('2026-04-15T00:00:00Z'),
        Carbon::parse('2026-04-16T00:00:00Z'),
        'opportunity_item',
        999_999,
    );

    expect($available)->toBe(0);
});
