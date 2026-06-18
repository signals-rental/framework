<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-store operating hours and a virtual-store flag
 * (availability-engine.md §"Stores" — `operating_hours`, `is_virtual`).
 *
 * `operating_hours` (JSONB, nullable) holds per-day-of-week hours; null means
 * 24/7. `is_virtual` flags stores that are not a physical warehouse — vehicles,
 * job sites, sub-hire holding locations — which may be excluded from default
 * availability queries.
 *
 * Forward-looking schema dependencies: no consumer reads them yet, so they ship
 * as harmless additive columns the availability/transfer engines will use in
 * later milestones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->jsonb('operating_hours')->nullable()->after('timezone');
            $table->boolean('is_virtual')->default(false)->after('operating_hours');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn(['operating_hours', 'is_virtual']);
        });
    }
};
