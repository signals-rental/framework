<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityState as StateAxis;
use App\Enums\VersionStatus;
use App\Enums\VersionType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use App\Verbs\States\OpportunityVersionState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

/**
 * Genesis event for a quote version (opportunity-lifecycle.md §8.6).
 *
 * Applies to TWO states: the version's own {@see OpportunityVersionState} (it
 * projects the `opportunity_versions` row) AND the parent {@see OpportunityState}
 * (it promotes the new version to ACTIVE so the opportunity's line items and
 * totals follow it). The CreateVersion action then clones the source version's
 * items into this version's scope by firing standard {@see ItemAdded} events, and
 * supersedes the parent (revisions only) via {@see VersionSuperseded} — all
 * inside one atomic commit.
 *
 * `version_id` and `version_number` are application-allocated (the action bakes
 * them into the payload via {@see SequenceAllocator} and the running version
 * count), so a truncate + Verbs::replay() reproduces identical ids and numbers
 * (replay-stable — never a MAX() at apply-time). `version_state_id` is the version
 * state's Verbs snowflake StateId, autofilled on first fire; `opportunity_id` is
 * the parent OpportunityState's StateId.
 */
class VersionCreated extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        public int $version_id,
        public int $opportunity_pk,
        public int $version_number,
        #[StateId(OpportunityVersionState::class)]
        public ?int $version_state_id = null,
        #[StateId(OpportunityState::class)]
        public int $opportunity_id = 0,
        public int $version_type = VersionType::Revision->value,
        public ?int $parent_version_id = null,
        public ?string $label = null,
        public ?int $created_by = null,
        public ?string $notes = null,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            $state->state === StateAxis::Quotation->value,
            'Quote versions can only be created on an opportunity in the Quotation state.',
        );

        $this->assert(
            ! $state->isClosed(),
            'Quote versions cannot be created on a closed opportunity.',
        );

        $maxVersions = (int) settings('opportunities.max_versions', 20);
        $this->assert(
            $state->version_count < $maxVersions,
            "This opportunity has reached the maximum of {$maxVersions} quote versions.",
        );

        if ($this->version_type === VersionType::Alternative->value) {
            $maxAlternatives = (int) settings('opportunities.max_alternatives', 5);
            // Count the live (non-superseded/declined) alternatives so the cap
            // tracks concurrent options, not historical ones.
            $liveAlternatives = OpportunityVersion::query()
                ->where('opportunity_id', $this->opportunity_pk)
                ->where('version_type', VersionType::Alternative->value)
                ->whereNotIn('status', [VersionStatus::Superseded->value, VersionStatus::Declined->value])
                ->count();

            $this->assert(
                $liveAlternatives < $maxAlternatives,
                "This opportunity has reached the maximum of {$maxAlternatives} concurrent alternatives.",
            );
        }
    }

    public function applyToVersion(OpportunityVersionState $state): void
    {
        $state->version_id = $this->version_id;
        $state->opportunity_id = $this->opportunity_pk;
        $state->version_number = $this->version_number;
        $state->parent_version_id = $this->parent_version_id;
        $state->version_type = $this->version_type;
        $state->label = $this->label;
        $state->is_active = true;
        $state->status = VersionStatus::Draft->value;
        $state->notes = $this->notes;
        $state->created_by = $this->created_by;
        $state->is_deleted = false;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function applyToOpportunity(OpportunityState $state): void
    {
        $state->active_version_id = $this->version_id;
        $state->version_count = $this->version_number;

        if ($this->version_type === VersionType::Alternative->value) {
            $state->has_alternatives = true;
        }

        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityVersionState $versionState, OpportunityState $opportunityState): void
    {
        OpportunityVersion::query()->updateOrCreate(
            ['id' => $versionState->version_id],
            [
                'state_id' => $versionState->id,
                'opportunity_id' => $versionState->opportunity_id,
                'version_number' => $versionState->version_number,
                'parent_version_id' => $versionState->parent_version_id,
                'version_type' => $versionState->version_type,
                'label' => $versionState->label,
                'is_active' => true,
                'status' => $versionState->status,
                'notes' => $versionState->notes,
                'created_by' => $versionState->created_by,
            ],
        );

        // Identify the previously-active version (it is being demoted) so its item
        // demands can be released — only the new active version may hold demands.
        $previousActiveId = OpportunityVersion::query()
            ->where('opportunity_id', $versionState->opportunity_id)
            ->where('id', '!=', $versionState->version_id)
            ->where('is_active', true)
            ->value('id');

        // Demote the previously-active version (if any) on the same opportunity.
        OpportunityVersion::query()
            ->where('opportunity_id', $versionState->opportunity_id)
            ->where('id', '!=', $versionState->version_id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // First version on a previously-versionless opportunity: ADOPT its existing
        // legacy items (version_id = NULL) into this genesis version so the items,
        // totals and demands the opportunity already had carry forward unchanged
        // (rather than vanishing now that items() is version-scoped). Deterministic
        // on the projection state, so replay-stable; later versions clone instead.
        if ($versionState->version_number === 1) {
            OpportunityItem::query()
                ->where('opportunity_id', $versionState->opportunity_id)
                ->whereNull('version_id')
                ->update(['version_id' => $versionState->version_id]);
        }

        Opportunity::query()
            ->where('state_id', $opportunityState->id)
            ->update([
                'active_version_id' => $opportunityState->active_version_id,
                'version_count' => $opportunityState->version_count,
                'has_alternatives' => $opportunityState->has_alternatives,
            ]);

        // Release the previously-active version's item demands (replay-skipped):
        // the new version is now active, and only its (about-to-be-cloned) items
        // may claim stock. The new items sync their own demands as they are added.
        if ($previousActiveId !== null) {
            Verbs::unlessReplaying(function () use ($previousActiveId): void {
                $resolver = app(OpportunityItemDemandResolver::class);

                OpportunityItem::query()
                    ->where('version_id', $previousActiveId)
                    ->each(function (OpportunityItem $item) use ($resolver): void {
                        $resolver->releaseDemands($item);
                    });
            });
        }

        $opportunity = Opportunity::query()->where('state_id', $opportunityState->id)->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.version_created',
                newValues: [
                    'version_id' => $this->version_id,
                    'version_number' => $this->version_number,
                    'version_type' => $this->version_type,
                    'parent_version_id' => $this->parent_version_id,
                    'label' => $this->label,
                ],
                oldValues: null,
            );
        }
    }
}
