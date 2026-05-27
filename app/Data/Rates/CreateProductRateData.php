<?php

namespace App\Data\Rates;

use App\Enums\RateTransactionType;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

class CreateProductRateData extends Data
{
    public function __construct(
        public int $product_id,
        public int $rate_definition_id,
        public RateTransactionType $transaction_type,
        public int $price,
        public string $currency,
        public ?int $store_id = null,
        public ?string $valid_from = null,
        public ?string $valid_to = null,
        public int $priority = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'rate_definition_id' => ['required', 'integer', 'exists:rate_definitions,id'],
            'transaction_type' => ['required', new Enum(RateTransactionType::class)],
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'priority' => ['sometimes', 'integer'],
        ];
    }
}
