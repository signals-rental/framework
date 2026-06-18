<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityState as StateAxis;
use App\Models\Opportunity;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Genesis event for an opportunity. Creates the state as a Draft/Open document
 * and projects the initial `opportunities` row.
 *
 * `opportunity_id` is the application-allocated small primary key. The
 * CreateOpportunity action allocates it via {@see SequenceAllocator}
 * and passes it in here, so it is persisted in the event payload and replay
 * reproduces identical projection ids (replay-stable).
 *
 * `state_id` is the Verbs snowflake StateId — autofilled by Verbs on first fire
 * (accessible as `$state->id`) — which bridges the projection to the event
 * stream via the `state_id` column.
 */
class OpportunityCreated extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        public int $opportunity_id,
        #[StateId(OpportunityState::class)]
        public ?int $state_id = null,
        public ?string $subject = null,
        public ?string $number = null,
        public ?int $member_id = null,
        public ?int $store_id = null,
        public ?int $owned_by = null,
        public ?int $venue_id = null,
        public ?string $reference = null,
        public ?string $description = null,
        public ?string $external_description = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public int $charge_total = 0,
        public ?string $currency_code = null,
        public bool $prices_include_tax = false,
    ) {}

    public function apply(OpportunityState $state): void
    {
        $state->opportunity_id = $this->opportunity_id;
        $state->state = StateAxis::Draft->value;
        $state->status = StateAxis::Draft->defaultStatus()->statusValue();
        $state->subject = $this->subject;
        $state->number = $this->number;
        $state->member_id = $this->member_id;
        $state->store_id = $this->store_id;
        $state->owned_by = $this->owned_by;
        $state->venue_id = $this->venue_id;
        $state->reference = $this->reference;
        $state->description = $this->description;
        $state->external_description = $this->external_description;
        $state->starts_at = $this->starts_at !== null ? CarbonImmutable::parse($this->starts_at) : null;
        $state->ends_at = $this->ends_at !== null ? CarbonImmutable::parse($this->ends_at) : null;
        $state->charge_total = $this->charge_total;
        $state->currency_code = $this->currency_code ?? settings('company.base_currency', 'GBP');
        $state->exchange_rate = '1';
        $state->prices_include_tax = $this->prices_include_tax;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        $opportunity = Opportunity::query()->updateOrCreate(
            ['id' => $state->opportunity_id],
            [
                'state_id' => $state->id,
                'subject' => $state->subject,
                'number' => $state->number,
                'state' => $state->state,
                'status' => $state->status,
                'member_id' => $state->member_id,
                'store_id' => $state->store_id,
                'owned_by' => $state->owned_by,
                'venue_id' => $state->venue_id,
                'reference' => $state->reference,
                'description' => $state->description,
                'external_description' => $state->external_description,
                'starts_at' => $state->starts_at,
                'ends_at' => $state->ends_at,
                'charge_total' => $state->charge_total,
                'currency_code' => $state->currency_code,
                'exchange_rate' => $state->exchange_rate,
                'prices_include_tax' => $state->prices_include_tax,
            ],
        );

        // Genesis: no prior values. Capture the projected header snapshot as the
        // new values, sourcing state/status as raw integers from the State object
        // (the model casts `state` to an enum) so the JSON column stays stable
        // and identical across replay.
        $this->recordAudit(
            $opportunity,
            'opportunity.created',
            newValues: [
                'subject' => $state->subject,
                'number' => $state->number,
                'state' => $state->state,
                'status' => $state->status,
                'member_id' => $state->member_id,
                'store_id' => $state->store_id,
                'owned_by' => $state->owned_by,
                'venue_id' => $state->venue_id,
                'reference' => $state->reference,
                'description' => $state->description,
                'external_description' => $state->external_description,
                'charge_total' => $state->charge_total,
            ],
            oldValues: null,
        );
    }
}
