<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\Events\Opportunities\Concerns\ResyncsOpportunityDemands;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Moves an opportunity to a different status WITHIN its current state — for
 * example Quotation/Provisional → Quotation/Reserved, or Order/Active →
 * Order/Cancelled.
 *
 * Guarded on two generic rules:
 *  - the opportunity's CURRENT status must not be terminal/closed (so a
 *    Complete/Cancelled/Lost/Dead opportunity cannot be moved back to an active
 *    status, which would re-consume stock via demand); and
 *  - the TARGET status must belong to the opportunity's current state (state
 *    transitions go through the dedicated convert events, not this one).
 *
 * The closed check uses only the generic {@see OpportunityStatus::isClosed()}
 * property — there is deliberately NO per-status transition allow-list here, so
 * custom/configurable statuses remain supported. The richer configurable
 * per-transition business-rules engine is a later milestone.
 */
class OpportunityStatusChanged extends Event
{
    use RecordsOpportunityAudit;
    use ResyncsOpportunityDemands;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public int $to_status,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            ! $state->isClosed(),
            'A closed opportunity cannot change status.',
        );

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
        // Capture the prior status as a raw integer BEFORE the projection update.
        $oldRow = Opportunity::query()->where('state_id', $state->id)->first();
        $oldValues = $oldRow !== null
            ? ['status' => (int) $oldRow->getRawOriginal('status')]
            : null;

        Opportunity::query()
            ->where('state_id', $state->id)
            ->update(['status' => $state->status]);

        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();

        $this->recordAudit(
            $opportunity,
            'opportunity.status_changed',
            newValues: ['status' => $state->status],
            oldValues: $oldValues,
        );

        $this->resyncOpportunityDemands($opportunity);
    }
}
