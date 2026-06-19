<?php

use App\Services\Shortages\ShortageAutoResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the store-level auto-resolution policy
 * (shortage-resolution-sub-hires.md §7.1, §7.5).
 *
 * `shortage_auto_resolve_enabled` switches on the synchronous auto-resolution
 * loop run by {@see ShortageAutoResolver} before the
 * confirmation gate evaluates. `shortage_preferred_resolvers` is the ordered
 * list of resolver keys the loop iterates; null/empty means "all resolvers by
 * priority". Stored as columns on `stores` to match the existing
 * `shortage_policy` column so the gate stays a single store read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->boolean('shortage_auto_resolve_enabled')
                ->default(false)
                ->after('shortage_policy');

            $table->jsonb('shortage_preferred_resolvers')
                ->nullable()
                ->after('shortage_auto_resolve_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn(['shortage_auto_resolve_enabled', 'shortage_preferred_resolvers']);
        });
    }
};
