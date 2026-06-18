<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `opportunity_item_assets` table is the read-optimised projection of the
 * event-sourced per-asset assignments for serialised line items (section 3.3 of
 * opportunity-lifecycle.md). It links a specific physical asset (`stock_level_id`)
 * to a line item and tracks that asset's individual position through the
 * dispatch/return cycle. All mutations flow through Verbs asset events (M5) whose
 * handle() methods dual-write this row.
 *
 * One row per physical asset (quantity is implicitly 1). Assets are hard rows
 * tied to the line item (no soft delete — de-allocation is event-sourced in M5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_item_assets', function (Blueprint $table) {
            // RMS-compatible small integer primary key. Application-assigned: the
            // AssetAllocated event (M5) allocates it via SequenceAllocator
            // ('opportunity_item_assets') and bakes it into the event, so a
            // truncate + Verbs::replay() rebuild reproduces identical ids.
            $table->unsignedBigInteger('id')->primary();

            // Bridge to the Verbs event stream (snowflake StateId). Unique +
            // indexed so projections upsert by it and replay rebuilds
            // deterministically — mirrors `opportunities.state_id`.
            $table->unsignedBigInteger('state_id')->unique();

            // Parent line item (the projection's small int id).
            $table->foreignId('opportunity_item_id')->constrained('opportunity_items')->cascadeOnDelete();

            // The specific physical asset assigned. Points at the Phase-2
            // `stock_levels` table (auto-increment id). Nullable + nullOnDelete so
            // the projection tolerates the asset row being corrected/removed.
            $table->foreignId('stock_level_id')->nullable()->constrained('stock_levels')->nullOnDelete();

            // Per-asset position in the dispatch/return cycle (0=Allocated ..
            // 5=Finalised, see AssetAssignmentStatus).
            $table->smallInteger('status')->default(0);

            // Kit/case this asset is nested within, if any (also a stock_levels id).
            $table->foreignId('container_stock_level_id')->nullable()->constrained('stock_levels')->nullOnDelete();

            // Lifecycle milestone timestamps (UTC).
            $table->dateTime('allocated_at')->nullable();
            $table->dateTime('prepared_at')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->dateTime('checked_at')->nullable();

            // Condition assessed at check-in (0=Good, 1=Damaged, 2=Missing).
            $table->smallInteger('condition_on_return')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('opportunity_item_id');
            $table->index('stock_level_id');
            $table->index('status');
            $table->index('container_stock_level_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_item_assets');
    }
};
