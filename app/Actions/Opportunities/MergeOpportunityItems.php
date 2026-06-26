<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\MergeOpportunityItemsData;
use App\Data\Opportunities\OpportunityData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemDatesChanged;
use App\Verbs\Events\Opportunities\ItemDiscountSet;
use App\Verbs\Events\Opportunities\ItemPriceOverridden;
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
 * window, same parent path (same tree group), and same optional flag. A mismatch
 * is rejected rather than
 * collapsing distinct lines and losing their pricing/dates.
 */
class MergeOpportunityItems
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $survivor, MergeOpportunityItemsData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $survivor->opportunity()->firstOrFail();

        if ($opportunity->pricingFrozen()) {
            throw ValidationException::withMessages([
                'opportunity' => 'Line items cannot be edited while pricing is frozen.',
            ]);
        }

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
                    'duplicate_item_ids' => 'Only identical lines (same product, dates, charge type and tree group) can be merged.',
                ]);
            }
        }

        $mergedQuantity = (float) $survivor->quantity
            + $duplicates->sum(fn (OpportunityItem $item): float => (float) $item->quantity);

        $richest = collect([$survivor])->concat($duplicates)
            ->sortByDesc(fn (OpportunityItem $item): array => [
                (int) ($item->total ?? 0),
                (int) ($item->unit_price ?? 0),
            ])
            ->first();

        $this->commitVerbs(function () use ($survivor, $duplicates, $mergedQuantity, $richest): void {
            $this->applySurvivorPricingFrom($survivor, $richest);

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
     * period, hire window, parent path and optional flag all match.
     */
    private function isMergeable(OpportunityItem $survivor, OpportunityItem $duplicate): bool
    {
        return $survivor->itemable_id === $duplicate->itemable_id
            && $survivor->itemable_type === $duplicate->itemable_type
            && $survivor->getRawOriginal('transaction_type') === $duplicate->getRawOriginal('transaction_type')
            && $survivor->getRawOriginal('charge_period') === $duplicate->getRawOriginal('charge_period')
            && $survivor->parentPath() === $duplicate->parentPath()
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

    private function applySurvivorPricingFrom(OpportunityItem $survivor, OpportunityItem $source): void
    {
        if ((int) ($source->unit_price ?? 0) !== (int) ($survivor->unit_price ?? 0)) {
            ItemPriceOverridden::fire(
                opportunity_item_id: $survivor->state_id,
                unit_price: (int) ($source->unit_price ?? 0),
            );
        }

        $sourceDiscount = $source->discount_percent !== null ? (string) $source->discount_percent : null;
        $survivorDiscount = $survivor->discount_percent !== null ? (string) $survivor->discount_percent : null;

        if ($sourceDiscount !== $survivorDiscount) {
            ItemDiscountSet::fire(
                opportunity_item_id: $survivor->state_id,
                discount_percent: $sourceDiscount,
            );
        }

        if (! $this->sameInstant($source->starts_at, $survivor->starts_at)
            || ! $this->sameInstant($source->ends_at, $survivor->ends_at)) {
            ItemDatesChanged::fire(
                opportunity_item_id: $survivor->state_id,
                starts_at: $source->starts_at?->toIso8601String(),
                ends_at: $source->ends_at?->toIso8601String(),
            );
        }
    }
}
