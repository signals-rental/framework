<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Restores (un-archives) a previously soft-deleted opportunity. The
 * event-sourced truth is that the withdrawal recorded by
 * {@see OpportunityDeleted} has been reversed; the projection row's `deleted_at`
 * is cleared in handle() so it re-enters list/availability reads while the event
 * stream — and therefore full history — is preserved.
 *
 * State mutation is recorded on the in-memory {@see OpportunityState} so a replay
 * can reproduce the restoration, and the projection's soft-delete is reversed in
 * handle(). The audit row is dispatched through the standard
 * {@see RecordsOpportunityAudit} bridge (idempotent across replay).
 */
class OpportunityRestored extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
    ) {}

    public function apply(OpportunityState $state): void
    {
        $state->is_deleted = false;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        $opportunity = Opportunity::withTrashed()->where('state_id', $state->id)->firstOrFail();

        // Idempotent across replay: a row that is not (or no longer) soft-deleted
        // stays as-is, so re-running this event never resurrects nor re-audits.
        if (! $opportunity->trashed()) {
            return;
        }

        $opportunity->restore();

        $this->recordAudit(
            $opportunity,
            'opportunity.restored',
            newValues: ['id' => $opportunity->id],
            oldValues: null,
        );
    }
}
