<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Updates editable scalar header fields on an opportunity. Any payload field
 * left null is treated as "unchanged" — partial updates only ever set the
 * fields the caller provided.
 *
 * Guarded so closed/terminal opportunities (Complete, Cancelled, Lost, Dead)
 * cannot be edited.
 */
class OpportunityUpdated extends Event
{
    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public ?string $subject = null,
        public ?int $member_id = null,
        public ?int $venue_id = null,
        public ?int $store_id = null,
        public ?int $owned_by = null,
        public ?string $reference = null,
        public ?string $description = null,
        public ?string $external_description = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            ! $state->isClosed(),
            'A closed opportunity cannot be edited.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        if ($this->subject !== null) {
            $state->subject = $this->subject;
        }

        if ($this->member_id !== null) {
            $state->member_id = $this->member_id;
        }

        if ($this->venue_id !== null) {
            $state->venue_id = $this->venue_id;
        }

        if ($this->store_id !== null) {
            $state->store_id = $this->store_id;
        }

        if ($this->owned_by !== null) {
            $state->owned_by = $this->owned_by;
        }

        if ($this->reference !== null) {
            $state->reference = $this->reference;
        }

        if ($this->description !== null) {
            $state->description = $this->description;
        }

        if ($this->external_description !== null) {
            $state->external_description = $this->external_description;
        }

        if ($this->starts_at !== null) {
            $state->starts_at = CarbonImmutable::parse($this->starts_at);
        }

        if ($this->ends_at !== null) {
            $state->ends_at = CarbonImmutable::parse($this->ends_at);
        }

        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        Opportunity::query()
            ->where('state_id', $state->id)
            ->update([
                'subject' => $state->subject,
                'member_id' => $state->member_id,
                'venue_id' => $state->venue_id,
                'store_id' => $state->store_id,
                'owned_by' => $state->owned_by,
                'reference' => $state->reference,
                'description' => $state->description,
                'external_description' => $state->external_description,
                'starts_at' => $state->starts_at,
                'ends_at' => $state->ends_at,
            ]);
    }
}
