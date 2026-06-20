<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Models\Address;
use App\Models\Member;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityUpdated;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

/**
 * Updates editable header fields on an existing opportunity via the
 * OpportunityUpdated event.
 */
class UpdateOpportunity
{
    use CommitsVerbsEvents;

    /**
     * The clearable header fields, modelled as {@see Optional} on the DTO so an
     * EXPLICIT null clears the column while an absent key leaves it unchanged.
     */
    private const OPTIONAL_FIELDS = [
        'venue_id', 'reference', 'description', 'external_description',
        'chargeable_days', 'delivery_instructions', 'collection_instructions',
        'delivery_address_id', 'collection_address_id', 'tag_list',
    ];

    /**
     * The remaining header fields use plain-nullable semantics: null means
     * "leave unchanged" (they are not clearable through this update).
     */
    private const NULLABLE_FIELDS = [
        'subject', 'member_id', 'store_id', 'owned_by',
        'starts_at', 'ends_at', 'charge_starts_at', 'charge_ends_at',
        'prep_starts_at', 'prep_ends_at', 'load_starts_at', 'load_ends_at',
        'deliver_starts_at', 'deliver_ends_at', 'setup_starts_at', 'setup_ends_at',
        'show_starts_at', 'show_ends_at', 'takedown_starts_at', 'takedown_ends_at',
        'collect_starts_at', 'collect_ends_at', 'unload_starts_at', 'unload_ends_at',
        'deprep_starts_at', 'deprep_ends_at', 'ordered_at', 'quote_invalid_at',
        'use_chargeable_days', 'open_ended_rental', 'customer_collecting', 'customer_returning',
    ];

    /**
     * DTO field names that map to a differently-named event/state field, so the
     * provided set carries the event's field name (which {@see OpportunityUpdated}
     * matches against its FIELDS constant). The `invoiced` DTO field projects to
     * the `is_invoiced` state property.
     *
     * @var array<string, string>
     */
    private const FIELD_ALIASES = ['invoiced' => 'is_invoiced'];

    public function __invoke(Opportunity $opportunity, UpdateOpportunityData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $provided = $this->providedFields($data);

        // Authoritative IDOR guard: a supplied delivery/collection address must
        // belong to the opportunity's member. When the member is being changed in
        // this same update, scope to the new member; otherwise the current one. Only
        // address fields actually provided (not their Optional sentinel) are checked.
        $this->assertAddressesBelongToMember($opportunity, $data);

        $this->commitVerbs(function () use ($opportunity, $data, $provided): void {
            OpportunityUpdated::fire(
                opportunity_id: $opportunity->state_id,
                provided: $provided,
                subject: $data->subject,
                member_id: $data->member_id,
                venue_id: $this->resolveOptional($data->venue_id),
                store_id: $data->store_id,
                owned_by: $data->owned_by,
                reference: $this->resolveOptional($data->reference),
                description: $this->resolveOptional($data->description),
                external_description: $this->resolveOptional($data->external_description),
                starts_at: $data->starts_at,
                ends_at: $data->ends_at,
                charge_starts_at: $data->charge_starts_at,
                charge_ends_at: $data->charge_ends_at,
                prep_starts_at: $data->prep_starts_at,
                prep_ends_at: $data->prep_ends_at,
                load_starts_at: $data->load_starts_at,
                load_ends_at: $data->load_ends_at,
                deliver_starts_at: $data->deliver_starts_at,
                deliver_ends_at: $data->deliver_ends_at,
                setup_starts_at: $data->setup_starts_at,
                setup_ends_at: $data->setup_ends_at,
                show_starts_at: $data->show_starts_at,
                show_ends_at: $data->show_ends_at,
                takedown_starts_at: $data->takedown_starts_at,
                takedown_ends_at: $data->takedown_ends_at,
                collect_starts_at: $data->collect_starts_at,
                collect_ends_at: $data->collect_ends_at,
                unload_starts_at: $data->unload_starts_at,
                unload_ends_at: $data->unload_ends_at,
                deprep_starts_at: $data->deprep_starts_at,
                deprep_ends_at: $data->deprep_ends_at,
                ordered_at: $data->ordered_at,
                quote_invalid_at: $data->quote_invalid_at,
                use_chargeable_days: $data->use_chargeable_days,
                chargeable_days: $this->resolveOptional($data->chargeable_days),
                open_ended_rental: $data->open_ended_rental,
                customer_collecting: $data->customer_collecting,
                customer_returning: $data->customer_returning,
                delivery_instructions: $this->resolveOptional($data->delivery_instructions),
                collection_instructions: $this->resolveOptional($data->collection_instructions),
                delivery_address_id: $this->resolveOptional($data->delivery_address_id),
                collection_address_id: $this->resolveOptional($data->collection_address_id),
                is_invoiced: $data->invoiced,
                tag_list: $this->resolveTagList($data->tag_list),
            );
        });

        // Custom fields live outside the event stream. Sync only when the caller
        // supplied them (null = leave untouched), matching the Members convention.
        if ($data->custom_fields !== null) {
            $opportunity->syncCustomFields($data->custom_fields);
        }

        return OpportunityData::fromModel($opportunity->refresh()->load('customFieldValues'));
    }

    /**
     * Assert that every supplied (non-null) address FK points at an {@see Address}
     * owned by the opportunity's member. When the member is being changed in this
     * same update the new member is authoritative; otherwise the current member is.
     * An absent address field (its Optional sentinel) or an explicit null (clearing
     * the column) is skipped — neither leaks another member's address. A mismatch
     * (including a non-Member-owned address) is surfaced as a 422.
     */
    private function assertAddressesBelongToMember(Opportunity $opportunity, UpdateOpportunityData $data): void
    {
        $memberId = $data->member_id ?? $opportunity->member_id;

        foreach (['delivery_address_id', 'collection_address_id'] as $field) {
            $value = $data->{$field};

            // Skip absent (Optional) and explicit-null (clearing) values; only a
            // newly-set address id can introduce a foreign-member reference.
            if ($value instanceof Optional || $value === null) {
                continue;
            }

            $belongs = $memberId !== null && Address::query()
                ->whereKey($value)
                ->where('addressable_type', Member::class)
                ->where('addressable_id', $memberId)
                ->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    $field => ['The selected address does not belong to this opportunity\'s member.'],
                ]);
            }
        }
    }

    /**
     * The list of header fields the caller actually supplied. Optional fields
     * count as provided unless still their `Optional` sentinel; nullable fields
     * count as provided unless null (the existing "null = unchanged" rule).
     *
     * @return list<string>
     */
    private function providedFields(UpdateOpportunityData $data): array
    {
        $provided = [];

        foreach (self::OPTIONAL_FIELDS as $field) {
            if (! $data->{$field} instanceof Optional) {
                $provided[] = $field;
            }
        }

        foreach (self::NULLABLE_FIELDS as $field) {
            if ($data->{$field} !== null) {
                $provided[] = $field;
            }
        }

        // `invoiced` is a nullable-bool DTO field (null = unchanged) that projects
        // to the differently-named `is_invoiced` state property.
        if ($data->invoiced !== null) {
            $provided[] = self::FIELD_ALIASES['invoiced'];
        }

        return $provided;
    }

    /**
     * Collapse an Optional value to its underlying value: an unfilled Optional
     * becomes null (the field is absent and will not be in the provided set, so
     * the event ignores it regardless).
     */
    private function resolveOptional(mixed $value): mixed
    {
        return $value instanceof Optional ? null : $value;
    }

    /**
     * Collapse the optional `tag_list` to the event payload value: an unfilled
     * Optional (or explicit null) becomes null, which the event treats as an
     * empty list when `tag_list` is in the provided set; a supplied array is
     * re-indexed to a clean list.
     *
     * @param  array<int, string>|null|Optional  $value
     * @return list<string>|null
     */
    private function resolveTagList(array|null|Optional $value): ?array
    {
        if ($value instanceof Optional || $value === null) {
            return null;
        }

        return array_values($value);
    }
}
