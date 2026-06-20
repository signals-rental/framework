<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for attaching a member to an opportunity in a named role.
 *
 * Participants are plain, non-event-sourced CRM associations. The parent
 * opportunity is supplied to the action directly, so it is not part of the
 * payload. `role` is a free-text string (the UI offers a suggested set); `mute`
 * opts the member out of opportunity notifications.
 */
class AddOpportunityParticipantData extends Data
{
    public function __construct(
        public int $member_id,
        public ?string $role = null,
        public bool $mute = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'role' => ['nullable', 'string', 'max:100'],
            'mute' => ['sometimes', 'boolean'],
        ];
    }
}
