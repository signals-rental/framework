<?php

namespace App\Data\Tax;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateTaxRuleData extends Data
{
    public function __construct(
        #[Required, Exists('organisation_tax_classes', 'id')]
        public int $organisation_tax_class_id,
        #[Required, Exists('product_tax_classes', 'id')]
        public int $product_tax_class_id,
        #[Required, Exists('tax_rates', 'id')]
        public int $tax_rate_id,
        public int $priority = 0,
        public bool $is_active = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'organisation_tax_class_id' => ['required', 'integer', 'exists:organisation_tax_classes,id'],
            'product_tax_class_id' => ['required', 'integer', 'exists:product_tax_classes,id'],
            'tax_rate_id' => ['required', 'integer', 'exists:tax_rates,id'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
