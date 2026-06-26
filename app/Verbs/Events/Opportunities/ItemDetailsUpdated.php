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
 * Updates a line item's customer-facing description and warehouse notes.
 *
 * Neither field affects pricing or availability demand — projection-only with
 * audit on the parent opportunity.
 */
class ItemDetailsUpdated extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public ?string $description = null,
        public ?string $notes = null,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->description = $this->description;
        $state->notes = $this->notes;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $oldDescription = $item->description;
        $oldNotes = $item->notes;

        $item->forceFill([
            'description' => $this->description,
            'notes' => $this->notes,
        ])->saveQuietly();

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.item_details_updated',
                newValues: [
                    'item_id' => $item->id,
                    'description' => $this->description,
                    'notes' => $this->notes,
                ],
                oldValues: [
                    'item_id' => $item->id,
                    'description' => $oldDescription,
                    'notes' => $oldNotes,
                ],
            );
        }
    }
}
