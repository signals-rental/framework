<?php

namespace App\Data\Members;

use App\Models\MemberRelationship;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class MemberRelationshipData extends Data
{
    public function __construct(
        public int $id,
        public int $member_id,
        public int $related_member_id,
        public ?string $relationship_type,
        public bool $is_primary,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(MemberRelationship $relationship): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $relationship->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $relationship->updated_at;

        return new self(
            id: $relationship->id,
            member_id: $relationship->member_id,
            related_member_id: $relationship->related_member_id,
            relationship_type: $relationship->relationship_type,
            is_primary: $relationship->is_primary,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
