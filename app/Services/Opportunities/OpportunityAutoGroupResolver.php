<?php

namespace App\Services\Opportunities;

use App\Models\OpportunityItem;
use App\Models\OpportunitySection;
use App\Models\Product;
use Database\Migrations;

/**
 * Resolves the automatic grouping key + label for an opportunity line item, using
 * the linked product's parent-group -> product-group tree.
 *
 * This is the single source of truth for auto-grouping, shared by:
 *  - the eager unification backfill ({@see Migrations} backfill),
 *  - the line-item editor's find-or-create-on-add path, and
 *  - any future consumer that needs to bucket a line into its auto group.
 *
 * Under the unified group model every auto-group is a real, persisted
 * {@see OpportunitySection} whose `auto_group_key` matches the key
 * returned here, so a line is found-or-created into its real section rather than
 * being bucketed only at render time.
 *
 * The keys mirror the historical render-time bucketing exactly:
 *  - non-product / unlinked lines -> "auto:other" ("Other")
 *  - product with no group        -> "auto:ungrouped" ("Ungrouped")
 *  - product with a group         -> "auto:{groupId}" (parent · group, or group)
 */
class OpportunityAutoGroupResolver
{
    /**
     * Resolve the auto-group key + display label for a line item.
     *
     * `$products` is an optional id-keyed cache of pre-loaded Product models (with
     * `productGroup.parent` eager-loaded) so a bulk caller can avoid N+1 queries.
     * When a needed product is absent from the cache it is loaded on demand.
     *
     * @param  array<int, Product>  $products
     * @return array{0: string, 1: string}
     */
    public function resolve(OpportunityItem $item, array $products = []): array
    {
        if ($item->itemable_id === null || $item->itemable_type !== Product::class) {
            return ['auto:other', __('Other')];
        }

        $product = $products[$item->itemable_id] ?? Product::query()
            ->with('productGroup.parent')
            ->find($item->itemable_id);

        $group = $product?->productGroup;

        if ($group === null) {
            return ['auto:ungrouped', __('Ungrouped')];
        }

        $parent = $group->parent;
        $label = $parent !== null ? $parent->name.' · '.$group->name : $group->name;

        return ['auto:'.$group->id, $label];
    }
}
