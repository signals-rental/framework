<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityState as StateAxis;
use App\Models\Opportunity;
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
    use RecordsOpportunityAudit;
    use ResyncsOpportunityDemands;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
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
    }

    public function apply(OpportunityState $state): void
    {
        $state->state = StateAxis::Order->value;
        $state->status = StateAxis::Order->defaultStatus()->statusValue();
        // Confirming the order freezes the FX rate and the tax figures so they can
        // never silently re-derive once the client has committed (MC §4.3/§7.2).
        $state->exchange_rate_locked = true;
        $state->tax_locked = true;
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
            ]);

        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();

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
