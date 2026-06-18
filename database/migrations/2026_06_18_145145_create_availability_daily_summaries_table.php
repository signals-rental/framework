<?php

use App\Services\Availability\RecalculationPipeline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rolled-up daily availability summaries — the fast read path for calendar and
 * month-grid queries.
 *
 * One row per product / store / calendar-day (the day in the store's local
 * timezone). Each row captures the worst and best availability across every slot
 * that fell within that day, so a calendar cell can be coloured (and a shortage
 * flagged) from a single row instead of fanning out across the intra-day
 * snapshots.
 *
 * The {@see RecalculationPipeline} maintains these rows: after it upserts slot
 * snapshots it rolls the affected days up into this table. The design
 * (availability-engine.md) calls daily summaries out as needed "only for
 * quarterly/hourly installations" because under Daily resolution a snapshot
 * already *is* the day. We nonetheless populate this table at **all** resolutions
 * so calendar/grid consumers have one uniform read surface regardless of the
 * configured resolution — at Daily resolution the rollup is a 1:1 copy of the
 * single daily slot, which costs one row per day either way, so there is no
 * write amplification to avoid.
 *
 * Driver-agnostic: a `has_shortage` boolean carries the `min_available < 0`
 * signal so the partial shortage index can be expressed portably (Postgres uses
 * a partial index; SQLite a plain one).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_daily_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            // Calendar date in the store's local timezone (see SlotCalculator —
            // slot starts are derived in local time before being stored as UTC).
            $table->date('date');
            // Minimum `available` across every slot that fell within this day.
            $table->integer('min_available');
            // Maximum `available` across every slot that fell within this day.
            $table->integer('max_available');
            // True when availability dipped below zero at any point during the day
            // (i.e. min_available < 0). Denormalised so a shortage calendar can be
            // served from this column alone.
            $table->boolean('has_shortage')->default(false);
            $table->timestampTz('calculated_at');
            $table->timestampsTz();

            // One summary per product/store/day — the pipeline upserts on this.
            // The backing index also serves single-product calendar queries.
            $table->unique(['product_id', 'store_id', 'date'], 'uq_daily_summaries_product_store_date');

            // Store-wide calendar/grid queries (all products in a store by date).
            $table->index(['store_id', 'date', 'product_id'], 'idx_daily_summaries_store_date_product');
        });

        // Shortage calendar: only days that actually went short. Postgres can
        // express this as a partial index; SQLite gets a plain composite index.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE INDEX idx_daily_summaries_shortage
                  ON availability_daily_summaries (store_id, date)
                  WHERE has_shortage = true
            SQL);

            return;
        }

        Schema::table('availability_daily_summaries', function (Blueprint $table): void {
            $table->index(['store_id', 'date', 'has_shortage'], 'idx_daily_summaries_shortage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_daily_summaries');
    }
};
