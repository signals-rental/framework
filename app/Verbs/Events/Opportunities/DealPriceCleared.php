<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Clears a manual deal-total override, reverting the opportunity's `charge_total`
 * headline to the engine-computed gross total.
 */
class DealPriceCleared extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            ! $state->isClosed(),
            'A closed opportunity\'s deal price cannot be changed.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        $state->deal_total = null;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        $opportunity = Opportunity::query()->where('state_id', $state->id)->first();

        if ($opportunity === null) {
            return;
        }

        $oldDealTotal = $opportunity->deal_total !== null ? (int) $opportunity->deal_total : null;

        $opportunity->forceFill(['deal_total' => null])->saveQuietly();

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());

        $opportunity->refresh();

        $this->recordAudit(
            $opportunity,
            'opportunity.deal_price_cleared',
            newValues: ['deal_total' => null, 'charge_total' => $opportunity->charge_total],
            oldValues: ['deal_total' => $oldDealTotal],
        );
    }
}
