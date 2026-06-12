<?php

namespace App\Data\Products;

use Illuminate\Validation\Rule;
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
            'primary_id' => ['required', 'integer', Rule::exists('products', 'id')->withoutTrashed()],
            'secondary_id' => ['required', 'integer', Rule::exists('products', 'id')->withoutTrashed(), 'different:primary_id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'secondary_id.different' => 'A product cannot be merged into itself.',
        ];
    }
}
