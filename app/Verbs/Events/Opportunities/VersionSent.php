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
 * Marks a quote version as Sent to the customer (opportunity-lifecycle.md §8.6).
 *
 * Valid only from Draft and while the opportunity is a Quotation (§8.6); sets
 * `status = Sent`, stamps `sent_at`, and records WHO it was sent to (`sent_to`,
 * a member) and HOW (`sent_via`, e.g. email/portal/manual) on the immutable event
 * stream. Idempotent dual-write — replay re-projects the same row.
 */
class VersionSent extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityVersionState::class)]
        public int $version_id,
        /** The member the version was sent to (§8.6); recorded on the event stream. */
        public ?int $sent_to = null,
        /** The channel it was sent through (e.g. email/portal/manual). */
        public ?string $sent_via = null,
        /** ISO-8601 instant the version was sent, baked at fire-time so replay stays stable. */
        public ?string $sent_at = null,
    ) {}

    public function validate(OpportunityVersionState $state): void
    {
        $this->assert(! $state->is_deleted, 'A deleted version cannot be sent.');
        $this->assert(
            $state->status === VersionStatus::Draft->value,
            'Only a draft version can be sent.',
        );

        $opportunity = Opportunity::query()->whereKey($state->opportunity_id)->first();
        $this->assert(
            $opportunity !== null && $opportunity->state === StateAxis::Quotation,
            'A version can only be sent while the opportunity is a Quotation.',
        );
    }

    public function apply(OpportunityVersionState $state): void
    {
        $state->status = VersionStatus::Sent->value;
        $state->sent_to = $this->sent_to;
        $state->sent_via = $this->sent_via;
        $state->sent_at = $this->sent_at ?? CarbonImmutable::now()->toIso8601String();
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityVersionState $state): void
    {
        $version = OpportunityVersion::query()->where('state_id', $state->id)->first();

        if ($version === null) {
            return;
        }

        $version->forceFill([
            'status' => VersionStatus::Sent->value,
            'sent_at' => $state->sent_at,
            'sent_to' => $this->sent_to,
            'sent_via' => $this->sent_via,
        ])->save();

        $opportunity = Opportunity::query()->whereKey($version->opportunity_id)->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.version_sent',
                newValues: [
                    'version_id' => $version->id,
                    'status' => VersionStatus::Sent->value,
                    'sent_to' => $this->sent_to,
                    'sent_via' => $this->sent_via,
                ],
                oldValues: ['status' => VersionStatus::Draft->value],
            );
        }
    }
}
