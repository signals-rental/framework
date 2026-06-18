<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\OpportunityCost;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityCosts;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityCostState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Removes a cost from its opportunity. Hard-deletes the projection row (there is
 * no soft delete on `opportunity_costs`) and rolls the parent totals back down.
 */
class CostRemoved extends Event
{
    use PricesOpportunityCosts, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityCostState::class)]
        public int $opportunity_cost_id,
    ) {}

    public function validate(OpportunityCostState $state): void
    {
        $this->assertCostMutable($state);
    }

    public function apply(OpportunityCostState $state): void
    {
        $state->is_removed = true;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityCostState $state): void
    {
        $cost = OpportunityCost::query()->whereKey($state->opportunity_cost_id)->first();

        if ($cost === null) {
            return;
        }

        $opportunity = $cost->opportunity()->first();
        $snapshot = $this->costSnapshot($cost);

        $cost->delete();

        if ($opportunity !== null) {
            $this->rollUpOnly($opportunity);

            $this->recordAudit(
                $opportunity,
                'opportunity.cost_removed',
                newValues: null,
                oldValues: $snapshot,
            );
        }
    }
}
