<?php

use App\Enums\ShortagePolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the store-level shortage confirmation-gate policy
 * (shortage-resolution-sub-hires.md §7.1). Stored as a column on `stores` rather
 * than a generic store setting because availability/shortage behaviour is keyed
 * by store everywhere else in the engine (Demand.store_id, snapshots, the gate)
 * and a typed column keeps the gate query a single read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('shortage_policy', 16)
                ->default(ShortagePolicy::default()->value)
                ->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn('shortage_policy');
        });
    }
};
