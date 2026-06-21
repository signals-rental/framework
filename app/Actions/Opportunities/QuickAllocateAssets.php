<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\QuickAllocateAssetsData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\AssetAllocated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
            // Track the running allocation count per line within this batch. The
            // per-event AssetAllocated::validate() cannot see in-flight siblings
            // (their projection rows are written only after commit), so the
            // over-allocation count guard is enforced here across the whole batch.
            $batchCounts = [];

            // Pre-fetch the already-allocated count for every targeted line in a
            // single grouped query (keyed by line id), instead of a COUNT() per
            // iteration. The in-batch running count is added on top below.
            $itemIds = array_map(static fn (array $allocation): int => (int) $allocation['opportunity_item_id'], $data->allocations);

            $existingCounts = OpportunityItemAsset::query()
                ->selectRaw('opportunity_item_id, COUNT(*) as cnt')
                ->whereIn('opportunity_item_id', $itemIds)
                ->groupBy('opportunity_item_id')
                ->pluck('cnt', 'opportunity_item_id');

            foreach ($data->allocations as $allocation) {
                $item = OpportunityItem::query()->whereKey($allocation['opportunity_item_id'])->first();

                if ($item === null || $item->opportunity_id !== $opportunity->id) {
                    throw new NotFoundHttpException('A line item in the batch does not belong to the opportunity.');
                }

                $alreadyAllocated = (int) ($existingCounts[$item->id] ?? 0);

                $batchCounts[$item->id] = ($batchCounts[$item->id] ?? 0) + 1;

                if ($alreadyAllocated + $batchCounts[$item->id] > (int) ceil((float) $item->quantity)) {
                    throw ValidationException::withMessages([
                        'allocations' => __('Allocating these assets would exceed line item :id\'s quantity.', ['id' => $item->id]),
                    ]);
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
