<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\QuickAllocateAssetsData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\AssetAllocated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Batch-allocates several serialised assets to line items of one opportunity in a
 * SINGLE atomic Verbs commit (the RMS `quick_allocate` action).
 *
 * Every {@see AssetAllocated} event is fired inside one
 * {@see CommitsVerbsEvents::commitVerbs()} boundary, so a validation failure on any
 * one allocation (asset unavailable, wrong product, etc.) rolls back the whole
 * batch — no partial allocation can be left behind.
 *
 * All allocations must target line items of the bound opportunity; any allocation
 * referencing a foreign line item aborts the batch with a 404.
 */
class QuickAllocateAssets
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, QuickAllocateAssetsData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $allocatedAt = Carbon::now('UTC')->toIso8601String();

        $this->commitVerbs(function () use ($opportunity, $data, $allocatedAt): void {
            foreach ($data->allocations as $allocation) {
                $item = OpportunityItem::query()->whereKey($allocation['opportunity_item_id'])->first();

                if ($item === null || $item->opportunity_id !== $opportunity->id) {
                    throw new NotFoundHttpException('A line item in the batch does not belong to the opportunity.');
                }

                $assignmentId = app(SequenceAllocator::class)->next('opportunity_item_assets');

                AssetAllocated::fire(
                    assignment_id: $assignmentId,
                    opportunity_item_id: $item->id,
                    stock_level_id: $allocation['stock_level_id'],
                    allocated_at: $allocatedAt,
                );
            }
        });

        return OpportunityData::fromModel(
            $opportunity->fresh(['items', 'items.assets']) ?? $opportunity,
        );
    }
}
