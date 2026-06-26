<?php

namespace App\Actions\Opportunities;

use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Opportunities\OpportunityEditorTreeService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Dissolves a custom group (section) row: promotes direct children, restructures,
 * then removes the empty group via {@see RemoveOpportunityItem}.
 */
class DissolveOpportunityGroup
{
    public function __construct(
        private OpportunityEditorTreeService $tree,
    ) {}

    public function __invoke(Opportunity $opportunity, OpportunityItem $group): void
    {
        Gate::authorize('opportunities.edit');

        if ($group->item_type !== OpportunityItemType::Group) {
            throw ValidationException::withMessages([
                'group' => 'The group could not be found.',
            ]);
        }

        if ($group->opportunity_id !== $opportunity->id) {
            throw ValidationException::withMessages([
                'group' => 'The group could not be found.',
            ]);
        }

        $opportunity = $opportunity->fresh(['items']) ?? $opportunity;
        $nodes = $this->tree->nodesAfterDissolvingGroup($opportunity->items, $group->id);
        $this->tree->restructure($opportunity, $nodes);
        (new RemoveOpportunityItem)($group->fresh());
    }
}
