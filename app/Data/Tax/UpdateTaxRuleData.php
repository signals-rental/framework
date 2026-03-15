<?php

namespace App\Data\Tax;

use Spatie\LaravelData\Data;

class UpdateTaxRuleData extends Data
{
    public function __construct(
        public ?int $organisation_tax_class_id = null,
        public ?int $product_tax_class_id = null,
        public ?int $tax_rate_id = null,
        public ?int $priority = null,
        public ?bool $is_active = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'organisation_tax_class_id' => ['sometimes', 'integer', 'exists:organisation_tax_classes,id'],
            'product_tax_class_id' => ['sometimes', 'integer', 'exists:product_tax_classes,id'],
            'tax_rate_id' => ['sometimes', 'integer', 'exists:tax_rates,id'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
