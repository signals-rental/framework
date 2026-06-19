<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds FX and tax locking flags to the opportunity projection
 * (multi-currency-tax-engine.md §4.3 / §7.2).
 *
 * `exchange_rate_locked` freezes the stored `exchange_rate` so totals never
 * silently re-derive from the live rate once a quote is confirmed. `tax_locked`
 * freezes the stored tax figures so a later tax-rule/rate change does not alter a
 * confirmed order's totals. Both default false and are flipped true atomically by
 * the OpportunityConvertedToOrder event (quote → order). Written exclusively
 * through Verbs events, never directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->boolean('exchange_rate_locked')->default(false)->after('exchange_rate');
            $table->boolean('tax_locked')->default(false)->after('exchange_rate_locked');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropColumn(['exchange_rate_locked', 'tax_locked']);
        });
    }
};
