<?php

use App\Enums\WaitlistMonitorStatus;
use App\Services\Shortages\Resolvers\WaitlistResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shortage waitlist monitors (shortage-resolution-sub-hires.md §4.6).
 *
 * A monitor is the durable watch the {@see WaitlistResolver}
 * places on a shortage: it links to the monitoring resolution record and captures
 * the product/store/quantity/window so the availability-change listener can flip
 * it to `matched` when freed-up stock would satisfy it, and the scheduled expiry
 * job can retire stale monitors. Integer PK per the RMS-compatibility convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortage_waitlist_monitors', function (Blueprint $table): void {
            $table->id();

            // The resolution record this monitor backs (the §4.6 Monitoring
            // resolution). Cascade-deletes with it.
            $table->foreignId('shortage_resolution_id')
                ->constrained('shortage_resolutions')
                ->cascadeOnDelete();

            $table->foreignId('opportunity_item_id')
                ->nullable()
                ->constrained('opportunity_items')
                ->nullOnDelete();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            $table->integer('quantity_needed');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();

            $table->string('status', 16)->default(WaitlistMonitorStatus::Active->value);
            $table->timestampTz('matched_at')->nullable();
            $table->timestampTz('notified_at')->nullable();
            $table->timestampTz('expires_at')->nullable();

            $table->timestampsTz();

            // The listener scans active monitors for the changed product/store.
            $table->index(['product_id', 'store_id', 'status'], 'idx_waitlist_product_store_status');
            // The expiry job scans active monitors past their expiry.
            $table->index(['status', 'expires_at'], 'idx_waitlist_status_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortage_waitlist_monitors');
    }
};
