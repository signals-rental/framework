<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\VersionStatus;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityVersionState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Marks a quote version as Superseded (opportunity-lifecycle.md §8.6).
 *
 * Fired on a parent version when a new REVISION is created, and on all
 * non-confirmed versions at quote → order conversion (§8.8). Already-terminal
 * (Accepted/Declined) versions are left untouched so the historical decision is
 * preserved.
 *
 * `superseded_by_version_id` records WHICH version replaced this one (the new
 * revision's id, or the confirmed version's id on order conversion) — forward
 * lineage carried in the payload so it survives replay. Null when the superseding
 * version is unknown.
 */
class VersionSuperseded extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityVersionState::class)]
        public int $version_id,
        public ?int $superseded_by_version_id = null,
    ) {}

    public function apply(OpportunityVersionState $state): void
    {
        // Only supersede a still-open version; preserve a recorded
        // Accepted/Declined decision.
        if (! in_array($state->status, [VersionStatus::Accepted->value, VersionStatus::Declined->value], true)) {
            $state->status = VersionStatus::Superseded->value;
        }

        $state->superseded_by_version_id = $this->superseded_by_version_id;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityVersionState $state): void
    {
        $version = OpportunityVersion::query()->where('state_id', $state->id)->first();

        if ($version === null) {
            return;
        }

        $oldStatus = (int) $version->getRawOriginal('status');

        if (in_array($oldStatus, [VersionStatus::Accepted->value, VersionStatus::Declined->value], true)) {
            // Still record the forward-lineage pointer even when the recorded
            // Accepted/Declined decision is preserved.
            $version->forceFill(['superseded_by_version_id' => $this->superseded_by_version_id])->save();

            return;
        }

        $version->forceFill([
            'status' => VersionStatus::Superseded->value,
            'superseded_by_version_id' => $this->superseded_by_version_id,
        ])->save();

        $opportunity = Opportunity::query()->whereKey($version->opportunity_id)->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.version_superseded',
                newValues: [
                    'version_id' => $version->id,
                    'status' => VersionStatus::Superseded->value,
                    'superseded_by_version_id' => $this->superseded_by_version_id,
                ],
                oldValues: ['status' => $oldStatus],
            );
        }
    }
}
