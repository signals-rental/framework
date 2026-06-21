<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\Concerns\GuardsAssetAssignment;
use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\AssetAssignmentState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Records the condition assessment for a returned asset (opportunity-lifecycle.md
 * §5.4). Valid only from Checked In. This is the quality workflow that runs AFTER
 * availability has already been released on return — it does not touch demand or
 * allocation (availability-engine "Availability Update Timing on Return").
 *
 * Auto-promotion (§7.6): once every returned asset is checked, the order is
 * promoted to Checked.
 */
class AssetChecked extends Event
{
    use GuardsAssetAssignment, PromotesOpportunityStatus, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(AssetAssignmentState::class)]
        public int $state_id,
        public int $condition = 0,
        public ?int $checked_by = null,
        public ?string $damage_notes = null,
        public ?string $checked_at = null,
    ) {}

    public function validate(AssetAssignmentState $state): void
    {
        $this->assertAssignmentMutable($state);
        $this->assertStatusIn(
            $state,
            [AssetAssignmentStatus::CheckedIn],
            'Only a checked-in asset can be condition-checked.',
        );

        $this->assert(
            AssetCondition::tryFrom($this->condition) !== null,
            'The supplied condition is not a valid asset condition.',
        );
    }

    public function apply(AssetAssignmentState $state): void
    {
        $state->status = AssetAssignmentStatus::Finalised->value;
        $state->condition_on_return = $this->condition;
        $state->checked_at = $this->checkedAt();
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(AssetAssignmentState $state): void
    {
        $asset = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first();

        if ($asset === null) {
            return;
        }

        $asset->forceFill([
            'status' => AssetAssignmentStatus::Finalised->value,
            'condition_on_return' => $this->condition,
            'checked_at' => $state->checked_at,
            'notes' => $this->damage_notes ?? $asset->notes,
        ])->saveQuietly();

        $opportunity = $this->opportunityForAssignment($state->assignment_id);

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.asset_checked',
                newValues: [
                    'assignment_id' => $state->assignment_id,
                    'condition' => $this->condition,
                    'checked_by' => $this->checked_by,
                ],
                oldValues: ['status' => AssetAssignmentStatus::CheckedIn->value],
            );
        }
    }

    public function fired(AssetAssignmentState $state): void
    {
        $this->promoteOpportunityFromItems(
            $this->opportunityForAssignment($state->assignment_id),
            $this->singleAssetOverlay($state->assignment_id, AssetAssignmentStatus::Finalised),
        );
    }

    private function checkedAt(): CarbonImmutable
    {
        return $this->checked_at !== null
            ? CarbonImmutable::parse($this->checked_at)
            : CarbonImmutable::now();
    }
}
