<?php

namespace App\Data\Tax;

use App\Models\TaxRate;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class TaxRateData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public string $rate,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(TaxRate $taxRate): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $taxRate->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $taxRate->updated_at;

        return new self(
            id: $taxRate->id,
            name: $taxRate->name,
            description: $taxRate->description,
            rate: (string) $taxRate->rate,
            is_active: $taxRate->is_active,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
