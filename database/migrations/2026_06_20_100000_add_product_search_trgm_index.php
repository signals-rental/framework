<?php

use App\Services\Opportunities\ProductSearchService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Add a PostgreSQL `pg_trgm` trigram GIN index over `products.name` and
 * `products.sku`, backing the server tier of the opportunity line-item editor's
 * two-tier product search (fuzzy / typo-tolerant similarity ranking via the `%`
 * operator and `similarity()`).
 *
 * PostgreSQL only. On SQLite (the default test connection) this is a deliberate
 * no-op: SQLite has no extensions and the server search degrades to an `ilike`
 * substring/prefix rank (see {@see ProductSearchService}).
 * The trigram path is exercised on the dedicated `@group pgsql` lane.
 *
 * REVERSIBILITY (a hard project lesson): `down()` drops ONLY the index — it does
 * NOT drop the `pg_trgm` extension, because other features may rely on it. Both
 * up() and down() are guarded behind a `pgsql` driver check so the migration is a
 * clean no-op on SQLite and a `migrate` + `migrate:rollback` cycle reverses
 * cleanly on both drivers. `CREATE EXTENSION IF NOT EXISTS` and the indexes' own
 * `IF [NOT] EXISTS` guards make every statement idempotent.
 */
return new class extends Migration
{
    private const NAME_INDEX = 'products_name_trgm_idx';

    private const SKU_INDEX = 'products_sku_trgm_idx';

    public function up(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $connection->statement(sprintf(
            'CREATE INDEX IF NOT EXISTS %s ON products USING gin (name gin_trgm_ops)',
            self::NAME_INDEX,
        ));

        $connection->statement(sprintf(
            'CREATE INDEX IF NOT EXISTS %s ON products USING gin (sku gin_trgm_ops)',
            self::SKU_INDEX,
        ));
    }

    /**
     * Drop the trigram indexes on rollback (PostgreSQL only). The `pg_trgm`
     * extension is intentionally LEFT INSTALLED — other features may use it.
     * `IF EXISTS` keeps this safe even if the indexes were never created.
     */
    public function down(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement(sprintf('DROP INDEX IF EXISTS %s', self::SKU_INDEX));
        $connection->statement(sprintf('DROP INDEX IF EXISTS %s', self::NAME_INDEX));
    }
};
