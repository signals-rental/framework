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
 * Updates an opportunity cost's editable fields (description, type, amount,
 * quantity, optionality, sort order, notes), re-resolving its tax rate and rolling
 * the totals back up onto the parent.
 *
 * Every editable field is carried in the payload — the AddOpportunityCost action
 * merges the requested changes over the cost's current values BEFORE firing, so
 * the event payload is a complete snapshot and apply() stays a pure single-state
 * assignment (replay-stable, no cross-state reads).
 */
class CostUpdated extends Event
{
    use PricesOpportunityCosts, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityCostState::class)]
        public int $opportunity_cost_id,
        public string $description = '',
        public int $cost_type = 0,
        public int $transaction_type = 2,
        public int $amount = 0,
        public string $quantity = '1',
        public bool $is_optional = false,
        public int $sort_order = 0,
        public ?string $notes = null,
    ) {}

    public function validate(OpportunityCostState $state): void
    {
        $this->assertCostMutable($state);
    }

    public function apply(OpportunityCostState $state): void
    {
        $state->description = $this->description;
        $state->cost_type = $this->cost_type;
        $state->transaction_type = $this->transaction_type;
        $state->amount = $this->amount;
        $state->quantity = $this->quantity;
        $state->is_optional = $this->is_optional;
        $state->sort_order = $this->sort_order;
        $state->notes = $this->notes;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityCostState $state): void
    {
        $cost = OpportunityCost::query()->whereKey($state->opportunity_cost_id)->first();

        if ($cost === null) {
            return;
        }

        $oldSnapshot = $this->costSnapshot($cost);

        $cost->forceFill([
            'description' => $state->description,
            'cost_type' => $state->cost_type,
            'transaction_type' => $state->transaction_type,
            'amount' => $state->amount,
            'quantity' => $state->quantity,
            'is_optional' => $state->is_optional,
            'sort_order' => $state->sort_order,
            'notes' => $state->notes,
        ])->saveQuietly();

        $this->repriceAndRollUp($cost);

        $opportunity = $cost->opportunity()->first();

        if ($opportunity !== null) {
            $cost->refresh();

            $this->recordAudit(
                $opportunity,
                'opportunity.cost_updated',
                newValues: $this->costSnapshot($cost),
                oldValues: $oldSnapshot,
            );
        }
    }
}
