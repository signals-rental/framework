<?php

use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL availability lane — advisory lock timeout
|--------------------------------------------------------------------------
|
| Proves R2 FIX 2 against real Postgres: the RecalculationPipeline bounds its
| per-product/store advisory lock wait with `SET LOCAL lock_timeout`, read from
| the `availability.recalculation_lock_timeout_ms` setting, so a contended recalc
| can never block a worker indefinitely. On timeout (SQLSTATE 55P03) the pipeline
| skips the run rather than hanging or churning retries.
|
| The advisory-lock branch only runs at the TOP level (transactionLevel() === 0).
| The pgsql harness wraps each test in a transaction, so this test COMMITS the
| harness transaction first to reach the locked path, then cleans up its own rows
| (the harness rollback no longer covers them).
|
| Run the lane:
|   php artisan test --compact --group=pgsql tests/Pgsql/RecalculationLockTimeoutPostgresTest.php
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->pipeline = app(RecalculationPipeline::class);
});

it('issues SET LOCAL lock_timeout from the configured setting when locking at the top level', function () {
    settings()->set('availability.recalculation_lock_timeout_ms', 2500, 'integer');

    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 3,
    ]);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-09-01T00:00:00Z'), Carbon::parse('2026-09-03T00:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
        ]);

    $connection = DB::connection('pgsql_testing');

    // Reach the top-level advisory-locked path: commit the harness transaction.
    while ($connection->transactionLevel() > 0) {
        $connection->commit();
    }

    $connection->enableQueryLog();

    try {
        $this->pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-09-01T00:00:00Z'),
            Carbon::parse('2026-09-03T00:00:00Z'),
        );

        $queries = collect($connection->getQueryLog())->pluck('query');

        expect($queries->contains(fn (string $q): bool => str_contains($q, 'SET LOCAL lock_timeout = 2500')))->toBeTrue()
            ->and($queries->contains(fn (string $q): bool => str_contains($q, 'pg_advisory_xact_lock')))->toBeTrue();
    } finally {
        $connection->disableQueryLog();

        // The harness rollback no longer covers our committed rows — clean up.
        Demand::query()->where('product_id', $product->id)->delete();
        StockLevel::query()->where('product_id', $product->id)->delete();
        DB::connection('pgsql_testing')->table('availability_snapshots')->where('product_id', $product->id)->delete();
        DB::connection('pgsql_testing')->table('availability_daily_summaries')->where('product_id', $product->id)->delete();
        DB::connection('pgsql_testing')->table('availability_events')->where('product_id', $product->id)->delete();
        Product::query()->whereKey($product->id)->delete();
        Store::query()->whereKey($this->store->id)->delete();
        settings()->set('availability.recalculation_lock_timeout_ms', 5000, 'integer');

        // Re-open a transaction so the trait's tearDown rollback has one to close.
        $connection->beginTransaction();
    }
});

it('skips the run and does not throw when the advisory lock times out (SQLSTATE 55P03)', function () {
    settings()->set('availability.recalculation_lock_timeout_ms', 250, 'integer');

    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 3,
    ]);

    $connection = DB::connection('pgsql_testing');

    while ($connection->transactionLevel() > 0) {
        $connection->commit();
    }

    // Hold the SAME advisory lock key on a SEPARATE connection so the pipeline's
    // acquire blocks and times out. The key mirrors the pipeline's hashed key.
    $blocker = DB::connectUsing('pgsql_blocker', config('database.connections.pgsql_testing'));
    $blocker->beginTransaction();
    $blocker->statement(
        'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
        [$product->id.':'.$this->store->id],
    );

    try {
        // The pipeline should catch the lock_timeout, skip, and return — NOT throw.
        $result = $this->pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-09-01T00:00:00Z'),
            Carbon::parse('2026-09-03T00:00:00Z'),
        );

        expect($result->slots)->toBe(0);

        // Skipped: no snapshot was written under contention.
        $count = DB::connection('pgsql_testing')->table('availability_snapshots')
            ->where('product_id', $product->id)->count();

        expect($count)->toBe(0);
    } finally {
        $blocker->rollBack();
        DB::purge('pgsql_blocker');

        Demand::query()->where('product_id', $product->id)->delete();
        StockLevel::query()->where('product_id', $product->id)->delete();
        DB::connection('pgsql_testing')->table('availability_snapshots')->where('product_id', $product->id)->delete();
        DB::connection('pgsql_testing')->table('availability_events')->where('product_id', $product->id)->delete();
        Product::query()->whereKey($product->id)->delete();
        Store::query()->whereKey($this->store->id)->delete();
        settings()->set('availability.recalculation_lock_timeout_ms', 5000, 'integer');

        $connection->beginTransaction();
    }
});
