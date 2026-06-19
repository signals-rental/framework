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
 * Marks a quote version as Declined by the customer (opportunity-lifecycle.md
 * §8.6). Valid from Sent or Draft and while the opportunity is a Quotation; sets
 * `status = Declined`, stamps `declined_at`, and persists the optional
 * `decline_reason` (carried in the event payload so it survives replay).
 */
class VersionDeclined extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityVersionState::class)]
        public int $version_id,
        public ?string $reason = null,
    ) {}

    public function validate(OpportunityVersionState $state): void
    {
        $this->assert(! $state->is_deleted, 'A deleted version cannot be declined.');
        $this->assert(
            in_array($state->status, [VersionStatus::Sent->value, VersionStatus::Draft->value], true),
            'Only a draft or sent version can be declined.',
        );

        $opportunity = Opportunity::query()->whereKey($state->opportunity_id)->first();
        $this->assert(
            $opportunity !== null && $opportunity->state === StateAxis::Quotation,
            'A version can only be declined while the opportunity is a Quotation.',
        );
    }

    public function apply(OpportunityVersionState $state): void
    {
        $state->status = VersionStatus::Declined->value;
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
            'status' => VersionStatus::Declined->value,
            'declined_at' => CarbonImmutable::now(),
            'decline_reason' => $this->reason,
        ])->save();

        $opportunity = Opportunity::query()->whereKey($version->opportunity_id)->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.version_declined',
                newValues: [
                    'version_id' => $version->id,
                    'status' => VersionStatus::Declined->value,
                    'reason' => $this->reason,
                ],
                oldValues: ['status' => $oldStatus],
            );
        }
    }
}
