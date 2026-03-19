<?php

namespace App\Data\Members;

use App\Models\Phone;
use Spatie\LaravelData\Data;

class PhoneData extends Data
{
    public function __construct(
        public int $id,
        public string $number,
        public ?string $country_code,
        public ?int $type_id,
        public bool $is_primary,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(Phone $phone): self
    {
        return new self(
            id: $phone->id,
            number: $phone->number,
            country_code: $phone->country_code,
            type_id: $phone->type_id,
            is_primary: $phone->is_primary,
            created_at: $phone->created_at->toIso8601String(),
            updated_at: $phone->updated_at->toIso8601String(),
        );
    }
}
