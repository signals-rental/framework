<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityState as StateAxis;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\GuardsOpportunityLifecycle;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\Events\Opportunities\Concerns\ResyncsOpportunityDemands;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Reverses a confirmed Order back to a Quotation (opportunity-lifecycle.md §5.2
 * OpportunityRevertedToQuote) — the state-axis backward transition, the inverse of
 * {@see OpportunityConvertedToOrder}.
 *
 * Lands on Quotation/Provisional (the Quotation state's default status). Used to
 * pull a prematurely-confirmed order back to a re-negotiable quote.
 *
 * Guarded on two generic, phase/physical-state rules (never a named-status matrix,
 * Ben's locked steer):
 *  - the opportunity must be in the Order state and not closed/terminal; and
 *  - NOTHING may have been dispatched — {@see opportunityHasDispatchHistory()}
 *    rejects the revert if any serialised asset has reached Dispatched or any bulk
 *    line has a non-zero dispatched quantity. A job that has begun fulfilment
 *    cannot be un-ordered.
 *
 * Lock interaction: confirming the order froze the FX rate and tax figures
 * ({@see OpportunityConvertedToOrder}); reverting to a quote RELEASES both locks,
 * matching {@see OpportunityLocksReleased} / UnlockOpportunity semantics — a quote
 * is freely re-priceable, so the {@see App\Guards\Opportunities\Rules\FxTaxLockRule}
 * must no longer block rate/tax edits. Demand re-derives from the now-provisional
 * quotation status via {@see ResyncsOpportunityDemands}.
 *
 * Replay-safe: apply() flips in-memory state/status/locks, handle() projects them
 * + records the audit (deduped on the event id) + resyncs (idempotent).
 */
class OpportunityRevertedToQuotation extends Event
{
    use GuardsOpportunityLifecycle;
    use RecordsOpportunityAudit;
    use ResyncsOpportunityDemands;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public ?string $reason = null,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            $state->state === StateAxis::Order->value,
            'Only an order can be reverted to a quotation.',
        );

        $this->assert(
            ! $state->isClosed(),
            'A closed order cannot be reverted to a quotation.',
        );

        // §5.2 — "nothing dispatched": an order whose fulfilment has begun (any
        // asset dispatched, or any bulk line with stock booked out — even if since
        // returned) can never be un-ordered.
        $this->assert(
            ! $this->opportunityHasDispatchHistory($state->id),
            'An order with dispatched assets cannot be reverted to a quotation.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        $state->state = StateAxis::Quotation->value;
        $state->status = StateAxis::Quotation->defaultStatus()->statusValue();

        // Reverting to a quote re-opens pricing: release the FX/tax locks frozen at
        // order confirmation so the quote is freely re-priceable/re-taxable.
        $state->exchange_rate_locked = false;
        $state->tax_locked = false;

        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        // Capture the prior state/status + lock flags as raw values BEFORE the
        // projection update (the model casts `state` to an enum, so read raw).
        $oldRow = Opportunity::query()->where('state_id', $state->id)->first();
        $oldValues = $oldRow !== null ? [
            'state' => (int) $oldRow->getRawOriginal('state'),
            'status' => (int) $oldRow->getRawOriginal('status'),
            'exchange_rate_locked' => (bool) $oldRow->exchange_rate_locked,
            'tax_locked' => (bool) $oldRow->tax_locked,
        ] : null;

        Opportunity::query()
            ->where('state_id', $state->id)
            ->update([
                'state' => $state->state,
                'status' => $state->status,
                'exchange_rate_locked' => $state->exchange_rate_locked,
                'tax_locked' => $state->tax_locked,
            ]);

        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();

        $this->recordAudit(
            $opportunity,
            'opportunity.reverted_to_quotation',
            newValues: [
                'state' => $state->state,
                'status' => $state->status,
                'exchange_rate_locked' => $state->exchange_rate_locked,
                'tax_locked' => $state->tax_locked,
                'reason' => $this->reason,
            ],
            oldValues: $oldValues,
        );

        $this->resyncOpportunityDemands($opportunity);
    }
}
