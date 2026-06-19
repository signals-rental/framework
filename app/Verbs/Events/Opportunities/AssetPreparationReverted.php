<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\AssetAssignmentStatus;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\Concerns\GuardsAssetAssignment;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\AssetAssignmentState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Reverts a prepared asset back to Allocated (opportunity-lifecycle.md §5.4).
 * Valid only from the Prepared status. Clears the prepared timestamp.
 */
class AssetPreparationReverted extends Event
{
    use GuardsAssetAssignment, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(AssetAssignmentState::class)]
        public int $state_id,
    ) {}

    public function validate(AssetAssignmentState $state): void
    {
        $this->assertAssignmentMutable($state);
        $this->assertStatusIn(
            $state,
            [AssetAssignmentStatus::Prepared],
            'Only a prepared asset can have its preparation reverted.',
        );
    }

    public function apply(AssetAssignmentState $state): void
    {
        $state->status = AssetAssignmentStatus::Allocated->value;
        $state->prepared_at = null;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(AssetAssignmentState $state): void
    {
        $asset = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first();

        if ($asset === null) {
            return;
        }

        $asset->forceFill([
            'status' => AssetAssignmentStatus::Allocated->value,
            'prepared_at' => null,
        ])->saveQuietly();

        $opportunity = $asset->item()->first()?->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.asset_preparation_reverted',
                newValues: ['assignment_id' => $state->assignment_id, 'status' => AssetAssignmentStatus::Allocated->value],
                oldValues: ['status' => AssetAssignmentStatus::Prepared->value],
            );
        }
    }
}
