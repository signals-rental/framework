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
 * Marks an allocated asset as prepared (picked/packed, ready to dispatch)
 * (opportunity-lifecycle.md §5.4). Valid only from the Allocated status.
 *
 * Preparation does not change which assets a line claims, so it leaves the
 * availability demands untouched — the asset is still committed to the line.
 */
class AssetPrepared extends Event
{
    use GuardsAssetAssignment, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(AssetAssignmentState::class)]
        public int $state_id,
        public ?string $prepared_at = null,
    ) {}

    public function validate(AssetAssignmentState $state): void
    {
        $this->assertAssignmentMutable($state);
        $this->assertStatusIn(
            $state,
            [AssetAssignmentStatus::Allocated],
            'Only an allocated asset can be prepared.',
        );
    }

    public function apply(AssetAssignmentState $state): void
    {
        $state->status = AssetAssignmentStatus::Prepared->value;
        $state->prepared_at = $this->preparedAt();
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(AssetAssignmentState $state): void
    {
        $asset = OpportunityItemAsset::query()->whereKey($state->assignment_id)->first();

        if ($asset === null) {
            return;
        }

        $asset->forceFill([
            'status' => AssetAssignmentStatus::Prepared->value,
            'prepared_at' => $state->prepared_at,
        ])->saveQuietly();

        $opportunity = $asset->item()->first()?->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.asset_prepared',
                newValues: ['assignment_id' => $state->assignment_id, 'status' => AssetAssignmentStatus::Prepared->value],
                oldValues: ['status' => AssetAssignmentStatus::Allocated->value],
            );
        }
    }

    private function preparedAt(): CarbonImmutable
    {
        return $this->prepared_at !== null
            ? CarbonImmutable::parse($this->prepared_at)
            : CarbonImmutable::now();
    }
}
