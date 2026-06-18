<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Soft-deletes (archives) an opportunity. The event-sourced truth is that the
 * document was withdrawn; the projection row is soft-deleted so it drops out of
 * list/availability reads while the event stream — and therefore full history —
 * is preserved.
 *
 * State mutation is recorded on the in-memory {@see OpportunityState} so a
 * replay can reproduce the deletion, and the projection's `deleted_at` is set in
 * handle(). The audit row is dispatched through the standard
 * {@see RecordsOpportunityAudit} bridge (idempotent across replay).
 */
class OpportunityDeleted extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
    ) {}

    public function apply(OpportunityState $state): void
    {
        $state->is_deleted = true;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();

        // Idempotent across replay: a row already soft-deleted stays soft-deleted.
        if ($opportunity->trashed()) {
            return;
        }

        $opportunity->delete();

        $this->recordAudit(
            $opportunity,
            'opportunity.deleted',
            newValues: ['id' => $opportunity->id],
            oldValues: null,
        );
    }
}
