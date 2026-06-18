<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
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
    use RecordsOpportunityAudit;

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
        // Capture prior values of only the fields this event actually changes
        // (payload non-null), BEFORE the projection update. Dates normalise to
        // ISO 8601 strings so old/new stay comparable and JSON-stable.
        $changedFields = $this->changedFields();
        $oldRow = Opportunity::query()->where('state_id', $state->id)->first();
        $oldValues = $oldRow !== null
            ? $this->snapshotFrom($changedFields, fn (string $field): mixed => $this->normalise($oldRow->getAttribute($field)))
            : null;

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

        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();

        $newValues = $this->snapshotFrom($changedFields, fn (string $field): mixed => $this->normalise($state->{$field}));

        $this->recordAudit(
            $opportunity,
            'opportunity.updated',
            newValues: $newValues,
            oldValues: $oldValues,
        );
    }

    /**
     * The header fields this update actually touched — those whose payload was
     * provided (non-null), mirroring apply()'s partial-update rule.
     *
     * @return list<string>
     */
    protected function changedFields(): array
    {
        $candidates = [
            'subject', 'member_id', 'venue_id', 'store_id', 'owned_by',
            'reference', 'description', 'external_description', 'starts_at', 'ends_at',
        ];

        return array_values(array_filter(
            $candidates,
            fn (string $field): bool => $this->{$field} !== null,
        ));
    }

    /**
     * Build a {field => value} snapshot for the given fields using the resolver.
     *
     * @param  list<string>  $fields
     * @param  callable(string): mixed  $resolve
     * @return array<string, mixed>
     */
    protected function snapshotFrom(array $fields, callable $resolve): array
    {
        $snapshot = [];

        foreach ($fields as $field) {
            $snapshot[$field] = $resolve($field);
        }

        return $snapshot;
    }

    /**
     * Normalise a value for JSON-stable audit storage — dates to ISO 8601.
     */
    protected function normalise(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return $value;
    }
}
