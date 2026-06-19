<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityState as StateAxis;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityVersionState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Deletes a quote version (opportunity-lifecycle.md §8.6).
 *
 * Guarded: the version must NOT be the only version, must NOT be the active
 * version, and the opportunity must still be a Quotation. The version's line
 * items are removed by the DeleteVersion action firing standard {@see ItemRemoved}
 * events BEFORE this event hard-deletes the version row (replay-consistent), so a
 * truncate + replay rebuilds the same end state.
 */
class VersionDeleted extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityVersionState::class)]
        public int $version_id,
        public ?string $reason = null,
    ) {}

    public function validate(OpportunityVersionState $state): void
    {
        $this->assert(! $state->is_deleted, 'The version is already deleted.');

        // Read is_active from the PROJECTION, not the state: activating another
        // version flips this row's projected flag but cannot reach back into this
        // version's in-memory state, so the projection is the source of truth.
        $version = OpportunityVersion::query()->where('state_id', $state->id)->first();
        $this->assert(
            $version !== null && ! $version->is_active,
            'The active version cannot be deleted.',
        );

        $opportunity = Opportunity::query()->whereKey($state->opportunity_id)->first();

        $this->assert(
            $opportunity !== null && $opportunity->state === StateAxis::Quotation,
            'Versions can only be deleted while the opportunity is a Quotation.',
        );

        $versionCount = OpportunityVersion::query()
            ->where('opportunity_id', $state->opportunity_id)
            ->count();

        $this->assert($versionCount > 1, 'The only remaining version cannot be deleted.');
    }

    public function apply(OpportunityVersionState $state): void
    {
        $state->is_deleted = true;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityVersionState $state): void
    {
        $version = OpportunityVersion::query()->where('state_id', $state->id)->first();

        if ($version === null) {
            return;
        }

        $opportunity = Opportunity::query()->whereKey($version->opportunity_id)->first();
        $versionId = $version->id;
        $versionNumber = $version->version_number;

        $version->delete();

        // NB: `version_count` is the running MAX version number issued (it drives
        // replay-stable numbering and the max-versions cap), NOT a live row count,
        // so it is deliberately NOT decremented here.
        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.version_deleted',
                newValues: $this->reason !== null ? ['reason' => $this->reason] : null,
                oldValues: ['version_id' => $versionId, 'version_number' => $versionNumber],
            );
        }
    }
}
