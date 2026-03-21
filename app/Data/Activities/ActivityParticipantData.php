<?php

namespace App\Data\Activities;

use App\Models\ActivityParticipant;
use Spatie\LaravelData\Data;

class ActivityParticipantData extends Data
{
    public function __construct(
        public int $id,
        public int $member_id,
        public string $member_name,
        public bool $mute,
    ) {}

    public static function fromModel(ActivityParticipant $participant): self
    {
        return new self(
            id: $participant->id,
            member_id: $participant->member_id,
            member_name: $participant->member->name ?? '',
            mute: $participant->mute,
        );
    }
}
