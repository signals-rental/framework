<?php

namespace App\Data\Rates;

use App\Enums\RateTransactionType;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

class UpdateProductRateData extends Data
{
    public function __construct(
        public ?int $rate_definition_id = null,
        public ?RateTransactionType $transaction_type = null,
        public ?int $price = null,
        public ?string $currency = null,
        public ?int $store_id = null,
        public ?string $valid_from = null,
        public ?string $valid_to = null,
        public ?int $priority = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'rate_definition_id' => ['sometimes', 'integer', 'exists:rate_definitions,id'],
            'transaction_type' => ['sometimes', new Enum(RateTransactionType::class)],
            'price' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'priority' => ['sometimes', 'integer'],
        ];
    }
}
