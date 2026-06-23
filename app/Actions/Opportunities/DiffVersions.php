<?php

namespace App\Actions\Opportunities;

use App\Data\Opportunities\VersionDiffData;
use App\Data\Opportunities\VersionDiffItemData;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Services\CurrencyService;
use Brick\Money\Currency;
use Brick\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Computes the item-level content diff between two versions of the SAME
 * opportunity (opportunity-lifecycle.md §8.11).
 *
 * Lines are matched by product AND per-product occurrence index (so two distinct
 * lines for the same product still match positionally across versions rather than
 * collapsing into one); ad-hoc lines (no product) are matched by a synthetic key so
 * they still appear. The diff is computed on-demand from the
 * projections (never stored): items ADDED (in target, not source), REMOVED (in
 * source, not target), CHANGED (quantity / unit_price / discount_percent differ),
 * and the net change in total value. Money figures are decimal strings (RMS).
 *
 * Both versions must belong to the same opportunity, else a 422.
 */
class DiffVersions
{
    public function __invoke(OpportunityVersion $source, OpportunityVersion $target): VersionDiffData
    {
        Gate::authorize('opportunities.view');

        if ($source->opportunity_id !== $target->opportunity_id) {
            throw ValidationException::withMessages([
                'versions' => ['Both versions must belong to the same opportunity.'],
            ]);
        }

        // The opportunity's currency (both versions share one opportunity) formats
        // every money figure; defaults to the company base currency, never a
        // hardcoded literal.
        $currencyCode = $source->opportunity->currency_code
            ?? app(CurrencyService::class)->baseCurrencyCode();

        $sourceItems = $this->keyItems($source->items()->get());
        $targetItems = $this->keyItems($target->items()->get());

        $added = [];
        $removed = [];
        $changed = [];

        foreach ($targetItems as $key => $targetItem) {
            if (! isset($sourceItems[$key])) {
                $added[] = $this->addedLine($targetItem, $currencyCode);
            }
        }

        foreach ($sourceItems as $key => $sourceItem) {
            if (! isset($targetItems[$key])) {
                $removed[] = $this->removedLine($sourceItem, $currencyCode);

                continue;
            }

            $targetItem = $targetItems[$key];

            if ($this->hasChanged($sourceItem, $targetItem)) {
                $changed[] = $this->changedLine($sourceItem, $targetItem, $currencyCode);
            }
        }

        $sourceTotal = $this->sumTotals($sourceItems);
        $targetTotal = $this->sumTotals($targetItems);

        return new VersionDiffData(
            source_version_id: $source->id,
            target_version_id: $target->id,
            source_version_number: $source->version_number,
            target_version_number: $target->version_number,
            added: $added,
            removed: $removed,
            changed: $changed,
            source_total: $this->money($sourceTotal, $currencyCode),
            target_total: $this->money($targetTotal, $currencyCode),
            net_change: $this->money($targetTotal - $sourceTotal, $currencyCode),
        );
    }

    /**
     * Key the items so the same line matches across versions without collapsing
     * distinct lines:
     *
     * - Product lines are keyed by `product:{item_type}:{item_id}:#{occurrence}`,
     *   where `occurrence` is the running count of that product seen so far in this
     *   version (items are already ordered by `sort_order`). Two lines for the same
     *   product therefore get distinct keys (#0, #1) and match positionally across
     *   versions, instead of overwriting each other.
     * - Ad-hoc lines (no product) fall back to a per-id key so they always appear as
     *   added/removed rather than collapsing together.
     *
     * @param  Collection<int, OpportunityItem>  $items
     * @return array<string, OpportunityItem>
     */
    private function keyItems($items): array
    {
        $keyed = [];

        /** @var array<string, int> $occurrences */
        $occurrences = [];

        foreach ($items as $item) {
            if ($item->itemable_id !== null) {
                $productKey = "{$item->itemable_type}:{$item->itemable_id}";
                $occurrence = $occurrences[$productKey] ?? 0;
                $occurrences[$productKey] = $occurrence + 1;

                $key = "product:{$productKey}:#{$occurrence}";
            } else {
                $key = "adhoc:{$item->id}";
            }

            $keyed[$key] = $item;
        }

        return $keyed;
    }

    private function hasChanged(OpportunityItem $source, OpportunityItem $target): bool
    {
        return bccomp((string) $source->quantity, (string) $target->quantity, 4) !== 0
            || $source->unit_price !== $target->unit_price
            || bccomp((string) ($source->discount_percent ?? '0'), (string) ($target->discount_percent ?? '0'), 4) !== 0
            || $source->total !== $target->total;
    }

    private function addedLine(OpportunityItem $item, string $currencyCode): VersionDiffItemData
    {
        return new VersionDiffItemData(
            item_id: $item->itemable_id,
            item_type: $item->itemable_type,
            name: $item->name,
            source_quantity: null,
            target_quantity: (string) $item->quantity,
            source_unit_price: null,
            target_unit_price: $this->money($item->unit_price, $currencyCode),
            source_discount_percent: null,
            target_discount_percent: $item->discount_percent !== null ? (string) $item->discount_percent : null,
            source_total: null,
            target_total: $this->money($item->total, $currencyCode),
            total_delta: $this->money($item->total, $currencyCode),
        );
    }

    private function removedLine(OpportunityItem $item, string $currencyCode): VersionDiffItemData
    {
        return new VersionDiffItemData(
            item_id: $item->itemable_id,
            item_type: $item->itemable_type,
            name: $item->name,
            source_quantity: (string) $item->quantity,
            target_quantity: null,
            source_unit_price: $this->money($item->unit_price, $currencyCode),
            target_unit_price: null,
            source_discount_percent: $item->discount_percent !== null ? (string) $item->discount_percent : null,
            target_discount_percent: null,
            source_total: $this->money($item->total, $currencyCode),
            target_total: null,
            total_delta: $this->money(-$item->total, $currencyCode),
        );
    }

    private function changedLine(OpportunityItem $source, OpportunityItem $target, string $currencyCode): VersionDiffItemData
    {
        return new VersionDiffItemData(
            item_id: $target->itemable_id,
            item_type: $target->itemable_type,
            name: $target->name,
            source_quantity: (string) $source->quantity,
            target_quantity: (string) $target->quantity,
            source_unit_price: $this->money($source->unit_price, $currencyCode),
            target_unit_price: $this->money($target->unit_price, $currencyCode),
            source_discount_percent: $source->discount_percent !== null ? (string) $source->discount_percent : null,
            target_discount_percent: $target->discount_percent !== null ? (string) $target->discount_percent : null,
            source_total: $this->money($source->total, $currencyCode),
            target_total: $this->money($target->total, $currencyCode),
            total_delta: $this->money($target->total - $source->total, $currencyCode),
        );
    }

    /**
     * @param  array<string, OpportunityItem>  $items
     */
    private function sumTotals(array $items): int
    {
        $sum = 0;

        foreach ($items as $item) {
            if (! $item->is_optional) {
                $sum += (int) $item->total;
            }
        }

        return $sum;
    }

    /**
     * Format minor units as a signed decimal string (RMS money format) at the
     * opportunity currency's natural scale (JPY 0dp, GBP 2dp, KWD 3dp).
     */
    private function money(int $minor, string $currencyCode): string
    {
        $currency = Currency::of($currencyCode);

        return (string) Money::ofMinor($minor, $currency)
            ->getAmount()
            ->toScale($currency->getDefaultFractionDigits());
    }
}
