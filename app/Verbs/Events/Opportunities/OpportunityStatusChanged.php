<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Moves an opportunity to a different status WITHIN its current state — for
 * example Quotation/Provisional → Quotation/Reserved, or Order/Active →
 * Order/Cancelled.
 *
 * Guarded: the target status must belong to the opportunity's current state
 * (state transitions go through the dedicated convert events, not this one).
 */
class OpportunityStatusChanged extends Event
{
    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public int $to_status,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $target = OpportunityStatus::tryFrom($state->state * 100 + $this->to_status);

        $this->assert(
            $target !== null,
            'The target status is not valid for the current opportunity state.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        $state->status = $this->to_status;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        Opportunity::query()
            ->where('state_id', $state->id)
            ->update(['status' => $state->status]);
    }
}
