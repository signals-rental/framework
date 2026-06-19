<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityState as StateAxis;
use App\Enums\VersionStatus;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityVersionState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Marks a quote version as Accepted by the customer (opportunity-lifecycle.md
 * §8.6). Valid from Sent or Draft and while the opportunity is a Quotation; sets
 * `status = Accepted`, stamps `accepted_at`, and records WHO accepted it
 * (`accepted_by`, a member) on the immutable event stream. An accepted version
 * takes priority at quote → order conversion (§8.8).
 */
class VersionAccepted extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityVersionState::class)]
        public int $version_id,
        /** The member who accepted the version (§8.6); recorded on the event stream. */
        public ?int $accepted_by = null,
    ) {}

    public function validate(OpportunityVersionState $state): void
    {
        $this->assert(! $state->is_deleted, 'A deleted version cannot be accepted.');
        $this->assert(
            in_array($state->status, [VersionStatus::Sent->value, VersionStatus::Draft->value], true),
            'Only a draft or sent version can be accepted.',
        );

        $opportunity = Opportunity::query()->whereKey($state->opportunity_id)->first();
        $this->assert(
            $opportunity !== null && $opportunity->state === StateAxis::Quotation,
            'A version can only be accepted while the opportunity is a Quotation.',
        );
    }

    public function apply(OpportunityVersionState $state): void
    {
        $state->status = VersionStatus::Accepted->value;
        $state->accepted_by = $this->accepted_by;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityVersionState $state): void
    {
        $version = OpportunityVersion::query()->where('state_id', $state->id)->first();

        if ($version === null) {
            return;
        }

        $oldStatus = (int) $version->getRawOriginal('status');

        $version->forceFill([
            'status' => VersionStatus::Accepted->value,
            'accepted_at' => CarbonImmutable::now(),
            'accepted_by' => $this->accepted_by,
        ])->save();

        $opportunity = Opportunity::query()->whereKey($version->opportunity_id)->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.version_accepted',
                newValues: [
                    'version_id' => $version->id,
                    'status' => VersionStatus::Accepted->value,
                    'accepted_by' => $this->accepted_by,
                ],
                oldValues: ['status' => $oldStatus],
            );
        }
    }
}
