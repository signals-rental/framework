<?php

namespace App\Data\Members;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateMemberRelationshipData extends Data
{
    public function __construct(
        #[Required]
        public int $related_member_id,
        public ?string $relationship_type = null,
        public bool $is_primary = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'related_member_id' => ['required', 'integer', 'exists:members,id'],
            'relationship_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
