<?php

namespace App\Data\Tax;

use App\Models\TaxRule;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class TaxRuleData extends Data
{
    public function __construct(
        public int $id,
        public int $organisation_tax_class_id,
        public int $product_tax_class_id,
        public int $tax_rate_id,
        public int $priority,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(TaxRule $taxRule): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $taxRule->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $taxRule->updated_at;

        return new self(
            id: $taxRule->id,
            organisation_tax_class_id: $taxRule->organisation_tax_class_id,
            product_tax_class_id: $taxRule->product_tax_class_id,
            tax_rate_id: $taxRule->tax_rate_id,
            priority: $taxRule->priority,
            is_active: $taxRule->is_active,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
