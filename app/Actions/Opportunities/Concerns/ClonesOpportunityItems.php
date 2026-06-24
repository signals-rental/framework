<?php

namespace App\Actions\Opportunities\Concerns;

use App\Actions\Opportunities\AddOpportunityItem;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;

/**
 * Shared line-item cloning helpers for the actions that copy a source line into a
 * new add-item payload — {@see App\Actions\Opportunities\CloneOpportunity} (clones
 * into a new Draft) and {@see App\Actions\Opportunities\CreateVersion} (clones into
 * a new version scope).
 *
 * Both route through the standard {@see App\Actions\Opportunities\AddOpportunityItem}
 * flow so the clone's demands and totals rebuild from the same pricing pipeline.
 *
 * Requires {@see FormatsOpportunityDates} for {@see FormatsOpportunityDates::toIso()}.
 */
trait ClonesOpportunityItems
{
    use FormatsOpportunityDates;

    /**
     * Copy a source line item into an add-item payload, optionally scoped to a
     * specific version. A null `$versionId` lets {@see AddOpportunityItem} resolve
     * the scope (the opportunity's active version, or none) exactly as omitting the
     * key would. A product-backed line reprices from the rate engine on the clone; a
     * manual/no-product line carries its manual price through.
     */
    protected function itemDataFrom(OpportunityItem $item, ?int $versionId = null): AddOpportunityItemData
    {
        return AddOpportunityItemData::from([
            'name' => $item->name,
            'itemable_id' => $item->itemable_id,
            'itemable_type' => $item->itemable_type,
            'item_type' => $item->item_type->value,
            'parent_path' => $item->parentPath(),
            'revenue_group_id' => $item->revenue_group_id,
            'description' => $item->description,
            'quantity' => (string) $item->quantity,
            'transaction_type' => $item->transaction_type->value,
            'charge_period' => $item->charge_period->value,
            'starts_at' => $this->toIso($item->starts_at),
            'ends_at' => $this->toIso($item->ends_at),
            'is_optional' => $item->is_optional,
            'discount_percent' => $item->discount_percent,
            'notes' => $item->notes,
            'custom_fields' => $item->custom_fields,
            'currency' => $item->currency_code ?? 'GBP',
            // Already-minor units: an int passes straight through the MoneyInput cast.
            'unit_price' => $this->manualUnitPrice($item),
            'version_id' => $versionId,
            'materialize_included_accessories' => false,
        ]);
    }

    /**
     * The source line's MANUAL price override, if one was set. An item priced by the
     * rate engine carries no manual override, so the clone must NOT pin its resolved
     * unit_price — it returns null so the clone reprices from the rate engine. A line
     * with NO product reference can never be rate-priced, so its unit_price is
     * inherently manual and must be carried to the clone.
     */
    protected function manualUnitPrice(OpportunityItem $item): ?int
    {
        if ($item->itemable_id === null) {
            return $item->unit_price !== 0 ? $item->unit_price : null;
        }

        return null;
    }

    /**
     * Clone one source line onto an opportunity, remapping `parent_path` through
     * the growing old-path → new-path map so gapped source trees (from removals
     * that do not recompact siblings) still nest correctly on the target.
     *
     * @param  array<string, string>  $pathMap
     */
    protected function cloneItemWithPathRemap(
        Opportunity $opportunity,
        OpportunityItem $sourceItem,
        array &$pathMap,
        ?int $versionId = null,
    ): void {
        $sourceParentPath = $sourceItem->parentPath();
        $remappedParentPath = $sourceParentPath === null
            ? null
            : ($pathMap[$sourceParentPath] ?? $sourceParentPath);

        $payload = $this->itemDataFrom($sourceItem, $versionId)->toArray();
        $payload['parent_path'] = $remappedParentPath;

        $existingIds = $opportunity->allItems()->pluck('id')->all();

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from($payload));

        /** @var OpportunityItem $newItem */
        $newItem = $opportunity->fresh()->allItems()
            ->whereNotIn('id', $existingIds)
            ->sole();

        $pathMap[$sourceItem->path] = $newItem->path;
    }
}
