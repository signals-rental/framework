<?php

use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Verbs\States\AssetAssignmentState;
use App\Verbs\States\OpportunityCostState;
use App\Verbs\States\OpportunityItemState;
use App\Verbs\States\OpportunityState;
use App\Verbs\States\OpportunityVersionState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Post-cutover data wipe for the unified opportunity line-item model.
 *
 * Runs AFTER {@see 2026_06_22_230001_cutover_unified_opportunity_line_items} on
 * databases that still carry legacy item projections and frozen item-scoped
 * Verbs events from the pre-unified model. Truncates item projections and demand/
 * asset rows, deletes ONLY item-scoped event-store rows (see
 * {@see itemScopedVerbStateTypes()}), and zeroes opportunity rollup columns.
 *
 * Idempotent on a fresh database (empty tables are a no-op). Data wipes are not
 * reversible — down() is intentionally a no-op.
 */
return new class extends Migration
{
    /**
     * Verbs state classes whose events must be purged so frozen legacy item rows
     * are never replayed onto the unified schema.
     *
     * Deliberately excludes {@see OpportunityState},
     * {@see OpportunityCostState}, and
     * {@see OpportunityVersionState} — those carry opportunity
     * header, cost, and quote-version lifecycle that survives the line-item reset.
     *
     * @return list<class-string>
     */
    private function itemScopedVerbStateTypes(): array
    {
        return [
            OpportunityItemState::class,
            AssetAssignmentState::class,
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('opportunity_items')) {
            return;
        }

        $this->purgeItemScopedVerbEvents();
        $this->truncateItemProjections();
        $this->zeroOpportunityRollups();
    }

    /**
     * Delete item-scoped Verbs rows via verb_state_events.state_type — NOT a blind
     * verb_events truncate. Junction rows and snapshots for the same state types
     * are removed so replay cannot resurrect stale item state from snapshots.
     */
    private function purgeItemScopedVerbEvents(): void
    {
        if (! Schema::hasTable('verb_state_events')) {
            return;
        }

        $stateTypes = $this->itemScopedVerbStateTypes();

        $eventIds = DB::table('verb_state_events')
            ->whereIn('state_type', $stateTypes)
            ->distinct()
            ->pluck('event_id');

        if ($eventIds->isNotEmpty() && Schema::hasTable('verb_events')) {
            DB::table('verb_events')->whereIn('id', $eventIds)->delete();
        }

        DB::table('verb_state_events')->whereIn('state_type', $stateTypes)->delete();

        if (Schema::hasTable('verb_snapshots')) {
            DB::table('verb_snapshots')->whereIn('type', $stateTypes)->delete();
        }
    }

    private function truncateItemProjections(): void
    {
        if (Schema::hasTable('opportunity_item_assets')) {
            DB::table('opportunity_item_assets')->delete();
        }

        if (Schema::hasTable('demands')) {
            DB::table('demands')->delete();
        }

        DB::table('opportunity_items')->delete();
    }

    /**
     * Zero rollup columns written by {@see OpportunityTotalsCalculator::rollUp()}.
     */
    private function zeroOpportunityRollups(): void
    {
        if (! Schema::hasTable('opportunities')) {
            return;
        }

        DB::table('opportunities')->update([
            'deal_total' => null,
            'charge_total' => 0,
            'rental_charge_total' => 0,
            'sale_charge_total' => 0,
            'service_charge_total' => 0,
            'sub_rental_charge_total' => 0,
            'transit_charge_total' => 0,
            'loss_damage_charge_total' => 0,
            'charge_excluding_tax_total' => 0,
            'charge_including_tax_total' => 0,
            'tax_total' => 0,
        ]);
    }

    public function down(): void
    {
        // Data wipes are not reversible. Re-populate via demo seeders after rollback.
    }
};
