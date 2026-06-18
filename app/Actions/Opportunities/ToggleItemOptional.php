<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\ToggleItemOptionalData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemOptionalToggled;
use Illuminate\Support\Facades\Gate;

/**
 * Toggles whether a line item is optional via the ItemOptionalToggled event.
 */
class ToggleItemOptional
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, ToggleItemOptionalData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data): void {
            ItemOptionalToggled::fire(
                opportunity_item_id: $item->state_id,
                is_optional: $data->is_optional,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}
