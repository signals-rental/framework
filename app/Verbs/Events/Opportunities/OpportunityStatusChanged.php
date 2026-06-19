<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\DemandPhase;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\GuardsOpportunityLifecycle;
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
    use GuardsOpportunityLifecycle;
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

        if ($target === null) {
            return;
        }

        // §12.1 invariants — keyed on the TARGET status's PHASE / capability, not a
        // named-status matrix, so configurable/custom statuses inherit the rule:

        // Cancel-with-assets-out: a transition into the Void phase (cancelled /
        // dead / lost) is rejected while any stock is physically out with the
        // client (a serialised asset Dispatched/OnHire, or a bulk line still
        // outstanding). Stock must be recovered before the deal can be voided.
        if ($target->phase() === DemandPhase::Void) {
            $this->assert(
                ! $this->opportunityHasStockOut($state->id),
                'An opportunity with assets still out on hire cannot be cancelled.',
            );
        }

        // Complete-with-unreturned: the terminal "complete" close requires every
        // asset to be finalised/returned — no asset still Dispatched/OnHire/
        // CheckedIn and no bulk line still out.
        if ($target->isTerminalComplete()) {
            $this->assert(
                ! $this->opportunityHasUnreturnedAssets($state->id),
                'An opportunity with unreturned assets cannot be completed.',
            );
        }
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
