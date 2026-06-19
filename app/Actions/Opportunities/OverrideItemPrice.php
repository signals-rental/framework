<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemPriceOverridden;

/**
 * Sets or clears a line item's manual unit-price override via the
 * ItemPriceOverridden event.
 *
 * Runs the M7 {@see GuardPipeline} for the `opportunity.item.price_override`
 * transition with `changes_rate: true`, so the registered
 * {@see App\Guards\Opportunities\Rules\FxTaxLockRule} rejects the edit (422) when
 * the order's exchange rate is locked. The pipeline's Permission stage replaces
 * the bare Gate::authorize the action used to make.
 */
class OverrideItemPrice
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, OverrideItemPriceData $data): OpportunityData
    {
        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data, $opportunity): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.item.price_override',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
                changes: ['changes_rate' => true],
            ));

            ItemPriceOverridden::fire(
                opportunity_item_id: $item->state_id,
                unit_price: $data->unit_price,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
