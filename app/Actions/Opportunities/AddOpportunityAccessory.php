<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\AddOpportunityAccessoryData;
use App\Data\Opportunities\OpportunityData;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Opportunities\ItemTreeService;
use App\Services\SequenceAllocator;
use App\Services\Shortages\ItemShortageProbe;
use App\Verbs\Events\Opportunities\ItemAdded;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Adds an accessory line beneath an existing product (principal) line via the
 * ItemAdded genesis event.
 *
 * An accessory is product-backed — {@see ItemAdded} prices it via the rate engine
 * and syncs availability demand. It inherits the principal's quote-version scope
 * and nests directly beneath it. The accessory-lock rule is enforced here: the
 * principal MUST be a product row, otherwise a {@see ValidationException} is thrown
 * and no row is created.
 */
class AddOpportunityAccessory
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, AddOpportunityAccessoryData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $principal = OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->whereKey($data->principal_item_id)
            ->firstOrFail();

        if ($principal->item_type !== OpportunityItemType::Product) {
            throw ValidationException::withMessages([
                'principal_item_id' => 'Accessories can only be added under a product line.',
            ]);
        }

        // The accessory inherits the principal's version scope so it always lands in
        // the same quote version as the product it hangs under.
        $versionId = $principal->version_id;
        $accessoryId = null;

        $this->commitVerbs(function () use ($opportunity, $data, $principal, $versionId, &$accessoryId): void {
            $accessoryId = app(SequenceAllocator::class)->next('opportunity_items');

            $path = app(ItemTreeService::class)->nextChildPath($opportunity->id, $versionId, $principal->path);

            ItemAdded::fire(
                opportunity_item_id: $accessoryId,
                opportunity_id: $opportunity->id,
                version_id: $versionId,
                itemable_id: $data->itemable_id,
                itemable_type: $data->itemable_type,
                item_type: OpportunityItemType::Accessory->value,
                path: $path,
                name: $data->name,
                quantity: $data->quantity,
            );
        });

        $fresh = $opportunity->fresh(['items']);

        if ($accessoryId !== null && $fresh !== null) {
            $item = $fresh->items->firstWhere('id', $accessoryId);

            if ($item instanceof OpportunityItem) {
                app(ItemShortageProbe::class)->probe($item);
            }
        }

        return OpportunityData::fromModel($fresh ?? $opportunity);
    }
}
