<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds currency handling and a manual deal-total override to the opportunity
 * projection, plus a per-item currency snapshot on `opportunity_items`.
 *
 * `currency_code` carries the document currency (snapshotted at creation, with a
 * matching snapshot on each line item). `exchange_rate` is the rate snapshot at
 * creation time (DECIMAL(20,10), default 1 for single-currency installs).
 * `deal_total` is an OPTIONAL manual override (INTEGER minor units, nullable): a
 * non-null value replaces the engine-computed `charge_total` headline; null means
 * "use the computed total". These mirror the RMS deal-pricing fields and are
 * written exclusively through Verbs events (DealPriceSet / DealPriceCleared).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('currency_code', 3)->nullable()->after('charge_ends_at');
            $table->decimal('exchange_rate', 20, 10)->default(1)->after('currency_code');
            // Manual deal-total override in integer minor units; null = use the
            // engine-computed charge_total.
            $table->integer('deal_total')->nullable()->after('charge_total');
        });

        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->string('currency_code', 3)->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropColumn(['currency_code', 'exchange_rate', 'deal_total']);
        });

        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropColumn('currency_code');
        });
    }
};
