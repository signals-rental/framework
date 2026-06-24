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
 * Renames any line-item row — a group header or a product/accessory/service line
 * (opportunity-lifecycle.md §5.1).
 *
 * WHY EVENT-SOURCED: `name` is part of the event-sourced item state — {@see
 * ItemAdded::apply()} writes it onto the {@see OpportunityItemState}, so a Verbs
 * replay re-applies the value from the stream. A plain `update()` to
 * `opportunity_items.name` would therefore be silently reverted on the next
 * replay; the rename must flow through the event stream so the new label survives.
 *
 * AUDIT: a rename affects neither pricing nor availability demand, so this event
 * performs NO reprice/roll-up and NO demand sync — it only re-projects the name
 * and records a single `opportunity.item_renamed` audit row on the parent
 * opportunity (the audit bridge additionally dedups on the event's snowflake id).
 */
class ItemRenamed extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public string $name = '',
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->name = $this->name;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $oldName = $item->name;

        $item->forceFill(['name' => $this->name])->saveQuietly();

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.item_renamed',
                newValues: ['name' => $this->name, 'item_id' => $item->id],
                oldValues: ['name' => $oldName, 'item_id' => $item->id],
            );
        }
    }
}
