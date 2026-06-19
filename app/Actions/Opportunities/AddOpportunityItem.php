<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Services\SequenceAllocator;
use App\Services\Shortages\ItemShortageProbe;
use App\Verbs\Events\Opportunities\ItemAdded;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Adds a line item to an opportunity via the ItemAdded genesis event, allocating
 * the replay-stable item id, firing the event, and committing it with its
 * projection atomically.
 *
 * After the event commits, the {@see ItemShortageProbe} runs a cheap inline
 * shortage check on the new line (shortage-resolution-sub-hires.md §2.4),
 * emitting `shortage.detected` when the line is short. The probe never blocks the
 * add and is skipped during replay.
 */
class AddOpportunityItem
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, AddOpportunityItemData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        // Resolve the effective hire window NOW (item dates ?? opportunity dates ??
        // a single captured fire-time now()) and bake CONCRETE dates into the event
        // so the totals engine never calls now() in handle(): a dateless rate-priced
        // line carries a concrete window on its projection row, keeping replay
        // totals identical. See OpportunityTotalsCalculator::effectiveDates().
        $now = Carbon::now('UTC');
        $startsAt = $data->starts_at
            ?? $this->toIso($opportunity->starts_at)
            ?? $now->toIso8601String();
        $endsAt = $data->ends_at
            ?? $this->toIso($opportunity->ends_at)
            ?? Carbon::parse($startsAt)->copy()->addDay()->toIso8601String();

        $itemId = null;

        $this->commitVerbs(function () use ($opportunity, $data, $startsAt, $endsAt, &$itemId): void {
            // Allocate the replay-stable small PK and bake it into the event so a
            // truncate + Verbs::replay() rebuild reproduces the identical id.
            $itemId = app(SequenceAllocator::class)->next('opportunity_items');

            ItemAdded::fire(
                opportunity_item_id: $itemId,
                opportunity_id: $opportunity->id,
                item_id: $data->item_id,
                item_type: $data->item_type,
                name: $data->name,
                description: $data->description,
                quantity: $data->quantity,
                transaction_type: $data->transaction_type,
                charge_period: $data->charge_period,
                starts_at: $startsAt,
                ends_at: $endsAt,
                is_optional: $data->is_optional,
                manual_unit_price: $data->unit_price,
                discount_percent: $data->discount_percent,
                sort_order: $data->sort_order,
                custom_fields: $data->custom_fields,
                notes: $data->notes,
            );
        });

        $fresh = $opportunity->fresh(['items']);

        // Inline shortage detection (§2.4) on the newly-added line. Cheap, never
        // blocks the add, replay-guarded inside the probe.
        if ($itemId !== null && $fresh !== null) {
            $item = $fresh->items->firstWhere('id', $itemId);

            if ($item !== null) {
                app(ItemShortageProbe::class)->probe($item);
            }
        }

        return OpportunityData::fromModel($fresh ?? $opportunity);
    }

    private function toIso(?\DateTimeInterface $value): ?string
    {
        return $value !== null ? Carbon::parse($value)->toIso8601String() : null;
    }
}
