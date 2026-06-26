<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityLocksApplied;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Applies an opportunity's FX and tax locks via the OpportunityLocksApplied event.
 *
 * Gated on the privileged `opportunities.unlock_rates` permission — the same
 * ability used to release locks — so only authorised users can freeze rates/tax.
 */
class LockOpportunity
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, ?string $reason = null): OpportunityData
    {
        Gate::authorize('opportunities.unlock_rates');

        if ($opportunity->deal_total !== null) {
            throw ValidationException::withMessages([
                'opportunity' => 'Clear the deal price before locking price.',
            ]);
        }

        $this->commitVerbs(function () use ($opportunity, $reason): void {
            OpportunityLocksApplied::fire(
                opportunity_id: $opportunity->state_id,
                reason: $reason,
            );
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}
