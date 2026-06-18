<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for setting (or clearing) a line item's percentage discount.
 */
class SetItemDiscountData extends Data
{
    public function __construct(
        public ?string $discount_percent = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'discount_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
