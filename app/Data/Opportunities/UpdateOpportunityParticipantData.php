<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Input DTO for updating an opportunity participant's role and/or mute flag.
 *
 * Both fields are OPTIONAL — the UpdateOpportunityParticipant action merges only
 * the provided fields over the participant's current values, so an omitted field
 * is left untouched. `role` is a free-text string (the UI offers a suggested set)
 * and may be explicitly cleared to null. The member association itself is
 * immutable; to change the member, remove and re-add the participant.
 */
class UpdateOpportunityParticipantData extends Data
{
    public function __construct(
        public string|null|Optional $role,
        public bool|Optional $mute,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'role' => ['sometimes', 'nullable', 'string', 'max:100'],
            'mute' => ['sometimes', 'boolean'],
        ];
    }
}
