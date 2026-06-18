<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Enable the PostgreSQL `btree_gist` extension.
 *
 * `btree_gist` lets GiST indexes mix scalar columns (e.g. product_id, store_id)
 * with range columns inside a single index — the foundation for the Phase 3
 * availability engine's exclusion constraints over `tstzrange` demand windows.
 *
 * PostgreSQL only. On SQLite (the default test connection) this is a deliberate
 * no-op so the suite migrates cleanly; SQLite has no concept of extensions and
 * the availability exclusion constraints are exercised on a dedicated pgsql lane.
 *
 * `CREATE EXTENSION IF NOT EXISTS` is idempotent: re-running against a database
 * where the extension already exists is a safe no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
    }

    /**
     * Drop the extension on rollback (PostgreSQL only).
     *
     * Guarded with `IF EXISTS` so it is safe even if the extension was never
     * installed. No-op on SQLite, mirroring `up()`.
     */
    public function down(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement('DROP EXTENSION IF EXISTS btree_gist');
    }
};
