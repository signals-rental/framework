<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\OpportunityItemAsset;
use App\Models\StockLevel;
use App\Verbs\Events\Opportunities\Concerns\GuardsAssetAssignment;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\AssetAssignmentState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Nests an allocated asset inside a kit/case container stock level
 * (opportunity-lifecycle.md §5.4). Both the asset and the container must exist.
 *
 * The container relationship is presentational/logistical — it does not change
 * which units a line claims, so availability demands are untouched.
 */
class AssetContainerSet extends Event
{
    use GuardsAssetAssignment, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(AssetAssignmentState::class)]
        public int $state_id,
        public int $container_stock_level_id = 0,
    ) {}

    public function validate(AssetAssignmentState $state): void
    {
        $this->assertAssignmentMutable($state);

        $this->assert(
            StockLevel::query()->whereKey($this->container_stock_level_id)->exists(),
            'The container stock level does not exist.',
        );

        $this->assert(
            $this->container_stock_level_id !== $state->stock_level_id,
            'An asset cannot be its own container.',
        );
    }

    public function apply(AssetAssignmentState $state): void
    {
        $state->container_stock_level_id = $this->container_stock_level_id;
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
            'container_stock_level_id' => $state->container_stock_level_id,
        ])->saveQuietly();

        $opportunity = $asset->item()->first()?->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.asset_container_set',
                newValues: ['assignment_id' => $state->assignment_id, 'container_stock_level_id' => $state->container_stock_level_id],
                oldValues: ['container_stock_level_id' => $previous],
            );
        }
    }
}
