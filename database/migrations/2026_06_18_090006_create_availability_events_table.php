<?php

use App\Enums\AvailabilityEventType;
use App\Observers\DemandObserver;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable, append-only log of availability-significant events.
 *
 * Separate from `action_logs` so availability has its own domain-focused audit
 * stream with independent retention. Written by the
 * {@see RecalculationPipeline} and the
 * {@see DemandObserver}: demand lifecycle changes
 * (`demand_created` / `demand_released`), recalculations
 * (`availability_recalculated`), and — in later milestones — stock and shortage
 * events. The auto-incrementing primary key provides a total ordering.
 *
 * @see AvailabilityEventType
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 50);
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            // Stock-level events carry no demand; demand rows may be hard-deleted,
            // so this is a plain nullable column rather than a constrained FK.
            $table->unsignedBigInteger('demand_id')->nullable();
            $table->string('source_type', 255)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            // Event-specific data: old/new values, affected range, quantities.
            $table->jsonb('payload');
            $table->timestampTz('created_at')->nullable();

            $table->index(['product_id', 'store_id', 'created_at'], 'idx_availability_events_product_store');
            $table->index(['event_type', 'created_at'], 'idx_availability_events_type');
            $table->index('demand_id', 'idx_availability_events_demand');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_events');
    }
};
