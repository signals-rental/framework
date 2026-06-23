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
 * Sets a single line item's structural `path` within the opportunity's item tree.
 *
 * WHY EVENT-SOURCED: `path` is part of the event-sourced item state — {@see
 * ItemAdded::apply()} writes it onto the {@see OpportunityItemState}, so a Verbs
 * replay re-applies the ORIGINAL value. A plain `update()` to
 * `opportunity_items.path` would therefore be silently reverted on the next replay.
 * Path mutations must flow through the event stream so the new tree position
 * survives replay, exactly like every other item-state mutation. (Contrast {@see
 * AssignItemToSection}: `section_id` is deliberately NON-event-sourced, so it
 * stays a plain write — `path` is the opposite and must stay in the stream.)
 *
 * AGGREGATE MODEL: the item aggregate is per-item — every item event targets ONE
 * {@see OpportunityItemState} via the `#[StateId]` attribute and Verbs folds it
 * onto exactly that one state. A single "batch" event cannot replay-correctly
 * `apply()` to N distinct item states, so a restructure fires one of these per
 * item (mirroring {@see ItemSortOrderChanged} et al.). Each event re-applies its
 * own item's path from its own payload on replay — fully replay-stable.
 *
 * AUDIT: `path` affects neither pricing nor availability demand, so this event
 * performs NO reprice/roll-up and NO demand sync. To keep a restructure as ONE
 * audit/webhook record (not N), only the action-designated anchor event
 * (`emit_audit = true`, carrying the full ordered path list) records the
 * `opportunity.items_restructured` audit. The flag lives in the payload, so the
 * single audit row reproduces deterministically on replay (and the audit bridge
 * additionally dedups on the event's snowflake id).
 */
class ItemsRestructured extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    /**
     * @param  list<array{id:int,path:string}>|null  $ordered_paths  full ordered tree, carried only on the audit anchor
     */
    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public string $path = '',
        public bool $emit_audit = false,
        public ?array $ordered_paths = null,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->path = $this->path;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $item->forceFill(['path' => $state->path])->saveQuietly();

        if (! $this->emit_audit) {
            return;
        }

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.items_restructured',
                newValues: ['paths' => $this->ordered_paths ?? []],
                oldValues: null,
            );
        }
    }
}
