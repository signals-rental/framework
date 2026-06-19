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
 * Renames a quote version's label (opportunity-lifecycle.md §8.6).
 *
 * Valid only while the opportunity is a Quotation (§8.6).
 */
class VersionLabelChanged extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityVersionState::class)]
        public int $version_id,
        public ?string $label = null,
    ) {}

    public function validate(OpportunityVersionState $state): void
    {
        $this->assert(! $state->is_deleted, 'A deleted version cannot be relabelled.');

        $opportunity = Opportunity::query()->whereKey($state->opportunity_id)->first();
        $this->assert(
            $opportunity !== null && $opportunity->state === StateAxis::Quotation,
            'A version can only be relabelled while the opportunity is a Quotation.',
        );
    }

    public function apply(OpportunityVersionState $state): void
    {
        $state->label = $this->label;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityVersionState $state): void
    {
        $version = OpportunityVersion::query()->where('state_id', $state->id)->first();

        if ($version === null) {
            return;
        }

        $oldLabel = $version->label;

        $version->forceFill(['label' => $this->label])->save();

        $opportunity = Opportunity::query()->whereKey($version->opportunity_id)->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.version_relabelled',
                newValues: ['version_id' => $version->id, 'label' => $this->label],
                oldValues: ['label' => $oldLabel],
            );
        }
    }
}
