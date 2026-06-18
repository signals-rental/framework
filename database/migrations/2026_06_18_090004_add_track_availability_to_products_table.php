<?php

use App\Services\Availability\RecalculationPipeline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the `track_availability` flag to products.
 *
 * Products participate in the availability engine by default. Setting this to
 * false (e.g. consumables, services, or notional catalogue entries that are
 * never demand-tracked) causes the {@see RecalculationPipeline}
 * to skip the product entirely — no snapshots are written and no availability is
 * computed for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('track_availability')->default(true)->after('post_rent_unavailability');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('track_availability');
        });
    }
};
