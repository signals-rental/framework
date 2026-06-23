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
 * transition (Permission stage replaces the bare Gate::authorize the action used
 * to make).
 *
 * It deliberately does NOT declare `changes_rate`/`changes_tax`: a manual
 * unit-price override is a STRUCTURAL edit to the agreed NET basis, not a re-
 * derivation of the exchange rate or the tax rule. Per
 * {@see App\Services\Opportunities\OpportunityTotalsCalculator} (class docblock,
 * "LOCKING"), the FX/tax lock freezes only later FX-rate or tax-rule re-pricing of
 * the already-agreed basis — structural/price edits on a locked order must STILL
 * recompute the net charge_total (at the frozen exchange-rate snapshot, without
 * re-deriving tax). So {@see App\Guards\Opportunities\Rules\FxTaxLockRule} must NOT
 * block this edit; the calculator's tax_locked branch keeps the tax figures frozen.
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
            ));

            ItemPriceOverridden::fire(
                opportunity_item_id: $item->state_id,
                unit_price: $data->unit_price,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
