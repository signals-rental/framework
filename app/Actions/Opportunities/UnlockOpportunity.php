<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityLocksReleased;
use Illuminate\Support\Facades\Gate;

/**
 * Releases an opportunity's FX and tax locks via the OpportunityLocksReleased
 * event (multi-currency-tax-engine.md §4.3/§7.2).
 *
 * Confirming an order locks the exchange rate and tax figures; this is the
 * privileged unlock path so an authorised user can re-price/re-tax. It is gated on
 * the dedicated `opportunities.unlock_rates` permission (a more privileged ability
 * than `opportunities.edit`) so routine editors cannot silently undo the lock.
 */
class UnlockOpportunity
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, ?string $reason = null): OpportunityData
    {
        Gate::authorize('opportunities.unlock_rates');

        $this->commitVerbs(function () use ($opportunity, $reason): void {
            OpportunityLocksReleased::fire(
                opportunity_id: $opportunity->state_id,
                reason: $reason,
            );
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}
