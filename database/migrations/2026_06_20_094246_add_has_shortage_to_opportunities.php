<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a denormalised `has_shortage` boolean to `opportunities` (R-A / master B5).
 *
 * Per-line shortage state already lives in the availability read model, but the
 * opportunity list/Show "has a shortage" badge and the `q[has_shortage_true]`
 * filter need a fast, indexable per-opportunity flag with zero per-row detection
 * cost. The column is maintained by the availability recalculation path
 * (RecalculateAvailabilityJob), which already enumerates every opportunity
 * affected by a recompute and re-runs the ShortageDetector for it; it is never
 * written on a Verbs replay (the recalc job is dispatched only by replay-skipped
 * demand/stock observers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->boolean('has_shortage')->default(false)->after('invoiced')->index();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            // Drop the index before the column: SQLite's native DROP COLUMN
            // fails while an index still references the column.
            $table->dropIndex(['has_shortage']);
            $table->dropColumn('has_shortage');
        });
    }
};
