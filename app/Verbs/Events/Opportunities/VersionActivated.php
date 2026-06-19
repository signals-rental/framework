<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityState as StateAxis;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use App\Verbs\States\OpportunityVersionState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

/**
 * Switches the ACTIVE quote version (opportunity-lifecycle.md §8.6/§8.7/§8.9).
 *
 * Applies to TWO states: the version being activated ({@see OpportunityVersionState})
 * and the parent {@see OpportunityState} (whose `active_version_id` and totals must
 * follow the new active version). In handle() it:
 *
 *  1. flips `is_active` on the version rows (previous active off, this one on);
 *  2. re-rolls the opportunity totals onto the new active version's scoped items
 *     ({@see OpportunityTotalsCalculator::rollUp} reads `items()`, which is scoped
 *     to `active_version_id`), so the opportunity's headline totals mirror the
 *     active version (REPLAY-ACTIVE — projection only);
 *  3. performs the DEMAND SWAP (§8.9): releases the previously-active version's
 *     item demands and syncs the new active version's item demands. SKIPPED ON
 *     REPLAY (demands are a rebuildable projection with their own rebuild path).
 *
 * Guarded: the version must exist, not be deleted, and the opportunity must still
 * be a Quotation.
 */
class VersionActivated extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityVersionState::class)]
        public int $version_id,
        public int $version_pk = 0,
        #[StateId(OpportunityState::class)]
        public int $opportunity_id = 0,
    ) {}

    public function validate(OpportunityVersionState $versionState, OpportunityState $opportunityState): void
    {
        $this->assert(! $versionState->is_deleted, 'A deleted version cannot be activated.');

        $this->assert(
            $opportunityState->state === StateAxis::Quotation->value,
            'A version can only be activated while the opportunity is a Quotation.',
        );
    }

    public function applyToVersion(OpportunityVersionState $state): void
    {
        $state->is_active = true;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function applyToOpportunity(OpportunityState $state): void
    {
        // active_version_id is the small projection PK, NOT the Verbs snowflake.
        $state->active_version_id = $this->version_pk;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityVersionState $versionState, OpportunityState $opportunityState): void
    {
        $version = OpportunityVersion::query()->where('state_id', $versionState->id)->first();

        if ($version === null) {
            return;
        }

        $opportunityPk = $version->opportunity_id;

        // Capture the previously-active version's items BEFORE the switch so the
        // demand swap can release exactly their demands.
        $previousVersionId = OpportunityVersion::query()
            ->where('opportunity_id', $opportunityPk)
            ->where('is_active', true)
            ->where('id', '!=', $version->id)
            ->value('id');

        $previousItems = $previousVersionId !== null
            ? OpportunityItem::query()->where('version_id', $previousVersionId)->get()
            : collect();

        // Flip the active flags.
        OpportunityVersion::query()
            ->where('opportunity_id', $opportunityPk)
            ->where('id', '!=', $version->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $version->forceFill(['is_active' => true])->save();

        // Point the opportunity at the new active version (small PK), then re-roll
        // its totals onto the now-active version's scoped items.
        Opportunity::query()
            ->where('state_id', $opportunityState->id)
            ->update(['active_version_id' => $version->id]);

        $opportunity = Opportunity::query()->where('state_id', $opportunityState->id)->first();

        if ($opportunity === null) {
            return;
        }

        $opportunity->refresh();
        app(OpportunityTotalsCalculator::class)->rollUp($opportunity);
        $this->syncVersionTotals($version, $opportunity);

        // DEMAND SWAP (§8.9) — replay-skipped. Release the old version's demands,
        // sync the new active version's. resyncForOpportunity reads items()
        // (scoped to the new active version).
        Verbs::unlessReplaying(function () use ($opportunity, $previousItems): void {
            $resolver = app(OpportunityItemDemandResolver::class);

            foreach ($previousItems as $previousItem) {
                $resolver->releaseDemands($previousItem);
            }

            $resolver->resyncForOpportunity($opportunity->refresh());
        });

        $this->recordAudit(
            $opportunity,
            'opportunity.version_activated',
            newValues: ['active_version_id' => $version->id],
            oldValues: $previousVersionId !== null ? ['active_version_id' => $previousVersionId] : null,
        );
    }

    /**
     * Mirror the opportunity's just-rolled-up NET totals onto the active version
     * row so the version carries its own snapshot of the figures.
     */
    private function syncVersionTotals(OpportunityVersion $version, Opportunity $opportunity): void
    {
        $opportunity->refresh();

        $version->forceFill([
            'charge_excluding_tax_total' => $opportunity->charge_excluding_tax_total,
            'tax_total' => $opportunity->tax_total,
            'charge_including_tax_total' => $opportunity->charge_including_tax_total,
            'charge_total' => $opportunity->charge_total,
        ])->save();
    }
}
