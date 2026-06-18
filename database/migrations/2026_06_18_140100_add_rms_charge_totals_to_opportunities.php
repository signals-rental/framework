<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the remaining no-dependency RMS opportunity money-total columns (M3-2):
 * `sub_rental_charge_total`, `transit_charge_total`, `loss_damage_charge_total`
 * (per opportunity-lifecycle.md §3.1). INTEGER minor units, default 0, all
 * engine-calculated and written exclusively through Verbs events.
 *
 * - `transit_charge_total` / `loss_damage_charge_total` are populated from the net
 *   of matching `opportunity_costs` types (Delivery / LossDamage) by the
 *   OpportunityTotalsCalculator rollup.
 * - `sub_rental_charge_total` stays 0 for now — sub-hire is a Phase 4 deliverable
 *   (shortage-resolution-sub-hires.md); the column is added now so the projection
 *   shape is RMS-complete, but nothing populates it until sub-hire POs exist.
 *
 * Deferred FK columns (project_id, billing_address_id, destination_id,
 * tax_class_id) are intentionally NOT added: each FKs a table not yet built
 * (projects = Phase 7, addresses, tax_classes are split into organisation/product
 * tax classes here). They land with their respective dependency milestones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->integer('sub_rental_charge_total')->default(0)->after('service_charge_total');
            $table->integer('transit_charge_total')->default(0)->after('sub_rental_charge_total');
            $table->integer('loss_damage_charge_total')->default(0)->after('transit_charge_total');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropColumn([
                'sub_rental_charge_total',
                'transit_charge_total',
                'loss_damage_charge_total',
            ]);
        });
    }
};
