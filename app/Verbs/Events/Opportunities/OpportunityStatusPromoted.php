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
 * Auto-promotes an opportunity to a later status WITHIN its current state as a
 * side-effect of asset-level progress — for example Order/Active → Order/Dispatched
 * once the last asset is dispatched, or → Order/Returned once the last asset is
 * returned (opportunity-lifecycle.md §7.2).
 *
 * Per the lifecycle plan, auto-promotion must be a DISTINCT auditable, replayable
 * event — not a silent column write inside another event's handler — so the audit
 * trail (and any downstream listeners) can tell an automatic promotion apart from a
 * manual {@see OpportunityStatusChanged}.
 *
 * FIRING PATH (M5-2, live): fired from
 * {@see App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus::promoteOpportunityFromItems()},
 * which runs in an asset/bulk fulfilment event's `fired()` hook. That hook
 * re-derives the Order's aggregate sub-status from every line item's projected
 * state (§7.6, the "lowest common denominator") and fires this event only when the
 * derived status differs from the current one. Because it runs in `fired()` (after
 * apply(), before commit, original request only — never on replay), the promotion
 * is persisted as its own event and replays independently; the asset event never
 * re-fires it during a replay.
 */
class OpportunityStatusPromoted extends Event
{
    use RecordsOpportunityAudit;
    use ResyncsOpportunityDemands;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public int $to_status = 0,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            ! $state->isClosed(),
            'A closed opportunity cannot be promoted.',
        );

        $target = OpportunityStatus::tryFrom($state->state * 100 + $this->to_status);

        $this->assert(
            $target !== null,
            'The promotion target status is not valid for the current opportunity state.',
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
            'opportunity.status_promoted',
            newValues: ['status' => $state->status],
            oldValues: $oldValues,
        );

        $this->resyncOpportunityDemands($opportunity);
    }
}
