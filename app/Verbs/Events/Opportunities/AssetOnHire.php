<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\AssetAssignmentStatus;
use App\Models\OpportunityItemAsset;
use App\Verbs\Events\Opportunities\Concerns\GuardsAssetAssignment;
use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\AssetAssignmentState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Confirms a dispatched asset is now physically on hire with the client
 * (opportunity-lifecycle.md §5.4). Valid only from Dispatched.
 *
 * Both Dispatched and On Hire map to the Operational demand phase, so the demand
 * window is unchanged. The aggregate opportunity status is re-derived for
 * completeness (§7.6) — On Hire and Dispatched both count as "unreturned", so this
 * transition only promotes the order once the LAST asset leaves Dispatched while
 * none remain undispatched.
 */
class AssetOnHire extends Event
{
    use GuardsAssetAssignment, PromotesOpportunityStatus, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(AssetAssignmentState::class)]
        public int $state_id,
        public ?string $on_hire_at = null,
    ) {}

    public function validate(AssetAssignmentState $state): void
    {
        $this->assertAssignmentMutable($state);
        $this->assertStatusIn(
            $state,
            [AssetAssignmentStatus::Dispatched],
            'Only a dispatched asset can be marked on hire.',
        );
    }

    public function apply(AssetAssignmentState $state): void
    {
        $state->status = AssetAssignmentStatus::OnHire->value;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(AssetAssignmentState $state): void
    {
        $asset = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first();

        if ($asset === null) {
            return;
        }

        $asset->forceFill(['status' => AssetAssignmentStatus::OnHire->value])->saveQuietly();

        $opportunity = $asset->item()->first()?->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.asset_on_hire',
                newValues: ['assignment_id' => $state->assignment_id, 'status' => AssetAssignmentStatus::OnHire->value],
                oldValues: ['status' => AssetAssignmentStatus::Dispatched->value],
            );
        }
    }

    public function fired(AssetAssignmentState $state): void
    {
        $opportunity = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first()
            ?->item()->first()
            ?->opportunity()->first();

        $this->promoteOpportunityFromItems(
            $opportunity,
            $this->singleAssetOverlay($state->assignment_id, AssetAssignmentStatus::OnHire),
        );
    }
}
