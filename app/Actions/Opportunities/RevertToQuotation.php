<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityRevertedToQuotation;

/**
 * Reverts a confirmed Order back to a Quotation via the
 * {@see OpportunityRevertedToQuotation} event — the state-axis backward
 * transition, the inverse of {@see ConvertToOrder}.
 *
 * Routed through the M7 {@see GuardPipeline} (Permission → Approval → Business
 * Rules → Plugin validators) before firing, so the `opportunity.revert_to_quotation`
 * transition is permission-checked and rule-extensible, and shows up in the
 * `available_actions` endpoint via {@see GuardPipeline::check()}. The hard
 * invariants — must be an Order, not closed, and nothing dispatched — stay in the
 * event's Verbs `validate()`. Reverting releases the order's FX/tax locks (the
 * event clears them) so the re-opened quote is freely re-priceable.
 */
class RevertToQuotation
{
    use CommitsVerbsEvents;

    /** The transition key this action drives through the guard pipeline. */
    public const string TRANSITION = 'opportunity.revert_to_quotation';

    public function __invoke(Opportunity $opportunity, ?string $reason = null): OpportunityData
    {
        $this->commitVerbs(function () use ($opportunity, $reason): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: self::TRANSITION,
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            OpportunityRevertedToQuotation::fire(
                opportunity_id: $opportunity->state_id,
                reason: $reason,
            );
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}
