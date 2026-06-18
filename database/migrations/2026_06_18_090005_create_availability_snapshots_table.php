<?php

use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-calculated availability state per product / store / time-slot.
 *
 * Snapshots are the hot read path for *range* availability queries (calendar
 * grids, timelines, shortage reports). They are derived from the authoritative
 * `demands` table by the {@see RecalculationPipeline}:
 * `available = total_stock - total_demanded` for the slot, with a per-source
 * `demand_breakdown`. Point queries do not read this table — they compute
 * on-the-fly from `demands` (see {@see AvailabilityService}).
 *
 * Each row is keyed uniquely by `(product_id, store_id, slot_start)`; the
 * pipeline upserts on that key. `available` is intentionally allowed to go
 * negative to represent shortages.
 *
 * Kit products have NO rows here — kit availability is composed at read time
 * from component snapshots (M5).
 *
 * TODO(perf): month-partition `slot_start`. The design (availability-engine.md)
 * calls for monthly range partitioning on `slot_start` with a rolling window
 * (default 90 days past / 365 future) at the deployment scales targeted
 * (~7.3M rows/year for daily resolution at 10k products × 2 stores). This is a
 * pure performance optimisation — it does not change the read/write contract of
 * this table or the AvailabilityService — so it is deferred to a later
 * milestone. A plain table is created here; the unique key and read indexes are
 * partition-compatible (they lead with the would-be partition key columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            // Slot boundary, aligned to the active resolution in the store's local
            // timezone and stored as UTC. See SlotCalculator.
            $table->timestampTz('slot_start');
            $table->integer('total_stock');
            $table->integer('total_demanded');
            // total_stock - total_demanded. May be negative (shortage).
            $table->integer('available');
            // Quantity demanded per source type, e.g. {"opportunity_item": 5}.
            $table->jsonb('demand_breakdown');
            // Returned-but-not-yet-cleared items (informational; populated in M5).
            $table->integer('pending_checkin_quantity')->default(0);
            $table->timestampTz('calculated_at');
            $table->timestampsTz();

            // One snapshot per product/store/slot — the pipeline upserts on this.
            $table->unique(['product_id', 'store_id', 'slot_start'], 'uq_snapshots_product_store_slot');

            // Primary read path for single-product range queries.
            $table->index(['product_id', 'store_id', 'slot_start'], 'idx_snapshots_product_store_slot');

            // Store-wide grid queries (all products in a store over a window).
            $table->index(['store_id', 'slot_start', 'product_id'], 'idx_snapshots_store_slot_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_snapshots');
    }
};
