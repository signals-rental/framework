<?php

namespace App\Data\Members;

use App\Models\Email;
use Spatie\LaravelData\Data;

class EmailData extends Data
{
    public function __construct(
        public int $id,
        public string $address,
        public ?int $type_id,
        public bool $is_primary,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(Email $email): self
    {
        return new self(
            id: $email->id,
            address: $email->address,
            type_id: $email->type_id,
            is_primary: $email->is_primary,
            created_at: $email->created_at->toIso8601String(),
            updated_at: $email->updated_at->toIso8601String(),
        );
    }
}
