<?php

namespace App\Data\TaxClasses;

use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class TaxClassData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public bool $is_default,
        public string $created_at,
        public string $updated_at,
    ) {}

    /**
     * Create from either OrganisationTaxClass or ProductTaxClass model.
     */
    public static function fromModel(OrganisationTaxClass|ProductTaxClass $taxClass): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $taxClass->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $taxClass->updated_at;

        return new self(
            id: $taxClass->id,
            name: $taxClass->name,
            description: $taxClass->description,
            is_default: $taxClass->is_default,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
