<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\Concerns\GuardsAssetAssignment;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\AssetAssignmentState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Removes an asset from its kit/case container (opportunity-lifecycle.md §5.4).
 * Valid only when a container is currently set.
 */
class AssetContainerCleared extends Event
{
    use GuardsAssetAssignment, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(AssetAssignmentState::class)]
        public int $state_id,
    ) {}

    public function validate(AssetAssignmentState $state): void
    {
        $this->assertAssignmentMutable($state);

        $this->assert(
            $state->container_stock_level_id !== null,
            'The asset is not currently in a container.',
        );
    }

    public function apply(AssetAssignmentState $state): void
    {
        $state->container_stock_level_id = null;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(AssetAssignmentState $state): void
    {
        $asset = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first();

        if ($asset === null) {
            return;
        }

        $previous = $asset->container_stock_level_id;

        $asset->forceFill([
            'container_stock_level_id' => null,
        ])->saveQuietly();

        $opportunity = $asset->item()->first()?->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.asset_container_cleared',
                newValues: ['assignment_id' => $state->assignment_id, 'container_stock_level_id' => null],
                oldValues: ['container_stock_level_id' => $previous],
            );
        }
    }
}
