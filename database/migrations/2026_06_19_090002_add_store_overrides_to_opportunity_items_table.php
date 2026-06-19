<?php

use App\Services\Availability\OpportunityItemDemandResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line dispatch/return store overrides on opportunity items
 * (availability-engine.md §"Multi-warehouse dispatch per line").
 *
 * A line item may dispatch from — or return to — a store other than the
 * opportunity's primary store (multi-warehouse dispatch, split returns). Both
 * are nullable: when unset the line inherits the opportunity's `store_id`. The
 * demand resolver reads `dispatch_store_id ?? opportunity->store_id` when
 * deciding which store a line's demand claims against
 * ({@see OpportunityItemDemandResolver::syncDemands()}).
 *
 * `return_store_id` is forward-looking schema for the dispatch/return model
 * (M5): it records where a line is expected back, which can differ from where it
 * went out. No availability consumer reads it yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->foreignId('dispatch_store_id')
                ->nullable()
                ->after('ends_at')
                ->constrained('stores')
                ->nullOnDelete();

            $table->foreignId('return_store_id')
                ->nullable()
                ->after('dispatch_store_id')
                ->constrained('stores')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dispatch_store_id');
            $table->dropConstrainedForeignId('return_store_id');
        });
    }
};
