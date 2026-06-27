<?php

use App\Contracts\Availability\AvailabilityStrategyContract;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL availability lane — non-lock-timeout error re-throw
|--------------------------------------------------------------------------
|
| The advisory-locked recalc transaction catches a lock_timeout (SQLSTATE 55P03)
| and SKIPS the run, but any OTHER QueryException must propagate so a genuine DB
| fault is not silently swallowed (RecalculationPipeline line 247). This drives a
| non-lock-timeout QueryException out of the per-slot work and asserts it
| re-throws rather than being treated as a skip.
|
| The advisory-lock branch only runs at the TOP level (transactionLevel() === 0),
| so this test COMMITS the harness transaction first, then cleans up its own rows.
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

it('re-throws a non-lock-timeout QueryException from the locked recalc transaction', function () {
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 3,
    ]);

    // A strategy that raises a non-lock-timeout QueryException (a syntactically
    // invalid statement → SQLSTATE 42601) from inside the locked work closure.
    app()->bind(AvailabilityStrategyContract::class, fn () => new class implements AvailabilityStrategyContract
    {
        public function preCalculation(int $productId, int $storeId, Carbon $rangeStart, Carbon $rangeEnd, Collection $demands): Collection
        {
            DB::connection('pgsql_testing')->statement('THIS IS NOT VALID SQL');

            return $demands;
        }

        public function postCalculation(int $productId, int $storeId, Carbon $rangeStart, Carbon $rangeEnd, Collection $slotResults): Collection
        {
            return $slotResults;
        }
    });

    $pipeline = app(RecalculationPipeline::class);
    $connection = DB::connection('pgsql_testing');

    // Reach the top-level advisory-lock branch (transactionLevel() === 0).
    while ($connection->transactionLevel() > 0) {
        $connection->commit();
    }

    try {
        expect(fn () => $pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-09-01T00:00:00Z'),
            Carbon::parse('2026-09-03T00:00:00Z'),
        ))->toThrow(QueryException::class);
    } finally {
        // The failed transaction already rolled back; clean our own rows since the
        // harness rollback no longer covers them.
        DB::connection('pgsql_testing')->table('availability_snapshots')->where('product_id', $product->id)->delete();
        DB::connection('pgsql_testing')->table('availability_events')->where('product_id', $product->id)->delete();
        StockLevel::query()->where('product_id', $product->id)->delete();
        Product::query()->whereKey($product->id)->delete();
        Store::query()->whereKey($this->store->id)->delete();

        $connection->beginTransaction();
    }
});
