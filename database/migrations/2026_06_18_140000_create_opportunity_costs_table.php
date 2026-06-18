<?php

use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityCostType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `opportunity_costs` table is the read-optimised projection of the
 * event-sourced opportunity costs (M3-2). Costs are ad-hoc charges that sit
 * alongside the priced line items — delivery, crew labour, surcharges, insurance,
 * loss/damage recovery, etc. Unlike line items they are NOT priced by the rate
 * engine; each carries its own `amount`. They are taxed (inclusive/exclusive,
 * matching the line-item handling) and rolled into the opportunity totals.
 *
 * Columns mirror the M3-1 line-item projection conventions: an application-
 * assigned small integer PK (allocated at event-fire time via SequenceAllocator
 * and baked into the genesis CostAdded event, replay-stable) plus a snowflake
 * `state_id` bridge to the Verbs event stream. Money is INTEGER minor units
 * (pence/cents/fils); `quantity` is a genuine decimal. No soft delete — removal is
 * event-sourced (CostRemoved hard-deletes the row).
 *
 * Deferred FK columns: the RMS `cost_group_id` (FK → cost_groups) is intentionally
 * NOT added here — the `cost_groups` table is a Discount & Pricing Rules Engine
 * deliverable (Phase 4) and does not yet exist. `cost_type` carries the categorical
 * intent in the interim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_costs', function (Blueprint $table): void {
            // RMS-compatible small integer primary key. Application-assigned: the
            // CostAdded event allocates it via SequenceAllocator ('opportunity_costs')
            // and bakes it into the event, so a truncate + Verbs::replay() rebuild
            // reproduces identical ids (replay-stable).
            $table->unsignedBigInteger('id')->primary();

            // Bridge to the Verbs event stream (snowflake StateId). Unique + indexed
            // so projections upsert by it and replay rebuilds deterministically —
            // mirrors `opportunity_items.state_id`.
            $table->unsignedBigInteger('state_id')->unique();

            // Parent opportunity (the projection's small int id).
            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();

            $table->string('description');

            // Categorical cost type (delivery/labour/surcharge/insurance/loss-damage/
            // misc). Steers which money-total bucket the cost's net contributes to.
            $table->smallInteger('cost_type')->default(OpportunityCostType::Misc->value);

            // Commercial nature mirrors the line-item axis; costs default to Service.
            $table->smallInteger('transaction_type')->default(LineItemTransactionType::Service->value);

            // amount is the per-unit charge in INTEGER minor units; quantity is a
            // genuine decimal (default 1). The line net is amount * round(quantity).
            $table->integer('amount')->default(0);
            $table->decimal('quantity', 10, 2)->default(1);

            // Resolved tax rate for the cost as a decimal-string percentage (snapshot).
            $table->decimal('tax_rate', 5, 2)->nullable();

            // Per-cost currency snapshot (inherits the opportunity currency).
            $table->string('currency_code', 3)->nullable();

            // Optional costs are excluded from the opportunity's charge totals.
            $table->boolean('is_optional')->default(false);

            $table->integer('sort_order')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('opportunity_id');
            $table->index('cost_type');
            $table->index(['opportunity_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_costs');
    }
};
