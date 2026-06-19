<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Releases the FX and tax locks set at quote → order conversion
 * (multi-currency-tax-engine.md §4.3/§7.2).
 *
 * {@see OpportunityConvertedToOrder} freezes `exchange_rate_locked` and
 * `tax_locked` so a confirmed order's stored rate / tax figures can never silently
 * re-derive. This is the inverse: it clears BOTH flags so an authorised user can
 * re-price or re-tax an order (e.g. to correct a rate booked against the wrong
 * day). After release, the {@see App\Guards\Opportunities\Rules\FxTaxLockRule}
 * guard permits rate/tax edits again, and the
 * {@see App\Services\Opportunities\OpportunityTotalsCalculator} resumes live
 * re-derivation on recompute.
 *
 * Pure dual-write: apply() flips the in-memory flags, handle() projects them and
 * records the audit. Idempotent — replay re-projects the same `false`/`false`
 * row — so it is fully replay-safe.
 */
class OpportunityLocksReleased extends Event
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
            $state->exchange_rate_locked || $state->tax_locked,
            'The opportunity has no active FX/tax locks to release.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        $state->exchange_rate_locked = false;
        $state->tax_locked = false;
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
            'opportunity.locks_released',
            newValues: [
                'exchange_rate_locked' => $state->exchange_rate_locked,
                'tax_locked' => $state->tax_locked,
                'reason' => $this->reason,
            ],
            oldValues: $oldValues,
        );
    }
}
