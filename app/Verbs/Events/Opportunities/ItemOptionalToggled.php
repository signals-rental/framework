<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityItems;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Toggles whether a line item is optional. Optional lines are EXCLUDED from the
 * opportunity's charge totals, so toggling re-rolls the parent totals. Demands
 * are resynced (idempotent) — optional lines still claim availability.
 */
class ItemOptionalToggled extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public bool $is_optional = false,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->is_optional = $this->is_optional;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $oldOptional = (bool) $item->is_optional;

        $item->forceFill(['is_optional' => $state->is_optional])->saveQuietly();

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $this->rollUpOnly($opportunity);
        }

        $this->syncDemand($item);

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.item_optional_toggled',
                newValues: ['item_id' => $item->id, 'is_optional' => $state->is_optional],
                oldValues: ['item_id' => $item->id, 'is_optional' => $oldOptional],
            );
        }
    }
}
