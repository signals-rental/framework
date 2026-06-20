<?php

use App\Services\Availability\RecalculationPipeline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roll the intra-day `pending_checkin_quantity` (units physically returned but not
 * yet inspected) up onto the daily summary read model.
 *
 * P2 populated `pending_checkin_quantity` on `availability_snapshots` only; this
 * column lets the calendar/month-grid read path surface the day's "pending return"
 * count without fanning out across the intra-day snapshots. The
 * {@see RecalculationPipeline} rolls the per-slot peak up into this column
 * alongside `min_available` / `max_available`. It is informational only — it never
 * affects availability or the `has_shortage` flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('availability_daily_summaries', function (Blueprint $table): void {
            // The day's peak units awaiting check-in across its slots. Defaults to
            // zero so existing rows (and Daily-resolution 1:1 copies) are valid.
            $table->integer('pending_checkin_quantity')->default(0)->after('max_available');
        });
    }

    public function down(): void
    {
        Schema::table('availability_daily_summaries', function (Blueprint $table): void {
            $table->dropColumn('pending_checkin_quantity');
        });
    }
};
