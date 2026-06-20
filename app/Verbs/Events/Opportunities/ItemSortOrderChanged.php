<?php

namespace App\Verbs\Events\Opportunities;

use App\Actions\Opportunities\AssignItemToSection;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityItems;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Sets a single line item's display `sort_order`.
 *
 * WHY EVENT-SOURCED (the crux of M8-3d-i): `sort_order` is part of the
 * event-sourced item state — {@see ItemAdded::apply()} writes it onto the
 * {@see OpportunityItemState}, so a Verbs replay re-applies the ORIGINAL value. A
 * plain `update()` to `opportunity_items.sort_order` would therefore be silently
 * reverted on the next replay. Reordering must flow through the event stream so
 * the new order survives replay, exactly like every other item-state mutation.
 * (Contrast {@see AssignItemToSection}: `section_id` is
 * deliberately NON-event-sourced, so it stays a plain write — `sort_order` is the
 * opposite and must stay in the stream.)
 *
 * AGGREGATE MODEL: the item aggregate is per-item — every item event targets ONE
 * {@see OpportunityItemState} via the `#[StateId]` attribute and Verbs folds it
 * onto exactly that one state. A single "batch" event cannot replay-correctly
 * `apply()` to N distinct item states, so a reorder fires one of these per item
 * (mirroring {@see ItemQuantityChanged} et al.). Each event re-applies its own
 * item's sort_order from its own payload on replay — fully replay-stable.
 *
 * AUDIT: `sort_order` affects neither pricing nor availability demand, so this
 * event performs NO reprice/roll-up and NO demand sync. To keep a reorder as ONE
 * audit/webhook record (not N), only the action-designated anchor event
 * (`emit_audit = true`, carrying the full ordered id list) records the
 * `opportunity.items_reordered` audit. The flag lives in the payload, so the
 * single audit row reproduces deterministically on replay (and the audit bridge
 * additionally dedups on the event's snowflake id).
 */
class ItemSortOrderChanged extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    /**
     * @param  list<int>|null  $ordered_item_ids  the full ordered id list, carried only on the audit anchor
     */
    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public int $sort_order = 0,
        public bool $emit_audit = false,
        public ?array $ordered_item_ids = null,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->sort_order = $this->sort_order;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $item->forceFill(['sort_order' => $state->sort_order])->saveQuietly();

        if (! $this->emit_audit) {
            return;
        }

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.items_reordered',
                newValues: ['item_ids' => $this->ordered_item_ids ?? []],
                oldValues: null,
            );
        }
    }
}
