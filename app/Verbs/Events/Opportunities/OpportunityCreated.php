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
        public ?string $charge_starts_at = null,
        public ?string $charge_ends_at = null,
        public ?string $prep_starts_at = null,
        public ?string $prep_ends_at = null,
        public ?string $load_starts_at = null,
        public ?string $load_ends_at = null,
        public ?string $deliver_starts_at = null,
        public ?string $deliver_ends_at = null,
        public ?string $setup_starts_at = null,
        public ?string $setup_ends_at = null,
        public ?string $show_starts_at = null,
        public ?string $show_ends_at = null,
        public ?string $takedown_starts_at = null,
        public ?string $takedown_ends_at = null,
        public ?string $collect_starts_at = null,
        public ?string $collect_ends_at = null,
        public ?string $unload_starts_at = null,
        public ?string $unload_ends_at = null,
        public ?string $deprep_starts_at = null,
        public ?string $deprep_ends_at = null,
        public ?string $ordered_at = null,
        public ?string $quote_invalid_at = null,
        public bool $use_chargeable_days = false,
        public ?string $chargeable_days = null,
        public bool $open_ended_rental = false,
        public bool $customer_collecting = false,
        public bool $customer_returning = false,
        public ?string $delivery_instructions = null,
        public ?string $collection_instructions = null,
        public int $charge_total = 0,
        public ?string $currency_code = null,
        public ?string $exchange_rate = null,
        public bool $prices_include_tax = false,
        /** @var list<string> */
        public array $tag_list = [],
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
        $state->charge_starts_at = $this->charge_starts_at !== null ? CarbonImmutable::parse($this->charge_starts_at) : null;
        $state->charge_ends_at = $this->charge_ends_at !== null ? CarbonImmutable::parse($this->charge_ends_at) : null;
        // Event-logistics lifecycle dates + milestones (C3a). Each parses its ISO
        // string to CarbonImmutable, mirroring the hire-period dates above.
        $state->prep_starts_at = $this->parseDate($this->prep_starts_at);
        $state->prep_ends_at = $this->parseDate($this->prep_ends_at);
        $state->load_starts_at = $this->parseDate($this->load_starts_at);
        $state->load_ends_at = $this->parseDate($this->load_ends_at);
        $state->deliver_starts_at = $this->parseDate($this->deliver_starts_at);
        $state->deliver_ends_at = $this->parseDate($this->deliver_ends_at);
        $state->setup_starts_at = $this->parseDate($this->setup_starts_at);
        $state->setup_ends_at = $this->parseDate($this->setup_ends_at);
        $state->show_starts_at = $this->parseDate($this->show_starts_at);
        $state->show_ends_at = $this->parseDate($this->show_ends_at);
        $state->takedown_starts_at = $this->parseDate($this->takedown_starts_at);
        $state->takedown_ends_at = $this->parseDate($this->takedown_ends_at);
        $state->collect_starts_at = $this->parseDate($this->collect_starts_at);
        $state->collect_ends_at = $this->parseDate($this->collect_ends_at);
        $state->unload_starts_at = $this->parseDate($this->unload_starts_at);
        $state->unload_ends_at = $this->parseDate($this->unload_ends_at);
        $state->deprep_starts_at = $this->parseDate($this->deprep_starts_at);
        $state->deprep_ends_at = $this->parseDate($this->deprep_ends_at);
        $state->ordered_at = $this->parseDate($this->ordered_at);
        $state->quote_invalid_at = $this->parseDate($this->quote_invalid_at);
        // Chargeable-days + open-ended + customer collect/return flags (C3b/C3c).
        $state->use_chargeable_days = $this->use_chargeable_days;
        $state->chargeable_days = $this->chargeable_days;
        $state->open_ended_rental = $this->open_ended_rental;
        $state->customer_collecting = $this->customer_collecting;
        $state->customer_returning = $this->customer_returning;
        $state->delivery_instructions = $this->delivery_instructions;
        $state->collection_instructions = $this->collection_instructions;
        $state->tag_list = $this->tag_list;
        $state->charge_total = $this->charge_total;
        // The currency is resolved at fire-time by the CreateOpportunity action
        // (request currency ?? company base-currency setting) and baked into the
        // payload, so apply() stays pure — no settings()/external read here, which
        // keeps replay deterministic even if the company base currency later changes.
        $state->currency_code = $this->currency_code ?? 'GBP';
        // The exchange rate against the company base currency is resolved at
        // fire-time by the CreateOpportunity action (via CurrencyService) and baked
        // into the payload, so apply() stays pure and replay-deterministic — the
        // rate snapshot never re-fetches on replay even if rates later change. A
        // same-currency opportunity (or an unresolved payload) is exactly '1'.
        $state->exchange_rate = $this->exchange_rate ?? '1';
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
                'charge_starts_at' => $state->charge_starts_at,
                'charge_ends_at' => $state->charge_ends_at,
                'prep_starts_at' => $state->prep_starts_at,
                'prep_ends_at' => $state->prep_ends_at,
                'load_starts_at' => $state->load_starts_at,
                'load_ends_at' => $state->load_ends_at,
                'deliver_starts_at' => $state->deliver_starts_at,
                'deliver_ends_at' => $state->deliver_ends_at,
                'setup_starts_at' => $state->setup_starts_at,
                'setup_ends_at' => $state->setup_ends_at,
                'show_starts_at' => $state->show_starts_at,
                'show_ends_at' => $state->show_ends_at,
                'takedown_starts_at' => $state->takedown_starts_at,
                'takedown_ends_at' => $state->takedown_ends_at,
                'collect_starts_at' => $state->collect_starts_at,
                'collect_ends_at' => $state->collect_ends_at,
                'unload_starts_at' => $state->unload_starts_at,
                'unload_ends_at' => $state->unload_ends_at,
                'deprep_starts_at' => $state->deprep_starts_at,
                'deprep_ends_at' => $state->deprep_ends_at,
                'ordered_at' => $state->ordered_at,
                'quote_invalid_at' => $state->quote_invalid_at,
                'use_chargeable_days' => $state->use_chargeable_days,
                'chargeable_days' => $state->chargeable_days,
                'open_ended_rental' => $state->open_ended_rental,
                'customer_collecting' => $state->customer_collecting,
                'customer_returning' => $state->customer_returning,
                'delivery_instructions' => $state->delivery_instructions,
                'collection_instructions' => $state->collection_instructions,
                'charge_total' => $state->charge_total,
                'currency_code' => $state->currency_code,
                'exchange_rate' => $state->exchange_rate,
                'prices_include_tax' => $state->prices_include_tax,
                'tag_list' => $state->tag_list,
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
                'tag_list' => $state->tag_list,
            ],
            oldValues: null,
        );
    }

    /**
     * Parse an optional ISO datetime string from the event payload to a
     * CarbonImmutable, passing null through. Keeps apply() pure and
     * replay-deterministic.
     */
    private function parseDate(?string $value): ?CarbonImmutable
    {
        return $value !== null ? CarbonImmutable::parse($value) : null;
    }
}
