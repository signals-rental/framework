<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityItems;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Substitutes the catalogue item a line refers to (swaps the polymorphic
 * product reference and display name). Repricing resolves the new product's rate
 * + tax; the availability demand is resynced against the new product.
 */
class ItemSubstituted extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public ?int $item_id = null,
        public ?string $item_type = null,
        public ?string $name = null,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);

        // Substitution swaps the product reference, so it must not strand committed
        // fulfilment of the OLD product. Mirroring the {@see ItemRemoved} guard: a
        // line with serialised assets still allocated to it, or with bulk quantity
        // already dispatched, cannot be substituted — the assets must be deallocated
        // / returned first. Both are read from the projection (validate() runs before
        // handle(), so the rows reflect the pre-substitution state) and replay-safely
        // reproduce.
        $allocatedAssets = OpportunityItemAsset::query()
            ->where('opportunity_item_id', $state->opportunity_item_id)
            ->count();

        $this->assert(
            $allocatedAssets === 0,
            sprintf(
                'Cannot substitute a line with %d allocated asset(s); deallocate first.',
                $allocatedAssets,
            ),
        );

        $dispatched = BigDecimal::of((string) $state->dispatched_quantity);

        $this->assert(
            $dispatched->isZero(),
            sprintf(
                'Cannot substitute a line with %s already-dispatched unit(s); return them first.',
                (string) $dispatched->toScale(0, RoundingMode::DOWN),
            ),
        );
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->itemable_id = $this->item_id;
        $state->itemable_type = $this->item_type;

        if ($this->name !== null) {
            $state->name = $this->name;
        }

        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $oldValues = ['item_id' => $item->id, 'product_id' => $item->itemable_id, 'name' => $item->name];

        $item->forceFill([
            'itemable_id' => $state->itemable_id,
            'itemable_type' => $state->itemable_type,
            'name' => $state->name,
        ])->saveQuietly();

        $this->repriceAndRollUp($item, $state->manual_unit_price);
        $this->syncDemand($item);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $item->refresh();

            $this->recordAudit(
                $opportunity,
                'opportunity.item_substituted',
                newValues: ['item_id' => $item->id, 'product_id' => $item->itemable_id, 'name' => $item->name, 'total' => $item->total],
                oldValues: $oldValues,
            );
        }
    }
}
