<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The unified `demands` table — the source of truth for the availability engine.
 *
 * Every claim against stock, from any source, is one row here. Availability is
 * derived as stock minus the sum of active (Committed/Operational) demand
 * quantities overlapping a time window.
 *
 * This migration is driver-aware:
 *
 *  - On **PostgreSQL** the period is a native `tstzrange` with GiST indexes, a
 *    partial exclusion constraint preventing two active serialised demands for
 *    the same asset from overlapping in time, and CHECK constraints enforcing
 *    the serialised (quantity = 1) / bulk (quantity >= 1) invariants. These are
 *    the real guarantees, exercised on the `@group pgsql` test lane.
 *  - On **SQLite** (the default test suite) the table is created in a degraded
 *    form: `period` is omitted, only the plain `starts_at` / `ends_at`
 *    timestamps and B-tree indexes exist, and the range-overlap guarantees are
 *    not enforced. This lets the Demand model, registry, and resolver be
 *    exercised on SQLite while the tstzrange/exclusion guarantees are validated
 *    only against real Postgres.
 *
 * The `period` column always reflects the FULL unavailable window with product
 * buffers already baked in; `starts_at` / `ends_at` retain the original
 * pre-buffer dates for display/API. Indefinite demands use the sentinel end
 * date `2199-01-01T00:00:00Z` rather than NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            // Serialised assets only: the specific physical unit. Nullable for bulk.
            $table->foreignId('asset_id')->nullable()->constrained('stock_levels')->cascadeOnDelete();
            $table->integer('quantity');
            // `period` (tstzrange) is added below on PostgreSQL only.
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('source_type', 255);
            $table->unsignedBigInteger('source_id');
            $table->string('phase', 20);
            $table->boolean('is_active');
            $table->integer('priority')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            // Reverse lookup: all demands for a source entity, and phase filters.
            $table->index(['source_type', 'source_id'], 'idx_demands_source');
            $table->index('phase', 'idx_demands_phase');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            // SQLite degraded path: provide scalar indexes that approximate the
            // range query paths so the resolver/model can be exercised.
            Schema::table('demands', function (Blueprint $table): void {
                $table->index(['product_id', 'store_id', 'starts_at', 'ends_at'], 'idx_demands_product_store_window');
                $table->index(['asset_id', 'starts_at', 'ends_at'], 'idx_demands_asset_window');
            });

            return;
        }

        // --- PostgreSQL: native range column, GiST indexes, constraints. ---
        DB::statement('ALTER TABLE demands ADD COLUMN period TSTZRANGE NOT NULL');

        // Primary recalculation / availability query path (range overlap).
        DB::statement(<<<'SQL'
            CREATE INDEX idx_demands_product_store_period
              ON demands USING gist (product_id, store_id, period)
              WHERE is_active = true
        SQL);

        // Serialised asset range queries.
        DB::statement(<<<'SQL'
            CREATE INDEX idx_demands_asset_period
              ON demands USING gist (asset_id, period)
              WHERE asset_id IS NOT NULL AND is_active = true
        SQL);

        // Store-wide availability grids.
        DB::statement(<<<'SQL'
            CREATE INDEX idx_demands_store_active
              ON demands USING gist (store_id, period)
              WHERE is_active = true
        SQL);

        // Prevent two ACTIVE serialised demands for the same asset from
        // overlapping in time. Partial: bulk (asset_id NULL) and inactive rows
        // are ignored.
        DB::statement(<<<'SQL'
            ALTER TABLE demands
              ADD CONSTRAINT excl_demands_asset_period
              EXCLUDE USING gist (asset_id WITH =, period WITH &&)
              WHERE (asset_id IS NOT NULL AND is_active = true)
        SQL);

        // Serialised items always have quantity 1.
        DB::statement(<<<'SQL'
            ALTER TABLE demands
              ADD CONSTRAINT chk_demands_serialised_quantity
              CHECK (asset_id IS NULL OR quantity = 1)
        SQL);

        // Bulk items must have positive quantity.
        DB::statement(<<<'SQL'
            ALTER TABLE demands
              ADD CONSTRAINT chk_demands_bulk_quantity
              CHECK (asset_id IS NOT NULL OR quantity >= 1)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('demands');
    }
};
