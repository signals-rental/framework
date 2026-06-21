<?php

namespace App\Actions\Opportunities\Concerns;

use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves a serialised asset-assignment row and asserts it belongs to a line item
 * of the bound opportunity.
 *
 * Shared by the batch Quick* asset actions ({@see App\Actions\Opportunities\QuickBookOut},
 * {@see App\Actions\Opportunities\QuickCheckIn},
 * {@see App\Actions\Opportunities\QuickPrepareAssets}) so a foreign asset in a batch
 * aborts the whole atomic commit with a 404. The asset's `item` relation is
 * eager-loaded so callers can read it without an extra query.
 */
trait ResolvesOpportunityAssets
{
    /**
     * Resolve a single asset assignment, guarding that it belongs to the bound
     * opportunity. Throws a 404 when the asset is missing, has no line item, or
     * belongs to a different opportunity.
     */
    protected function assetForOpportunity(int $assetId, Opportunity $opportunity): OpportunityItemAsset
    {
        $asset = OpportunityItemAsset::query()->whereKey($assetId)->with('item')->first();

        if ($asset === null || $asset->item === null || $asset->item->opportunity_id !== $opportunity->id) {
            throw new NotFoundHttpException('An asset in the batch does not belong to the opportunity.');
        }

        return $asset;
    }
}
