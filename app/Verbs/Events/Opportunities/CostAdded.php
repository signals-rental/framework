<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityCosts;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityCostState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Genesis event for an opportunity cost. Creates the cost state, projects the
 * `opportunity_costs` row, resolves its tax rate, and rolls the totals up onto the
 * parent opportunity.
 *
 * `opportunity_cost_id` is the application-allocated small projection PK, allocated
 * by the AddOpportunityCost action via {@see SequenceAllocator} and baked into the
 * payload so replay reproduces identical ids (replay-stable). Costs are NOT priced
 * by the rate engine — there is no availability demand to sync.
 *
 * The projection dual-write, tax + totals recompute, and audit bridge all run on
 * replay (idempotent).
 */
class CostAdded extends Event
{
    use PricesOpportunityCosts, RecordsOpportunityAudit;

    public function __construct(
        public int $opportunity_cost_id,
        #[StateId(OpportunityCostState::class)]
        public ?int $state_id = null,
        public int $opportunity_id = 0,
        public string $description = '',
        public int $cost_type = 0,
        public int $transaction_type = 2,
        public int $amount = 0,
        public string $quantity = '1',
        public bool $is_optional = false,
        public int $sort_order = 0,
        public ?string $notes = null,
    ) {}

    public function apply(OpportunityCostState $state): void
    {
        $state->opportunity_cost_id = $this->opportunity_cost_id;
        $state->opportunity_id = $this->opportunity_id;
        $state->description = $this->description;
        $state->cost_type = $this->cost_type;
        $state->transaction_type = $this->transaction_type;
        $state->amount = $this->amount;
        $state->quantity = $this->quantity;
        $state->is_optional = $this->is_optional;
        $state->sort_order = $this->sort_order;
        $state->notes = $this->notes;
        $state->is_removed = false;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityCostState $state): void
    {
        $opportunity = Opportunity::query()->whereKey($state->opportunity_id)->first();

        $cost = OpportunityCost::query()->updateOrCreate(
            ['id' => $state->opportunity_cost_id],
            [
                'state_id' => $state->id,
                'opportunity_id' => $state->opportunity_id,
                'description' => $state->description,
                'cost_type' => $state->cost_type,
                'transaction_type' => $state->transaction_type,
                'amount' => $state->amount,
                'quantity' => $state->quantity,
                'currency_code' => $opportunity?->currency_code,
                'is_optional' => $state->is_optional,
                'sort_order' => $state->sort_order,
                'notes' => $state->notes,
            ],
        );

        $this->repriceAndRollUp($cost);

        if ($opportunity !== null) {
            $cost->refresh();

            $this->recordAudit(
                $opportunity,
                'opportunity.cost_added',
                newValues: $this->costSnapshot($cost),
                oldValues: null,
            );
        }
    }
}
