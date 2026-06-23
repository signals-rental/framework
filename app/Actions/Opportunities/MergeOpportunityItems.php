<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\MergeOpportunityItemsData;
use App\Data\Opportunities\OpportunityData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemQuantityChanged;
use App\Verbs\Events\Opportunities\ItemRemoved;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Merges duplicate line items into a single surviving line: the survivor's quantity
 * becomes the sum of itself and every merged duplicate, and the duplicates are
 * removed.
 *
 * Replay-safe by construction — it fires only the existing
 * {@see ItemQuantityChanged} and {@see ItemRemoved} events (no new event type), all
 * within one atomic {@see CommitsVerbsEvents} boundary. The fulfilment guards on
 * {@see ItemRemoved::validate()} (allocated assets / dispatched units) still apply
 * to each duplicate, so a partially-fulfilled duplicate cannot be silently dropped.
 *
 * Two lines are mergeable only when they are genuinely the same charge: same
 * product (item_id + item_type), same transaction type + charge period, same hire
 * window, same section, and same optional flag. A mismatch is rejected rather than
 * collapsing distinct lines and losing their pricing/dates.
 */
class MergeOpportunityItems
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $survivor, MergeOpportunityItemsData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $survivor->opportunity()->firstOrFail();

        $duplicates = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->whereIn('id', $data->duplicate_item_ids)
            ->whereKeyNot($survivor->id)
            ->get();

        if ($duplicates->isEmpty()) {
            throw ValidationException::withMessages([
                'duplicate_item_ids' => 'No matching duplicate line items were found to merge.',
            ]);
        }

        foreach ($duplicates as $duplicate) {
            if (! $this->isMergeable($survivor, $duplicate)) {
                throw ValidationException::withMessages([
                    'duplicate_item_ids' => 'Only identical lines (same product, dates, charge type and section) can be merged.',
                ]);
            }
        }

        $mergedQuantity = (float) $survivor->quantity
            + $duplicates->sum(fn (OpportunityItem $item): float => (float) $item->quantity);

        $this->commitVerbs(function () use ($survivor, $duplicates, $mergedQuantity): void {
            // Remove the duplicates first so their availability demand is released
            // before the survivor absorbs their quantity (avoids briefly
            // double-counting demand on the survivor's window).
            foreach ($duplicates as $duplicate) {
                ItemRemoved::fire(opportunity_item_id: $duplicate->state_id);
            }

            ItemQuantityChanged::fire(
                opportunity_item_id: $survivor->state_id,
                quantity: (string) $mergedQuantity,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }

    /**
     * Two lines are the same charge when their product, transaction type, charge
     * period, hire window, section and optional flag all match.
     */
    private function isMergeable(OpportunityItem $survivor, OpportunityItem $duplicate): bool
    {
        return $survivor->itemable_id === $duplicate->itemable_id
            && $survivor->itemable_type === $duplicate->itemable_type
            && $survivor->getRawOriginal('transaction_type') === $duplicate->getRawOriginal('transaction_type')
            && $survivor->getRawOriginal('charge_period') === $duplicate->getRawOriginal('charge_period')
            && $survivor->section_id === $duplicate->section_id
            && $survivor->is_optional === $duplicate->is_optional
            && $this->sameInstant($survivor->starts_at, $duplicate->starts_at)
            && $this->sameInstant($survivor->ends_at, $duplicate->ends_at);
    }

    private function sameInstant(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return $a->equalTo($b);
    }
}
