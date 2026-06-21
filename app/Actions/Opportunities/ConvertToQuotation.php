<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityQuoted;

/**
 * Converts a Draft opportunity into a Quotation via the OpportunityQuoted event.
 *
 * Routed through the M7 {@see GuardPipeline} (Permission → Approval → Business
 * Rules → Plugin validators) before firing, so the
 * `opportunity.convert_to_quotation` transition is permission-checked
 * (`opportunities.edit`, exactly as before) and rule-extensible, and reports
 * correctly via {@see GuardPipeline::check()} in the `available_actions` endpoint.
 * No business rule is registered for this transition today, so behaviour is
 * unchanged. The hard invariants stay in the event's Verbs `validate()`.
 */
class ConvertToQuotation
{
    use CommitsVerbsEvents;

    /** The transition key this action drives through the guard pipeline. */
    public const string TRANSITION = 'opportunity.convert_to_quotation';

    public function __invoke(Opportunity $opportunity): OpportunityData
    {
        $this->commitVerbs(function () use ($opportunity): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: self::TRANSITION,
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            OpportunityQuoted::fire(opportunity_id: $opportunity->state_id);
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}
