<?php

namespace App\Data\Concerns;

use Spatie\LaravelData\Data;

/**
 * Lightweight DTO representing a related entity reference with {id, name} shape.
 *
 * Used by response DTOs for belongsTo relationships where only the ID and
 * display name of the related entity are needed (e.g., product_group, tax_class).
 */
class EntityReferenceData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
