<?php

use App\Models\Store;
use App\Services\AvailabilityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the `include_in_default_queries` flag to stores
 * (availability-engine.md §"Stores" — `include_in_default_queries`).
 *
 * Virtual or secondary stores (vehicles, job sites, sub-hire holding locations)
 * can be flagged out of the *default* availability/grid queries while still
 * being addressable directly by id. This is distinct from `is_virtual`: a store
 * may be virtual yet still appear in default queries, or be physical yet hidden
 * from them. Defaults to true so existing stores are unaffected.
 *
 * Consumed by {@see Store::scopeInDefaultQueries()} and the
 * across-stores availability read path
 * ({@see AvailabilityService::getAvailabilityAcrossStores()}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->boolean('include_in_default_queries')->default(true)->after('is_virtual');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn('include_in_default_queries');
        });
    }
};
