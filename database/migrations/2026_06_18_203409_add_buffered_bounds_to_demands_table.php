<?php

use App\Models\Demand;
use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the buffered (turnaround-inclusive) demand bounds alongside the raw
 * pre-buffer `starts_at` / `ends_at`.
 *
 * On PostgreSQL the buffered window already lives in the `period` tstzrange and
 * is the authoritative range queried via `&&`. But the per-slot PHP attribution
 * loops ({@see AvailabilityService::sumDemandIn()},
 * {@see RecalculationPipeline::demandForSlot()}) and
 * the SQLite scalar overlap path ({@see Demand::scopeOverlapping()})
 * re-filter on the RAW `starts_at` / `ends_at`, so a bulk unit appeared
 * AVAILABLE during its own prep/turnaround window — fetch (buffered) and
 * attribute (raw) disagreed.
 *
 * Storing the buffered bounds as plain columns makes the buffered window
 * available to that PHP/SQLite logic on every driver. They are SNAPSHOTTED at
 * write time (computed once from the product's buffers when the demand row is
 * written), never recomputed from live config on read — so a Verbs replay that
 * predates a buffer-config change reproduces the exact same window and never
 * diverges. Nullable: zero-buffer demands and legacy rows fall back to the raw
 * dates via the model accessors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->timestampTz('buffered_starts_at')->nullable()->after('ends_at');
            $table->timestampTz('buffered_ends_at')->nullable()->after('buffered_starts_at');
        });

        // On Postgres the buffered window is the GiST-indexed `period`; these
        // columns back the read-model/display only, so no extra index is needed.
        // On SQLite they drive the scalar overlap path — mirror the existing
        // raw-date index so buffered lookups stay covered.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            return;
        }

        Schema::table('demands', function (Blueprint $table): void {
            $table->index(
                ['product_id', 'store_id', 'buffered_starts_at', 'buffered_ends_at'],
                'idx_demands_product_store_buffered',
            );
        });
    }

    public function down(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            if (Schema::getConnection()->getDriverName() !== 'pgsql') {
                $table->dropIndex('idx_demands_product_store_buffered');
            }

            $table->dropColumn(['buffered_starts_at', 'buffered_ends_at']);
        });
    }
};
