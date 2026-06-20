<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemDiscountSet;

/**
 * Sets or clears a line item's percentage discount via the ItemDiscountSet event.
 *
 * Runs the M7 {@see GuardPipeline} for the `opportunity.item.set_discount`
 * transition with `changes_rate: true` (a discount changes the line's effective
 * charge), so {@see App\Guards\Opportunities\Rules\FxTaxLockRule} rejects the edit
 * (422) on a locked order. The pipeline's Permission stage replaces the bare
 * Gate::authorize the action used to make.
 */
class SetItemDiscount
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, SetItemDiscountData $data): OpportunityData
    {
        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data, $opportunity): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.item.set_discount',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
                changes: ['changes_rate' => true],
            ));

            ItemDiscountSet::fire(
                opportunity_item_id: $item->state_id,
                discount_percent: $data->discount_percent,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
