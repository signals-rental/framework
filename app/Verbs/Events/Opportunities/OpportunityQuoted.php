<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityState as StateAxis;
use App\Models\Opportunity;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Converts a Draft opportunity into a Quotation, landing on the Quotation
 * state's default status (Provisional).
 *
 * Guarded: the opportunity must currently be in the Draft state.
 */
class OpportunityQuoted extends Event
{
    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            $state->state === StateAxis::Draft->value,
            'Only a draft opportunity can be converted to a quotation.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        $state->state = StateAxis::Quotation->value;
        $state->status = StateAxis::Quotation->defaultStatus()->statusValue();
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        Opportunity::query()
            ->where('state_id', $state->id)
            ->update([
                'state' => $state->state,
                'status' => $state->status,
            ]);
    }
}
