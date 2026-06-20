<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\OpportunityUpdated;
use Illuminate\Support\Facades\Gate;
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
    private const OPTIONAL_FIELDS = ['venue_id', 'reference', 'description', 'external_description', 'tag_list'];

    /**
     * The remaining header fields use plain-nullable semantics: null means
     * "leave unchanged" (they are not clearable through this update).
     */
    private const NULLABLE_FIELDS = ['subject', 'member_id', 'store_id', 'owned_by', 'starts_at', 'ends_at', 'charge_starts_at', 'charge_ends_at'];

    public function __invoke(Opportunity $opportunity, UpdateOpportunityData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $provided = $this->providedFields($data);

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
