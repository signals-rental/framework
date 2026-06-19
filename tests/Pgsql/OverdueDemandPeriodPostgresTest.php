<?php

use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL availability lane — overdue demand period rebuild
|--------------------------------------------------------------------------
|
| Proves R2 FIX 1 against real Postgres: when DetectOverdueDemands extends an
| overdue, unreturned demand to the sentinel it must rebuild the `period`
| tstzrange so the native `period &&` fetch (and the GiST index) see the held-over
| unit occupying every future slot. Without the rebuild the recalculation would
| free the still-out unit for future bookings.
|
| The pgsql harness wraps each test in a transaction, so the RecalculationPipeline
| runs $work() directly (no advisory lock) — see the BufferedDemandWindow lane note.
|
| Run the lane:
|   php artisan test --compact --group=pgsql tests/Pgsql/OverdueDemandPeriodPostgresTest.php
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-06-18T12:00:00Z'));

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->service = app(AvailabilityService::class);
    $this->pipeline = app(RecalculationPipeline::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('rebuilds the period tstzrange so an overdue unit stays unavailable for future slots on Postgres', function () {
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 1,
    ]);

    // A buffered demand whose raw AND buffered ends are both before "now"
    // (2026-06-18) — overdue and unreturned.
    $demand = Demand::factory()
        ->phase(DemandPhase::Operational)
        ->buffered(
            Carbon::parse('2026-06-10T08:00:00Z'),
            Carbon::parse('2026-06-15T17:00:00Z'),
            Carbon::parse('2026-06-10T08:00:00Z'),
            Carbon::parse('2026-06-16T17:00:00Z'),
        )
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
        ]);

    $this->artisan('availability:detect-overdue-demands')->assertSuccessful();

    $demand->refresh();

    // buffered_ends_at moved to the sentinel.
    expect($demand->buffered_ends_at->equalTo(Demand::sentinel()))->toBeTrue();

    // The `period` tstzrange now extends to (at least) the sentinel — a future
    // slot a week out overlaps it.
    $future = Carbon::parse('2026-06-25T00:00:00Z');
    $overlaps = DB::connection('pgsql_testing')->selectOne(
        'SELECT (period && tstzrange(?, ?)) AS overlaps FROM demands WHERE id = ?',
        [$future->toIso8601String(), $future->copy()->addDay()->toIso8601String(), $demand->id],
    );

    expect((bool) $overlaps->overlaps)->toBeTrue();

    // A future point read sees the unit still occupied → 0 free.
    $point = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-06-25T12:00:00Z'));

    expect($point->total_stock)->toBe(1)
        ->and($point->total_demanded)->toBe(1)
        ->and($point->available)->toBe(0);

    // And the recalculation writes a future-slot snapshot of 0 free (driven via
    // the `period &&` fetch on Postgres).
    $result = $this->pipeline->recalculate(
        $product->id,
        $this->store->id,
        $future,
        $future->copy()->addDay(),
    );

    $range = $this->service->getAvailabilityRange(
        $product->id,
        $this->store->id,
        $future,
        $future->copy()->addDay(),
    );

    expect($result->hasShortage)->toBeFalse(); // exactly zero, not negative
    expect(collect($range->slots)->first()->available)->toBe(0);
});
