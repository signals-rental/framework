<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityState as StateAxis;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use App\Verbs\Events\Opportunities\Concerns\GuardsOpportunityLifecycle;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\Events\Opportunities\Concerns\ResyncsOpportunityDemands;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Converts a Quotation into a confirmed Order, landing on the Order state's
 * default status (Active).
 *
 * Guarded: the opportunity must be in the Quotation state and must not be in a
 * closed/terminal status (the generic {@see OpportunityState::isClosed()} check,
 * which covers Lost and Dead without hardcoding status names).
 *
 * This is also the FX/tax locking point (multi-currency-tax-engine.md §4.3/§7.2):
 * confirming an order freezes both `exchange_rate_locked` and `tax_locked` so the
 * order's stored exchange rate and tax figures can never silently change if the
 * live rate or tax rules move afterwards. The {@see OpportunityTotalsCalculator}
 * honours these flags on every subsequent recompute.
 */
class OpportunityConvertedToOrder extends Event
{
    use GuardsOpportunityLifecycle;
    use RecordsOpportunityAudit;
    use ResyncsOpportunityDemands;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        /**
         * The quote version to confirm the order against (opportunity-lifecycle.md
         * §8.8). Resolved by the ConvertToOrder action — an Accepted version wins,
         * else the active version. Null for a non-versioned opportunity, where
         * conversion behaves exactly as before versioning.
         */
        public ?int $confirmed_version_id = null,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            $state->state === StateAxis::Quotation->value,
            'Only a quotation can be converted to an order.',
        );

        $this->assert(
            ! $state->isClosed(),
            'A closed quotation cannot be converted to an order.',
        );

        // §12.1 invariant — an order must have something to fulfil. Reads the
        // active-version line items from the projection (versioned opportunities
        // confirm against a single version, §8.8), so the check holds for both
        // versioned and non-versioned deals without name-matching a status.
        $this->assert(
            $this->opportunityHasActiveItem($state->id),
            'An opportunity with no items cannot be converted to an order.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        $state->state = StateAxis::Order->value;
        $state->status = StateAxis::Order->defaultStatus()->statusValue();
        // Confirming the order freezes the FX rate and the tax figures so they can
        // never silently re-derive once the client has committed (MC §4.3/§7.2).
        $state->exchange_rate_locked = true;
        $state->tax_locked = true;

        // §8.8 — the confirmed version becomes the (only) active version. Demand is
        // then created for its items via the resync; the others are superseded.
        if ($this->confirmed_version_id !== null) {
            $state->active_version_id = $this->confirmed_version_id;
        }

        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        // Capture the prior state/status as raw integers BEFORE the projection
        // update (the model casts `state` to an enum, so read getRawOriginal()).
        $oldRow = Opportunity::query()->where('state_id', $state->id)->first();
        $oldValues = $oldRow !== null ? [
            'state' => (int) $oldRow->getRawOriginal('state'),
            'status' => (int) $oldRow->getRawOriginal('status'),
        ] : null;

        Opportunity::query()
            ->where('state_id', $state->id)
            ->update([
                'state' => $state->state,
                'status' => $state->status,
                'exchange_rate_locked' => $state->exchange_rate_locked,
                'tax_locked' => $state->tax_locked,
                'active_version_id' => $state->active_version_id,
            ]);

        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();

        // §8.8 — confirm the order against a single version: the confirmed version
        // becomes the only active one, so the demand resync below claims stock for
        // its items alone. The non-confirmed versions are superseded by the
        // ConvertToOrder action firing VersionSuperseded events around this one.
        if ($this->confirmed_version_id !== null) {
            OpportunityVersion::query()
                ->where('opportunity_id', $opportunity->id)
                ->update(['is_active' => false]);

            OpportunityVersion::query()
                ->whereKey($this->confirmed_version_id)
                ->update(['is_active' => true]);
        }

        $this->recordAudit(
            $opportunity,
            'opportunity.converted_to_order',
            newValues: [
                'state' => $state->state,
                'status' => $state->status,
                'exchange_rate_locked' => $state->exchange_rate_locked,
                'tax_locked' => $state->tax_locked,
            ],
            oldValues: $oldValues,
        );

        $this->resyncOpportunityDemands($opportunity);
    }
}
