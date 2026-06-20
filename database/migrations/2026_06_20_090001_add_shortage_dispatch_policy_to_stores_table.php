<?php

use App\Enums\ShortageDispatchPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the store-level shortage DISPATCH-gate policy
 * (shortage-resolution-sub-hires.md §7.4).
 *
 * Separate from `shortage_policy` (the confirmation gate, §7.1): the dispatch gate
 * decides what happens to short line items at dispatch time. Stored as a typed
 * column on `stores` for the same reason as `shortage_policy` — availability and
 * shortage behaviour is keyed by store throughout the engine, and a column keeps
 * the gate read a single query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('shortage_dispatch_policy', 16)
                ->default(ShortageDispatchPolicy::default()->value)
                ->after('shortage_policy');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn('shortage_dispatch_policy');
        });
    }
};
