<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\OpportunityItemData;
use App\Enums\ShortageDispatchPolicy;
use App\Models\OpportunityItem;
use App\Services\Shortages\DispatchShortageGate;
use App\ValueObjects\DispatchGateResult;
use App\Verbs\Events\Opportunities\BulkQuantityDispatched;
use Illuminate\Support\Facades\Gate;

/**
 * Records a (partial) dispatch of a bulk line via the BulkQuantityDispatched
 * event.
 *
 * Before firing, the {@see DispatchShortageGate} enforces the store's
 * {@see ShortageDispatchPolicy} on the line (§7.4): Block → 422;
 * WarnPartial → proceed and expose held-item metadata via {@see $gateResult};
 * AllowPartial → proceed silently. The gate runs inside the atomic commit.
 */
class DispatchBulkQuantity
{
    use CommitsVerbsEvents;

    /**
     * The dispatch-gate outcome from the last invocation — the controller reads it
     * to surface held-item metadata on a WarnPartial dispatch. Null until invoked.
     */
    public ?DispatchGateResult $gateResult = null;

    public function __invoke(OpportunityItem $item, BulkDispatchData $data): OpportunityItemData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($item, $data): void {
            // §7.4 dispatch gate — Block throws (rolling back); Warn/Allow proceed.
            $this->gateResult = app(DispatchShortageGate::class)->enforceForItem($item);

            BulkQuantityDispatched::fire(
                opportunity_item_id: $item->state_id,
                quantity: $data->quantity,
                dispatched_by: $data->dispatched_by,
            );
        });

        return OpportunityItemData::fromModel($item->fresh() ?? $item);
    }
}
