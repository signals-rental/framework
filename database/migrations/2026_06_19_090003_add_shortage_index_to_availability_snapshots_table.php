<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Partial shortage index on `availability_snapshots`
 * (availability-engine.md §"availability_snapshots" — `idx_snapshots_shortage`).
 *
 * Powers the store-wide shortage sweep — scanning a store/slot window for rows
 * whose `available` dipped below zero. A partial index over only the negative
 * rows keeps it tiny (shortages are the exception), so the proactive shortage
 * monitor reads it in milliseconds.
 *
 * PostgreSQL only: SQLite supports partial indexes but the suite never runs the
 * store-wide sweep against it, and keeping this aligned with the other
 * pgsql-guarded availability indexes avoids a divergent SQLite-only index. It is
 * a no-op on every other driver — correctness is unaffected (the index is a pure
 * read optimisation). Exercised on the `@group pgsql` lane.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE INDEX idx_snapshots_shortage
              ON availability_snapshots (store_id, slot_start)
              WHERE available < 0
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_snapshots_shortage');
    }
};
