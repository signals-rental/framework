<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityState as StateAxis;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Converts a Quotation into a confirmed Order, landing on the Order state's
 * default status (Active).
 *
 * Guarded: the opportunity must be in the Quotation state and must not be in a
 * dead-end quotation status (Lost or Dead).
 */
class OpportunityConvertedToOrder extends Event
{
    use RecordsOpportunityAudit;

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
            ! in_array($state->status()->value, [
                OpportunityStatus::QuotationLost->value,
                OpportunityStatus::QuotationDead->value,
            ], true),
            'A lost or dead quotation cannot be converted to an order.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        $state->state = StateAxis::Order->value;
        $state->status = StateAxis::Order->defaultStatus()->statusValue();
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
            ]);

        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();

        $this->recordAudit(
            $opportunity,
            'opportunity.converted_to_order',
            newValues: ['state' => $state->state, 'status' => $state->status],
            oldValues: $oldValues,
        );
    }
}
