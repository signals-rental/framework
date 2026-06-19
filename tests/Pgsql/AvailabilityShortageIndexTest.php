<?php

use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL availability lane — shortage partial index
|--------------------------------------------------------------------------
|
| The `idx_snapshots_shortage` partial index over
| `availability_snapshots (store_id, slot_start) WHERE available < 0` is
| PostgreSQL-only (no-op on SQLite), so it can only be asserted on the pgsql
| lane. Skips cleanly when Postgres is unreachable.
|
| Run the lane:
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

it('creates the partial shortage index on availability_snapshots', function () {
    $index = DB::selectOne(<<<'SQL'
        SELECT indexdef
        FROM pg_indexes
        WHERE tablename = 'availability_snapshots'
          AND indexname = 'idx_snapshots_shortage'
    SQL);

    expect($index)->not->toBeNull()
        ->and($index->indexdef)->toContain('available < 0')
        ->and($index->indexdef)->toContain('store_id')
        ->and($index->indexdef)->toContain('slot_start');
});
