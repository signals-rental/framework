<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The `btree_gist` extension is PostgreSQL-only. The migration is a deliberate
 * no-op on SQLite (the default test connection), so this assertion is gated to
 * pgsql and skips on the SQLite suite. It runs for real on the dedicated pgsql
 * CI lane (an M2 concern) and against the pgsql dev database.
 */
it('installs the btree_gist extension on PostgreSQL', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('btree_gist is PostgreSQL-only; skipped on '.DB::connection()->getDriverName().'.');
    }

    $installed = DB::connection()
        ->table('pg_extension')
        ->where('extname', 'btree_gist')
        ->exists();

    expect($installed)->toBeTrue();
});

it('migrates cleanly on SQLite without attempting to create the extension', function () {
    if (DB::connection()->getDriverName() === 'pgsql') {
        $this->markTestSkipped('This assertion targets the SQLite no-op path.');
    }

    // The migration ran during RefreshDatabase setup. Reaching here on SQLite
    // proves the no-op guard held and migration did not error.
    expect(Schema::hasTable('migrations'))->toBeTrue();
});
