<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Applies FX and tax locks on an opportunity (inverse of
 * {@see OpportunityLocksReleased}).
 *
 * Mirrors the lock semantics baked into {@see OpportunityConvertedToOrder} so an
 * authorised user can freeze re-pricing / re-taxing without converting state.
 */
class OpportunityLocksApplied extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public ?string $reason = null,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            ! $state->exchange_rate_locked && ! $state->tax_locked,
            'The opportunity already has FX/tax locks applied.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        $state->exchange_rate_locked = true;
        $state->tax_locked = true;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        $oldRow = Opportunity::query()->where('state_id', $state->id)->first();
        $oldValues = $oldRow !== null ? [
            'exchange_rate_locked' => (bool) $oldRow->exchange_rate_locked,
            'tax_locked' => (bool) $oldRow->tax_locked,
        ] : null;

        Opportunity::query()
            ->where('state_id', $state->id)
            ->update([
                'exchange_rate_locked' => $state->exchange_rate_locked,
                'tax_locked' => $state->tax_locked,
            ]);

        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();

        $this->recordAudit(
            $opportunity,
            'opportunity.locks_applied',
            newValues: [
                'exchange_rate_locked' => $state->exchange_rate_locked,
                'tax_locked' => $state->tax_locked,
                'reason' => $this->reason,
            ],
            oldValues: $oldValues,
        );
    }
}
