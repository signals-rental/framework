<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\OpportunityData;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Services\Opportunities\ItemTreeService;
use App\Services\Opportunities\OpportunityEditorTreeService;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\ItemAdded;
use Illuminate\Support\Facades\Gate;

/**
 * Adds a structural group row to an opportunity via the ItemAdded genesis event.
 *
 * A group is a pure container: it carries no catalogue reference, no price, no
 * discount, and no dates — {@see ItemAdded} skips pricing and demand for the group
 * role, so this action leaves those event arguments at their defaults. The action
 * allocates the replay-stable item id and tree path inside the Verbs commit so a
 * truncate + replay reproduces the identical position.
 */
class AddOpportunityGroup
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, AddOpportunityGroupData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        app(OpportunityEditorTreeService::class)->assertCanNestUnder($data->parent_path);

        // When the opportunity carries an active quote version the new group lands
        // in that version's scope; a non-versioned opportunity keeps a NULL
        // version_id. A null override on the data wins (used by the version-clone
        // path to target a specific brand-new version).
        $versionId = $data->version_id ?? ($opportunity->active_version_id > 0 ? $opportunity->active_version_id : null);

        $this->commitVerbs(function () use ($opportunity, $data, $versionId): void {
            $groupId = app(SequenceAllocator::class)->next('opportunity_items');

            // Resolve the tree position inside the closure (after the id) so the path
            // is baked into the event and a Verbs replay reproduces it exactly.
            $tree = app(ItemTreeService::class);
            $path = $data->parent_path !== null
                ? $tree->nextChildPath($opportunity->id, $versionId, $data->parent_path)
                : $tree->nextTopLevelPath($opportunity->id, $versionId);

            ItemAdded::fire(
                opportunity_item_id: $groupId,
                opportunity_id: $opportunity->id,
                version_id: $versionId,
                item_type: OpportunityItemType::Group->value,
                path: $path,
                name: $data->name,
                custom_fields: $data->custom_fields,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']) ?? $opportunity);
    }
}
