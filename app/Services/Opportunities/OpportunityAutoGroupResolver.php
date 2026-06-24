<?php

namespace App\Services\Opportunities;

use App\Enums\LineItemTransactionType;
use App\Models\OpportunityItem;
use App\Models\Product;

/**
 * Resolves the revenue-group classification + display label for an opportunity
 * line item, using the linked product's revenue-group assignment and
 * parent-group → product-group tree.
 *
 * This is the single source of truth for revenue-group bucketing, shared by:
 *  - {@see AddOpportunityItem} when no explicit `revenue_group_id` is supplied,
 *  - the line-item editor's group labelling, and
 *  - any future consumer that needs to bucket a line into its revenue group.
 *
 * The label mirrors the historical render-time bucketing exactly:
 *  - non-product / unlinked lines -> null revenue group ("Other")
 *  - product with no catalogue group -> revenue group from the product row ("Ungrouped")
 *  - product with a catalogue group -> revenue group from the product row
 *    (parent · group, or group)
 */
class OpportunityAutoGroupResolver
{
    /**
     * Resolve the revenue-group id + display label for a line item.
     *
     * `$products` is an optional id-keyed cache of pre-loaded Product models (with
     * `productGroup.parent` eager-loaded) so a bulk caller can avoid N+1 queries.
     * When a needed product is absent from the cache it is loaded on demand.
     *
     * @param  array<int, Product>  $products
     * @return array{0: int|null, 1: string}
     */
    public function resolve(OpportunityItem $item, array $products = []): array
    {
        if ($item->itemable_id === null || $item->itemable_type !== Product::class) {
            return [null, __('Other')];
        }

        $product = $products[$item->itemable_id] ?? Product::query()
            ->with('productGroup.parent')
            ->find($item->itemable_id);

        if ($product === null) {
            return [null, __('Other')];
        }

        $revenueGroupId = $item->transaction_type === LineItemTransactionType::Sale
            ? $product->sale_revenue_group_id
            : $product->rental_revenue_group_id;

        $group = $product->productGroup;

        if ($group === null) {
            return [$revenueGroupId, __('Ungrouped')];
        }

        $parent = $group->parent;
        $label = $parent !== null ? $parent->name.' · '.$group->name : $group->name;

        return [$revenueGroupId, $label];
    }

    /**
     * Legacy auto-group section key for the pre-unified section backfill and the
     * current items editor until P5 replaces it.
     *
     * @param  array<int, Product>  $products
     * @return array{0: string, 1: string}
     */
    public function resolveLegacySectionKey(OpportunityItem $item, array $products = []): array
    {
        if ($item->itemable_id === null || $item->itemable_type !== Product::class) {
            return ['auto:other', __('Other')];
        }

        if (! array_key_exists($item->itemable_id, $products)) {
            $products[$item->itemable_id] = Product::query()
                ->with('productGroup.parent')
                ->find($item->itemable_id);
        }

        $product = $products[$item->itemable_id];

        if ($product === null) {
            return ['auto:other', __('Other')];
        }

        [, $label] = $this->resolve($item, $products);

        $group = $product->productGroup;

        if ($group === null) {
            return ['auto:ungrouped', $label];
        }

        return ['auto:'.$group->id, $label];
    }
}
