<?php

namespace App\Data\Products;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class MergeProductData extends Data
{
    public function __construct(
        #[Required]
        public int $primary_id,
        #[Required]
        public int $secondary_id,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'primary_id' => ['required', 'integer', 'exists:products,id'],
            'secondary_id' => ['required', 'integer', 'exists:products,id', 'different:primary_id'],
        ];
    }
}
