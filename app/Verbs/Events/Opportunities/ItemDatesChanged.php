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
 * Changes a line item's per-item hire window. The firing action pre-resolves the
 * effective window (requested ?? opportunity ?? a single fire-time now()) and bakes
 * CONCRETE dates into the payload, so this event carries an explicit window and the
 * totals engine never derives a now()-based fallback in handle() — keeping replay
 * totals identical. Duration affects period-based pricing, so the line + parent
 * totals are recomputed; the availability demand window is resynced.
 */
class ItemDatesChanged extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->starts_at = $this->starts_at !== null ? CarbonImmutable::parse($this->starts_at) : null;
        $state->ends_at = $this->ends_at !== null ? CarbonImmutable::parse($this->ends_at) : null;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $oldValues = [
            'item_id' => $item->id,
            'starts_at' => $item->starts_at?->toIso8601String(),
            'ends_at' => $item->ends_at?->toIso8601String(),
        ];

        $item->forceFill([
            'starts_at' => $state->starts_at,
            'ends_at' => $state->ends_at,
        ])->saveQuietly();

        $this->repriceAndRollUp($item, $state->manual_unit_price);
        $this->syncDemand($item);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $item->refresh();

            $this->recordAudit(
                $opportunity,
                'opportunity.item_dates_changed',
                newValues: [
                    'item_id' => $item->id,
                    'starts_at' => $item->starts_at?->toIso8601String(),
                    'ends_at' => $item->ends_at?->toIso8601String(),
                    'total' => $item->total,
                ],
                oldValues: $oldValues,
            );
        }
    }
}
