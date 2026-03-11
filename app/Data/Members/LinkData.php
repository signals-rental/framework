<?php

namespace App\Data\Members;

use App\Models\Link;
use Spatie\LaravelData\Data;

class LinkData extends Data
{
    public function __construct(
        public int $id,
        public string $url,
        public ?string $name,
        public ?int $type_id,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(Link $link): self
    {
        return new self(
            id: $link->id,
            url: $link->url,
            name: $link->name,
            type_id: $link->type_id,
            created_at: $link->created_at->toIso8601String(),
            updated_at: $link->updated_at->toIso8601String(),
        );
    }
}
