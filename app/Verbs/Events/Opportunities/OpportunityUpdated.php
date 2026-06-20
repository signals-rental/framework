<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Updates editable scalar header fields on an opportunity.
 *
 * Partial updates are driven by an explicit {@see $provided} set — the list of
 * field names the caller actually supplied — rather than by treating null as
 * "unchanged". This lets a client distinguish ABSENT (leave the column alone)
 * from an EXPLICIT null (clear the column): only fields named in `$provided` are
 * written, and a provided field carrying null clears its column.
 *
 * Guarded so closed/terminal opportunities (Complete, Cancelled, Lost, Dead)
 * cannot be edited.
 */
class OpportunityUpdated extends Event
{
    use RecordsOpportunityAudit;

    /**
     * The lifecycle/milestone datetime fields this event may touch. Each is
     * carried as an ISO string in the payload and parsed to CarbonImmutable in
     * apply() (mirroring `starts_at`/`charge_starts_at`).
     */
    private const DATE_FIELDS = [
        'starts_at', 'ends_at', 'charge_starts_at', 'charge_ends_at',
        'prep_starts_at', 'prep_ends_at', 'load_starts_at', 'load_ends_at',
        'deliver_starts_at', 'deliver_ends_at', 'setup_starts_at', 'setup_ends_at',
        'show_starts_at', 'show_ends_at', 'takedown_starts_at', 'takedown_ends_at',
        'collect_starts_at', 'collect_ends_at', 'unload_starts_at', 'unload_ends_at',
        'deprep_starts_at', 'deprep_ends_at', 'ordered_at', 'quote_invalid_at',
    ];

    /** The header fields this event may touch, in projection order. */
    private const FIELDS = [
        'subject', 'member_id', 'venue_id', 'store_id', 'owned_by',
        'reference', 'description', 'external_description',
        'starts_at', 'ends_at', 'charge_starts_at', 'charge_ends_at',
        'prep_starts_at', 'prep_ends_at', 'load_starts_at', 'load_ends_at',
        'deliver_starts_at', 'deliver_ends_at', 'setup_starts_at', 'setup_ends_at',
        'show_starts_at', 'show_ends_at', 'takedown_starts_at', 'takedown_ends_at',
        'collect_starts_at', 'collect_ends_at', 'unload_starts_at', 'unload_ends_at',
        'deprep_starts_at', 'deprep_ends_at', 'ordered_at', 'quote_invalid_at',
        'use_chargeable_days', 'chargeable_days', 'open_ended_rental',
        'customer_collecting', 'customer_returning',
        'delivery_instructions', 'collection_instructions',
        'is_invoiced', 'tag_list',
    ];

    /**
     * @param  list<string>  $provided  Field names the caller explicitly supplied;
     *                                  only these are applied (null clears them).
     */
    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        /** @var list<string> */
        public array $provided = [],
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
        public ?bool $use_chargeable_days = null,
        public ?string $chargeable_days = null,
        public ?bool $open_ended_rental = null,
        public ?bool $customer_collecting = null,
        public ?bool $customer_returning = null,
        public ?string $delivery_instructions = null,
        public ?string $collection_instructions = null,
        public ?bool $is_invoiced = null,
        /** @var list<string>|null */
        public ?array $tag_list = null,
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
        foreach ($this->changedFields() as $field) {
            $state->{$field} = match (true) {
                // Datetime fields parse their ISO string to CarbonImmutable; a
                // provided null clears the column.
                in_array($field, self::DATE_FIELDS, true) => $this->{$field} !== null
                    ? CarbonImmutable::parse($this->{$field})
                    : null,
                // A provided null clears the tags to an empty list, never null,
                // so the projection's JSONB column stays a normalised array.
                $field === 'tag_list' => $this->tag_list ?? [],
                // The boolean flags are non-nullable on the state; a provided
                // null coalesces to false so the state property never goes null.
                in_array($field, ['use_chargeable_days', 'open_ended_rental', 'customer_collecting', 'customer_returning', 'is_invoiced'], true) => (bool) $this->{$field},
                // chargeable_days (nullable decimal string) and all other scalars
                // pass straight through.
                default => $this->{$field},
            };
        }

        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        // Capture prior values of only the fields this event actually changes,
        // BEFORE the projection update. Dates normalise to ISO 8601 strings so
        // old/new stay comparable and JSON-stable.
        $changedFields = $this->changedFields();
        $oldRow = Opportunity::query()->where('state_id', $state->id)->first();
        $oldValues = $oldRow !== null
            ? $this->snapshotFrom($changedFields, fn (string $field): mixed => $this->normalise($oldRow->getAttribute($this->columnFor($field))))
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
                'invoiced' => $state->is_invoiced,
                'tag_list' => $state->tag_list,
            ]);

        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();

        // A member change can move the opportunity onto a different organisation
        // tax class, which makes the stored tax figures stale. Recompute the
        // grouped final tax (and the gross total) whenever member_id is among the
        // fields this update touched — but never when the opportunity's tax is
        // locked (a confirmed order preserves its snapshotted tax: MC §7.2). The
        // calculator is idempotent and writes the projection quietly, so this is
        // replay-stable.
        if (in_array('member_id', $changedFields, true) && ! $opportunity->tax_locked) {
            app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());
        }

        $newValues = $this->snapshotFrom($changedFields, fn (string $field): mixed => $this->normalise($state->{$field}));

        $this->recordAudit(
            $opportunity,
            'opportunity.updated',
            newValues: $newValues,
            oldValues: $oldValues,
        );
    }

    /**
     * The header fields this update actually touched — those the caller
     * explicitly provided, in projection order. A field listed here may carry
     * null, which clears the column.
     *
     * @return list<string>
     */
    protected function changedFields(): array
    {
        return array_values(array_filter(
            self::FIELDS,
            fn (string $field): bool => in_array($field, $this->provided, true),
        ));
    }

    /**
     * Map a logical field name to its projection COLUMN. The `is_invoiced` state
     * property (RMS `is_invoiced` naming) projects to the `invoiced` column, so
     * the old-value audit snapshot reads the right attribute; every other field
     * name matches its column one-to-one.
     */
    protected function columnFor(string $field): string
    {
        return $field === 'is_invoiced' ? 'invoiced' : $field;
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
