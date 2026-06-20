<?php

namespace App\Data\Opportunities;

use App\Data\Concerns\EntityReferenceData;
use App\Data\Concerns\FormatsTimestamps;
use App\Models\OpportunityParticipant;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * API/serialisation representation of an opportunity participant (the RMS
 * `participants[]` shape): a member attached to an opportunity in a named role.
 *
 * Participants are plain, non-event-sourced CRM associations. The related member
 * is lazy — present only as an `{id, name}` reference when the `member` relation
 * is eager-loaded — mirroring the entity-reference pattern used across
 * {@see OpportunityData}.
 */
class ParticipantData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public int $opportunity_id,
        public int $member_id,
        public ?string $role,
        public bool $mute,
        public string $created_at,
        public string $updated_at,
        public Lazy|EntityReferenceData|null $member = null,
    ) {}

    public static function fromModel(OpportunityParticipant $participant): self
    {
        return new self(
            id: $participant->id,
            opportunity_id: $participant->opportunity_id,
            member_id: $participant->member_id,
            role: $participant->role,
            mute: $participant->mute,
            created_at: self::formatTimestamp($participant->created_at),
            updated_at: self::formatTimestamp($participant->updated_at),
            member: Lazy::whenLoaded(
                'member',
                $participant,
                fn (): ?EntityReferenceData => $participant->member !== null
                    ? EntityReferenceData::from(['id' => $participant->member->id, 'name' => $participant->member->name])
                    : null,
            ),
        );
    }
}
